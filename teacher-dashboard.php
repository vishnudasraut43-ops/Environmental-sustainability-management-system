<?php
session_start();

// Check if teacher is logged in
if (!isset($_SESSION["teacher_id"])) {
    header("Location: teacher-login.php");
    exit();
}

// Display success/error messages
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $type = htmlspecialchars($_GET['type']);
    
    echo "<div class='message {$type}' style='margin: 20px auto; max-width: 1000px;'>
            {$message}
            <button onclick='this.parentElement.style.display=\"none\"' style='float: right; background: none; border: none; font-size: 20px; cursor: pointer;'>&times;</button>
          </div>";
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "esms_portal";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get teacher details
$teacher_id = $_SESSION["teacher_id"];
$query = "SELECT * FROM teachers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    session_destroy();
    header("Location: teacher-login.php?message=" . urlencode("❌ Teacher session expired. Please login again.") . "&type=error");
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Get current year for CL (12 per year)
$current_year = date('Y');

// Get CL balance for current year
$cl_query = "SELECT SUM(cl_taken) as cl_used FROM teacher_requests 
            WHERE teacher_id = ? 
            AND YEAR(submitted_at) = ? 
            AND request_type = 'cl' 
            AND hod_status = 'approved'";
$cl_stmt = $conn->prepare($cl_query);
$cl_stmt->bind_param("ii", $teacher_id, $current_year);
$cl_stmt->execute();
$cl_result = $cl_stmt->get_result();
$cl_used = $cl_result->fetch_assoc()['cl_used'] ?? 0;
$cl_remaining = 12 - $cl_used;
$cl_stmt->close();

// Get current month and year for Movement (3 per month)
$current_month = date('m');
$current_year = date('Y');

// Get Movement balance for current month
$movement_query = "SELECT COUNT(*) as movement_used FROM teacher_requests 
                  WHERE teacher_id = ? 
                  AND MONTH(submitted_at) = ? 
                  AND YEAR(submitted_at) = ? 
                  AND request_type = 'movement' 
                  AND hod_status = 'approved'";
$movement_stmt = $conn->prepare($movement_query);
$movement_stmt->bind_param("iii", $teacher_id, $current_month, $current_year);
$movement_stmt->execute();
$movement_result = $movement_stmt->get_result();
$movement_used = $movement_result->fetch_assoc()['movement_used'] ?? 0;
$movement_remaining = 3 - $movement_used;
$movement_stmt->close();

// On Duty is unlimited
$on_duty_remaining = 999;

// Get teacher's applications for current month
$applications_query = "SELECT * FROM teacher_requests WHERE teacher_id = ? AND MONTH(submitted_at) = ? AND YEAR(submitted_at) = ? ORDER BY submitted_at DESC";
$apps_stmt = $conn->prepare($applications_query);
$apps_stmt->bind_param("iii", $teacher_id, $current_month, $current_year);
$apps_stmt->execute();
$applications = $apps_stmt->get_result();

