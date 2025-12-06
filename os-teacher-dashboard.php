<?php
session_start();

// Check if OS is logged in
if (!isset($_SESSION["os_id"])) {
    header("Location: os-login.php");
    exit();
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

// Process application approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks']);
    
    // Get request details for balance check
    $request_query = "SELECT tr.* FROM teacher_requests tr WHERE tr.id = ?";
    $req_stmt = $conn->prepare($request_query);
    $req_stmt->bind_param("i", $request_id);
    $req_stmt->execute();
    $req_result = $req_stmt->get_result();
    $request_data = $req_result->fetch_assoc();
    $req_stmt->close();
    
    $json_data = json_decode($request_data['request_data'], true);
    
    if ($action == 'approve') {
        // Check leave balance
        $can_approve = true;
        
        if ($request_data['request_type'] == 'cl') {
            // Check CL balance for the year
            $days_requested = floatval($json_data['days']);
            $current_year = date('Y');
            
            $cl_used_query = "SELECT SUM(cl_taken) as cl_used FROM teacher_requests 
                            WHERE teacher_id = ? 
                            AND YEAR(submitted_at) = ? 
                            AND request_type = 'cl' 
                            AND hod_status = 'approved'";
            $cl_stmt = $conn->prepare($cl_used_query);
            $cl_stmt->bind_param("ii", $request_data['teacher_id'], $current_year);
            $cl_stmt->execute();
            $cl_result = $cl_stmt->get_result();
            $cl_used = $cl_result->fetch_assoc()['cl_used'] ?? 0;
            $cl_remaining = 12 - $cl_used;
            $cl_stmt->close();
            
            if ($days_requested > $cl_remaining) {
                $can_approve = false;
                $error = "Insufficient CL balance! Available: " . $cl_remaining . " days, Requested: " . $days_requested . " days";
            }
        } elseif ($request_data['request_type'] == 'movement') {
            // Check Movement balance for the month
            $current_month = date('m');
            $current_year = date('Y');
            
            $movement_used_query = "SELECT COUNT(*) as movement_used FROM teacher_requests 
                                  WHERE teacher_id = ? 
                                  AND MONTH(submitted_at) = ? 
                                  AND YEAR(submitted_at) = ? 
                                  AND request_type = 'movement' 
                                  AND hod_status = 'approved'";
            $movement_stmt = $conn->prepare($movement_used_query);
            $movement_stmt->bind_param("iii", $request_data['teacher_id'], $current_month, $current_year);
            $movement_stmt->execute();
            $movement_result = $movement_stmt->get_result();
            $movement_used = $movement_result->fetch_assoc()['movement_used'] ?? 0;
            $movement_remaining = 3 - $movement_used;
            $movement_stmt->close();
            
            if ($movement_remaining <= 0) {
                $can_approve = false;
                $error = "No movement requests remaining for this month!";
            }
        }
        // On Duty has no limits
        
        if ($can_approve) {
            $status = 'approved';
            $next_status = 'pending'; // Move to Principal
        } else {
            $status = 'rejected';
            $next_status = 'rejected';
        }
    } else {
        $status = 'rejected';
        $next_status = 'rejected';
    }
    
    if (!isset($error) || $action == 'reject') {
        $update_query = "UPDATE teacher_requests SET os_status = ?, os_remarks = ?, os_updated_at = NOW(), principal_status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sssi", $status, $remarks, $next_status, $request_id);
        
        if ($stmt->execute()) {
            // Create notification for teacher
            $notification_query = "INSERT INTO teacher_notifications (teacher_id, request_id, title, message) 
                                  VALUES (?, ?, 'OS Action', ?)";
            $notification_stmt = $conn->prepare($notification_query);
            $notification_message = "Your " . strtoupper($request_data['request_type']) . " application has been " . $status . " by Office Staff.";
            if (!empty($remarks)) {
                $notification_message .= " Remarks: " . $remarks;
            }
            $notification_stmt->bind_param("iis", $request_data['teacher_id'], $request_id, $notification_message);
            $notification_stmt->execute();
            $notification_stmt->close();
            
            $success = "Application " . $status . " successfully!";
        } else {
            $error = "Error updating application: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get pending teacher applications (approved by HOD, pending OS)
$query = "SELECT tr.*, t.name as teacher_name, t.employee_id, t.department
          FROM teacher_requests tr 
          JOIN teachers t ON tr.teacher_id = t.id 
          WHERE tr.hod_status = 'approved' AND tr.os_status = 'pending'
          ORDER BY tr.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$applications = $stmt->get_result();

// Get today's date
$today_date = date('l, F j, Y');

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Staff Dashboard - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .college-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            color: white;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 4px solid #f59e0b;
        }
        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .college-logo {
            height: 80px;
            width: auto;
            margin-right: 20px;
            border-radius: 8px;
        }
        .college-text {
            text-align: center;
            flex: 1;
        }
        .college-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .college-subtitle {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.4;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-rejected { background: #fecaca; color: #dc2626; }
        .history-filters {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            .college-logo {
                margin-right: 0;
                margin-bottom: 10px;
                height: 60px;
            }
            .college-name {
                font-size: 20px;
            }
            .college-subtitle {
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- College Header with Logo -->
    <div class="college-header">
        <div class="header-content">
            <!-- College Logo -->
            <img src="college-logo.jpg" alt="Yashwantrao Chavan Institute of Polytechnic, Beed" class="college-logo" 
                 onerror="this.style.display='none'">
            
            <!-- College Text Information -->
            <div class="college-text">
                <div class="college-name">YASHWANTRAO CHAVAN INSTITUTE OF POLYTECHNIC, BEED</div>
                <div class="college-subtitle">
                    Approved by AICTE, New Delhi & DTE, Mumbai | Affiliated to MSBTE of Mumbai
                </div>
                <div class="college-subtitle">
                    Barshi Road, Beed-431122 (Maharashtra)
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <!-- Dashboard Header with Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <!-- Welcome Card -->
            <div class="stats-card glass-card p-6 col-span-1 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Office Staff Dashboard</h1>
                        <p class="text-gray-600 text-lg">
                            <i class="fas fa-user-tie text-purple-500 mr-2"></i>
                            Welcome back, <span class="font-semibold text-purple-600">Office Staff</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="date-display">
                            <i class="fas fa-calendar-day text-yellow-300 mr-2"></i>
                            <span class="font-bold"><?php echo $today_date; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Applications Card -->
            <div class="stats-card bg-gradient-to-r from-pink-900 to-pink-500 text-white p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-3xl text-white opacity-80"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-2xl font-bold"><?php echo $applications->num_rows; ?></h3>
                        <p class="text-white opacity-90">Pending Applications</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="stats-card bg-gradient-to-r from-green-400 to-blue-500 text-white p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tasks text-3xl text-white opacity-80"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-2xl font-bold">Balance Check</h3>
                        <p class="text-white opacity-90">Leave Verification</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logout Button -->
        <div class="flex justify-end mb-6">
            <a href="logout.php" class="btn-danger flex items-center space-x-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <?php if (isset($success)): ?>
            <div class="bg-gradient-to-r from-green-400 to-green-500 text-white p-4 rounded-xl mb-6 flex items-center shadow-lg">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <div>
                    <h4 class="font-bold text-lg">Success!</h4>
                    <p><?php echo $success; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-gradient-to-r from-red-400 to-red-500 text-white p-4 rounded-xl mb-6 flex items-center shadow-lg">
                <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                <div>
                    <h4 class="font-bold text-lg">Attention Required!</h4>
                    <p><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Applications Section -->
        <div class="glass-card rounded-2xl overflow-hidden">
            <div class="gradient-bg text-white px-6 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold">
                            <i class="fas fa-clipboard-check mr-3"></i>
                            Teacher Applications - Leave Balance Verification
                        </h3>
                        <p class="text-blue-100 mt-1">
                            Applications approved by HOD, waiting for leave balance verification
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 px-4 py-2 rounded-full">
                        <span class="font-bold text-white"><?php echo $applications->num_rows; ?> Pending</span>
                    </div>
                </div>
            </div>

            <div class="bg-white">
                <?php if ($applications->num_rows > 0): ?>
                    <div class="divide-y divide-gray-100">
                        <?php 
                        // Reconnect for balance calculations
                        $conn = new mysqli($servername, $username, $password, $dbname);
                        
                        while($app = $applications->fetch_assoc()): 
                            $request_data = json_decode($app['request_data'], true);
                            
                            // Calculate current balances for this teacher
                            $current_year = date('Y');
                            $current_month = date('m');
                            
                            // CL Balance
                            $cl_used_query = "SELECT SUM(cl_taken) as cl_used FROM teacher_requests 
                                            WHERE teacher_id = ? 
                                            AND YEAR(submitted_at) = ? 
                                            AND request_type = 'cl' 
                                            AND hod_status = 'approved'";
                            $cl_stmt = $conn->prepare($cl_used_query);
                            $cl_stmt->bind_param("ii", $app['teacher_id'], $current_year);
                            $cl_stmt->execute();
                            $cl_result = $cl_stmt->get_result();
                            $cl_used = $cl_result->fetch_assoc()['cl_used'] ?? 0;
                            $cl_remaining = 12 - $cl_used;
                            $cl_stmt->close();
                            
                            // Movement Balance
                            $movement_used_query = "SELECT COUNT(*) as movement_used FROM teacher_requests 
                                                  WHERE teacher_id = ? 
                                                  AND MONTH(submitted_at) = ? 
                                                  AND YEAR(submitted_at) = ? 
                                                  AND request_type = 'movement' 
                                                  AND hod_status = 'approved'";
                            $movement_stmt = $conn->prepare($movement_used_query);
                            $movement_stmt->bind_param("iii", $app['teacher_id'], $current_month, $current_year);
                            $movement_stmt->execute();
                            $movement_result = $movement_stmt->get_result();
                            $movement_used = $movement_result->fetch_assoc()['movement_used'] ?? 0;
                            $movement_remaining = 3 - $movement_used;
                            $movement_stmt->close();
                            
                            // Check if this application can be approved
                            $can_approve = true;
                            $balance_status = 'info';
                            $balance_message = '';
                            
                            if ($app['request_type'] == 'cl') {
                                $days_requested = floatval($request_data['days']);
                                if ($days_requested > $cl_remaining) {
                                    $can_approve = false;
                                    $balance_status = 'danger';
                                    $balance_message = "Insufficient CL balance! Available: {$cl_remaining} days, Requested: {$days_requested} days";
                                } else {
                                    $balance_status = 'info';
                                    $balance_message = "CL Balance: {$cl_remaining}/12 days remaining this year";
                                }
                            } elseif ($app['request_type'] == 'movement') {
                                if ($movement_remaining <= 0) {
                                    $can_approve = false;
                                    $balance_status = 'danger';
                                    $balance_message = "No movement requests remaining for this month!";
                                } else {
                                    $balance_status = 'info';
                                    $balance_message = "Movement Balance: {$movement_remaining}/3 remaining this month";
                                }
                            } else {
                                $balance_status = 'info';
                                $balance_message = "On Duty - No balance limits";
                            }
                        ?>
                        <div class="application-card p-6 hover:bg-gray-50">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user-tie text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-gray-800">
                                            <?php echo $app['teacher_name']; ?>
                                        </h4>
                                        <p class="text-gray-600">
                                            <span class="font-semibold"><?php echo $app['employee_id']; ?></span> • 
                                            <?php echo $app['department']; ?> • 
                                            <span class="capitalize"><?php echo $app['request_type']; ?></span>
                                        </p>
                                        <div class="flex items-center space-x-3 mt-2">
                                            <span class="status-badge status-approved">
                                                <i class="fas fa-check mr-1"></i>HOD Approved
                                            </span>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock mr-1"></i>OS Verification
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium 
                                        <?php echo $app['request_type'] == 'cl' ? 'bg-blue-100 text-blue-800 border border-blue-200' : 
                                               ($app['request_type'] == 'movement' ? 'bg-red-100 text-red-800 border border-red-200' : 
                                               'bg-green-100 text-green-800 border border-green-200'); ?>">
                                        <i class="fas <?php echo $app['request_type'] == 'cl' ? 'fa-calendar-day' : 
                                                              ($app['request_type'] == 'movement' ? 'fa-walking' : 'fa-briefcase'); ?> mr-2"></i>
                                        <?php echo strtoupper($app['request_type']); ?>
                                    </span>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Balance Information -->
                            <div class="balance-<?php echo $balance_status; ?> mb-6">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-bold text-gray-800 text-lg mb-3">
                                        <i class="fas fa-chart-pie mr-2"></i>
                                        Leave Balance Status
                                    </h5>
                                    <?php if (!$can_approve): ?>
                                        <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Insufficient Balance
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                            <i class="fas fa-check-circle mr-1"></i>Balance Available
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-3">
                                    <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                        <div class="text-2xl font-bold text-blue-600"><?php echo $cl_remaining; ?>/12</div>
                                        <div class="text-gray-600">CL Days (<?php echo $current_year; ?>)</div>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                        <div class="text-2xl font-bold text-red-600"><?php echo $movement_remaining; ?>/3</div>
                                        <div class="text-gray-600">Movements (<?php echo date('F Y'); ?>)</div>
                                    </div>
                                    <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                                        <div class="text-2xl font-bold text-green-600">∞</div>
                                        <div class="text-gray-600">On Duty</div>
                                    </div>
                                </div>
                                <p class="text-sm font-semibold <?php echo $balance_status == 'danger' ? 'text-red-700' : 'text-gray-700'; ?>">
                                    <i class="fas <?php echo $balance_status == 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?> mr-2"></i>
                                    <?php echo $balance_message; ?>
                                </p>
                            </div>

                            <!-- Application Details -->
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-5 mb-6 border border-gray-200">
                                <h5 class="font-bold text-gray-800 text-lg mb-4 flex items-center">
                                    <i class="fas fa-file-alt mr-2 text-purple-500"></i>
                                    Application Details
                                </h5>
                                <?php if ($app['request_type'] == 'cl'): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">From Date:</strong> 
                                            <span class="text-blue-600 font-semibold"><?php echo $request_data['from_date']; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">To Date:</strong> 
                                            <span class="text-blue-600 font-semibold"><?php echo $request_data['to_date']; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Total Days:</strong> 
                                            <span class="text-orange-600 font-semibold"><?php echo $request_data['days']; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Reason:</strong> 
                                            <span class="text-gray-800"><?php echo $request_data['reason']; ?></span>
                                        </div>
                                        <?php if (!empty($request_data['description'])): ?>
                                            <div class="md:col-span-2 bg-white p-3 rounded-lg shadow-sm">
                                                <strong class="text-gray-700">Description:</strong> 
                                                <span class="text-gray-800"><?php echo $request_data['description']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($app['request_type'] == 'movement'): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Date:</strong> 
                                            <span class="text-blue-600 font-semibold"><?php echo $request_data['movement_date'] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">From Time:</strong> 
                                            <span class="text-green-600 font-semibold"><?php echo $request_data['from_time'] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">To Time:</strong> 
                                            <span class="text-red-600 font-semibold"><?php echo $request_data['to_time'] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Purpose:</strong> 
                                            <span class="text-gray-800"><?php echo $request_data['purpose'] ?? 'No reason provided'; ?></span>
                                        </div>
                                        <?php if (!empty($request_data['destination'])): ?>
                                            <div class="md:col-span-2 bg-white p-3 rounded-lg shadow-sm">
                                                <strong class="text-gray-700">Destination:</strong> 
                                                <span class="text-purple-600 font-semibold"><?php echo $request_data['destination']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($request_data['remarks'])): ?>
                                            <div class="md:col-span-2 bg-white p-3 rounded-lg shadow-sm">
                                                <strong class="text-gray-700">Remarks:</strong> 
                                                <span class="text-gray-800"><?php echo $request_data['remarks']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($app['request_type'] == 'on_duty'): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">From Date:</strong> 
                                            <span class="text-blue-600 font-semibold"><?php echo $request_data['from_date'] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">To Date:</strong> 
                                            <span class="text-blue-600 font-semibold"><?php echo $request_data['to_date'] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Total Days:</strong> 
                                            <span class="text-orange-600 font-semibold"><?php echo $request_data['daysod']; ?></span>
                                        </div>
                                        <div class="bg-white p-3 rounded-lg shadow-sm">
                                            <strong class="text-gray-700">Purpose:</strong> 
                                            <span class="text-gray-800"><?php echo $request_data['purpose'] ?? 'No reason provided'; ?></span>
                                        </div>
                                        <?php if (!empty($request_data['event_name'])): ?>
                                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                                <strong class="text-gray-700">Event Name:</strong> 
                                                <span class="text-purple-600 font-semibold"><?php echo $request_data['event_name']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($request_data['venue'])): ?>
                                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                                <strong class="text-gray-700">Venue:</strong> 
                                                <span class="text-green-600 font-semibold"><?php echo $request_data['venue']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($request_data['lecture_required']) && $request_data['lecture_required'] == 'yes'): ?>
                                            <div class="md:col-span-2 bg-blue-50 p-4 rounded-lg border border-blue-200">
                                                <h6 class="font-bold text-blue-800 mb-2 flex items-center">
                                                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                                                    Lecture Arrangement Required
                                                </h6>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                    <div><strong>Substitute:</strong> <?php echo $request_data['lecture_substitute'] ?? 'Not specified'; ?></div>
                                                    <div><strong>Subjects:</strong> <?php echo $request_data['subjects_covered'] ?? 'Not specified'; ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($request_data['practical_required']) && $request_data['practical_required'] == 'yes'): ?>
                                            <div class="md:col-span-2 bg-green-50 p-4 rounded-lg border border-green-200 mt-3">
                                                <h6 class="font-bold text-green-800 mb-2 flex items-center">
                                                    <i class="fas fa-flask mr-2"></i>
                                                    Practical Arrangement Required
                                                </h6>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                                    <div><strong>Substitute:</strong> <?php echo $request_data['practical_substitute'] ?? 'Not specified'; ?></div>
                                                    <div><strong>Practicals:</strong> <?php echo $request_data['practicals_covered'] ?? 'Not specified'; ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($request_data['additional_info'])): ?>
                                            <div class="md:col-span-2 bg-gray-50 p-3 rounded-lg">
                                                <strong class="text-gray-700">Additional Info:</strong> 
                                                <span class="text-gray-800"><?php echo $request_data['additional_info']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                <div class="mb-6">
                                    <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-3 flex items-center">
                                        <i class="fas fa-comment-dots mr-2 text-purple-500"></i>
                                        Your Remarks
                                    </label>
                                    <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                              rows="4"
                                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition duration-200"
                                              placeholder="Add your verification remarks here..."><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
                                </div>
                                <div class="flex space-x-4">
                                    <?php if ($can_approve): ?>
                                        <button type="submit" name="action" value="approve" 
                                                class="btn-success flex items-center space-x-2">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Approve & Send to Principal</span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="bg-gray-400 text-white px-6 py-3 rounded-xl font-semibold flex items-center space-x-2 cursor-not-allowed opacity-70"
                                                disabled>
                                            <i class="fas fa-ban"></i>
                                            <span>Cannot Approve (Insufficient Balance)</span>
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="reject" 
                                            class="btn-danger flex items-center space-x-2">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Reject Application</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endwhile; 
                        $conn->close();
                        ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-check text-white text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-700 mb-3">All Caught Up!</h3>
                        <p class="text-gray-500 text-lg max-w-md mx-auto">
                            No applications pending balance verification. All teacher applications have been processed.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-gray-800 to-gray-900 text-white py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-300">
                &copy; <?php echo date('Y'); ?> Yashwantrao Chavan Institute of Polytechnic, Beed. All rights reserved.
            </p>
            <p class="text-gray-400 text-sm mt-2">
                ESMS Portal - Office Staff Dashboard
            </p>
        </div>
    </footer>

    <script>
        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.stats-card, .application-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add hover effects to buttons
            const buttons = document.querySelectorAll('button, .btn-primary, .btn-success, .btn-danger');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>