// Get notifications
$notifications_query = "SELECT * FROM teacher_notifications WHERE teacher_id = ? AND is_read = FALSE ORDER BY created_at DESC";
$notif_stmt = $conn->prepare($notifications_query);
$notif_stmt->bind_param("i", $teacher_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

$apps_stmt->close();
$notif_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - ESMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header h1 { color: #2c3e50; margin-bottom: 5px; }
        .teacher-welcome { color: #666; }
        .logout { background: #e74c3c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        .leave-balance { background: #e7f3ff; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .balance-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .balance-item { background: white; padding: 15px; border-radius: 8px; text-align: center; }
        .balance-value { font-size: 24px; font-weight: bold; }
        .balance-value.cl { color: #3498db; }
        .balance-value.movement { color: #e74c3c; }
        .balance-value.on-duty { color: #2ecc71; }
        .options-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
        .option-card { background: white; padding: 25px; border-radius: 10px; text-align: center; text-decoration: none; color: #333; transition: transform 0.2s; }
        .option-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .application-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fafafa; }
        .status-tracker { display: flex; justify-content: space-between; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .month-indicator { background: #2c3e50; color: white; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; font-weight: bold; }
        .notification-badge { background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 12px; margin-left: 5px; }
        .status-pending { color: #007bff; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <div>
                <h1>Teacher Dashboard</h1>
                <div class="teacher-welcome">
                    Welcome back, <strong><?php echo htmlspecialchars($teacher['name'] ?? 'Teacher'); ?></strong>! 
                    (Employee ID: <?php echo htmlspecialchars($teacher['employee_id'] ?? 'N/A'); ?>)
                    <?php if ($notifications->num_rows > 0): ?>
                        <span class="notification-badge">
                            <?php echo $notifications->num_rows; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="logout.php" class="logout">Logout</a>
        </div>

        <!-- Leave Balance Section -->
        <div class="leave-balance">
            <h3>Your Leave Balance</h3>
            <div class="balance-grid">
                <div class="balance-item">
                    <strong>Casual Leave (CL)</strong>
                    <div class="balance-value cl"><?php echo max(0, $cl_remaining); ?> days</div>
                    <small style="color: #666;">12 days per year (<?php echo $current_year; ?>)</small>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Used: <?php echo $cl_used; ?> days
                    </div>
                </div>
                <div class="balance-item">
                    <strong>Movement Requests</strong>
                    <div class="balance-value movement"><?php echo max(0, $movement_remaining); ?></div>
                    <small style="color: #666;">3 per month (<?php echo date('F Y'); ?>)</small>
                    <div style="font-size: 12px; color: #666; margin-top: 5px;">
                        Used: <?php echo $movement_used; ?> this month
                    </div>
                </div>
                <div class="balance-item">
                    <strong>On Duty Leave</strong>
                    <div class="balance-value on-duty">
                        <?php echo $on_duty_remaining == 999 ? 'Unlimited' : $on_duty_remaining . ' days'; ?>
                    </div>
                    <small style="color: #666;">No limit per year</small>
                </div>
            </div>
        </div>

        <!-- Three Options Grid -->
        <div class="options-grid">
            <a href="apply-cl.php" class="option-card" style="border-top: 4px solid #3498db;">
                <div style="font-size: 48px; margin-bottom: 15px;">📅</div>
                <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Apply Casual Leave</div>
                <div style="color: #666;">Apply for casual leave</div>
                <div style="margin-top: 10px; padding: 5px; background: #e7f3ff; border-radius: 5px;">
                    <strong><?php echo max(0, $cl_remaining); ?>/12 days remaining</strong>
                </div>
            </a>
            
            <a href="apply-movement.php" class="option-card" style="border-top: 4px solid #e74c3c;">
                <div style="font-size: 48px; margin-bottom: 15px;">🚶</div>
                <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">Movement Request</div>
                <div style="color: #666;">Request for movement</div>
                <div style="margin-top: 10px; padding: 5px; background: #ffe7e7; border-radius: 5px;">
                    <strong><?php echo max(0, $movement_remaining); ?>/3 remaining this month</strong>
                </div>
            </a>
            
            <a href="apply-on-duty.php" class="option-card" style="border-top: 4px solid #2ecc71;">
                <div style="font-size: 48px; margin-bottom: 15px;">🎯</div>
                <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">On Duty Request</div>
                <div style="color: #666;">Apply for on-duty leave</div>
                <div style="margin-top: 10px; padding: 5px; background: #e7ffe7; border-radius: 5px;">
                    <strong>Unlimited</strong>
                </div>
            </a>
        </div>

        <!-- Applications Status Section -->
        <div style="background: white; padding: 20px; border-radius: 10px; margin-top: 20px;">
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Your Applications - <?php echo date('F Y'); ?></h2>
            <?php if ($applications->num_rows > 0): ?>
                <div style="display: grid; gap: 15px;">
                    <?php while($app = $applications->fetch_assoc()): 
                        $request_data = json_decode($app['request_data'] ?? '{}', true);
                    ?>
                    <div class="application-card">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <div style="font-weight: bold; color: #2c3e50;">
                                <?php 
                                $type_display = '';
                                switch($app['request_type'] ?? '') {
                                    case 'cl': $type_display = 'CASUAL LEAVE'; break;
                                    case 'movement': $type_display = 'MOVEMENT'; break;
                                    case 'on_duty': $type_display = 'ON DUTY'; break;
                                    default: $type_display = 'APPLICATION';
                                }
                                echo $type_display; 
                                ?> - 
                                <?php echo isset($request_data['reason']) ? htmlspecialchars($request_data['reason']) : 
                                      (isset($request_data['purpose']) ? htmlspecialchars($request_data['purpose']) : 'Application'); ?>
                            </div>
                            <div style="color: #666;">
                                <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                            </div>
                        </div>
                        
                        <!-- Status Tracker -->
                        <div class="status-tracker">
                            <div style="text-align: center; flex: 1; padding: 10px;" class="<?php echo $app['hod_status'] == 'approved' ? 'status-approved' : ($app['hod_status'] == 'rejected' ? 'status-rejected' : 'status-pending'); ?>">
                                HOD: <?php echo ucfirst($app['hod_status'] ?? 'pending'); ?>
                            </div>
                            <div style="text-align: center; flex: 1; padding: 10px;" class="<?php echo $app['os_status'] == 'approved' ? 'status-approved' : ($app['os_status'] == 'rejected' ? 'status-rejected' : 'status-pending'); ?>">
                                OS: <?php echo ucfirst($app['os_status'] ?? 'pending'); ?>
                            </div>
                            <div style="text-align: center; flex: 1; padding: 10px;" class="<?php echo $app['principal_status'] == 'approved' ? 'status-approved' : ($app['principal_status'] == 'rejected' ? 'status-rejected' : 'status-pending'); ?>">
                                Principal: <?php echo ucfirst($app['principal_status'] ?? 'pending'); ?>
                            </div>
                            
                        </div>
                        
                        <!-- Application Details -->
                        <div style="margin-top: 10px; font-size: 14px; color: #666;">
                            <?php if (($app['request_type'] ?? '') == 'cl'): ?>
                                <div><strong>From:</strong> <?php echo htmlspecialchars($request_data['from_date'] ?? ''); ?> <strong>To:</strong> <?php echo htmlspecialchars($request_data['to_date'] ?? ''); ?></div>
                                <div><strong>Days:</strong> <?php echo htmlspecialchars($request_data['days'] ?? ''); ?></div>
                                <div><strong>Reason:</strong> <?php echo htmlspecialchars($request_data['reason'] ?? ''); ?></div>
                            <?php elseif (($app['request_type'] ?? '') == 'movement'): ?>
                                <div><strong>Date:</strong> <?php echo htmlspecialchars($request_data['movement_date'] ?? ''); ?></div>
                                <div><strong>From Time:</strong> <?php echo htmlspecialchars($request_data['from_time'] ?? ''); ?> <strong>To Time:</strong> <?php echo htmlspecialchars($request_data['to_time'] ?? ''); ?></div>
                                <div><strong>Purpose:</strong> <?php echo htmlspecialchars($request_data['purpose'] ?? ''); ?></div>
                            <?php else: ?>
                                <div><strong>From:</strong> <?php echo htmlspecialchars($request_data['from_date'] ?? ''); ?> <strong>To:</strong> <?php echo htmlspecialchars($request_data['to_date'] ?? ''); ?></div>
                                <div><strong>Purpose:</strong> <?php echo htmlspecialchars($request_data['purpose'] ?? ''); ?></div>
                                <div><strong>Event:</strong> <?php echo htmlspecialchars($request_data['event_name'] ?? ''); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px;">No applications submitted for <?php echo date('F Y'); ?>.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>