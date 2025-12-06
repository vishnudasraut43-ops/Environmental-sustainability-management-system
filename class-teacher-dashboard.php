
<?php
session_start();

// Check if class teacher is logged in
if (!isset($_SESSION["class_teacher_id"])) {
    header("Location: class-teacher-login.php");
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
    
    if ($action == 'approve') {
        $status = 'approved';
    } else {
        $status = 'rejected';
    }
    
    $update_query = "UPDATE student_requests SET class_teacher_status = ?, class_teacher_remarks = ?, class_teacher_updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $status, $remarks, $request_id);
    
    if ($stmt->execute()) {
        $success = "Application " . $status . " successfully!";
    } else {
        $error = "Error updating application: " . $stmt->error;
    }
    $stmt->close();
}

// Get pending applications for this class teacher's branch and year
$branch = $_SESSION["class_teacher_branch"];
$year = $_SESSION["class_teacher_year"];

$query = "SELECT sr.*, s.name as student_name, s.roll_no, s.enrollment_no 
          FROM student_requests sr 
          JOIN students s ON sr.student_id = s.id 
          WHERE sr.branch = ? AND sr.year = ? AND sr.class_teacher_status = 'pending'
          ORDER BY sr.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $branch, $year);
$stmt->execute();
$applications = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_pending,
    SUM(class_teacher_status = 'approved') as total_approved,
    SUM(class_teacher_status = 'rejected') as total_rejected
    FROM student_requests 
    WHERE branch = ? AND year = ?";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("si", $branch, $year);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

$stats_stmt->close();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Teacher Dashboard - ESMS</title>
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
<body class="bg-gray-50 min-h-screen">
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Class Teacher Dashboard</h1>
                    <p class="text-gray-600">
                        <?php echo $_SESSION["class_teacher_name"]; ?> | 
                        <?php echo $_SESSION["class_teacher_branch"]; ?> - 
                        Year <?php echo $_SESSION["class_teacher_year"]; ?>
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="class-teacher-login.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Switch Class
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Applications</dt>
                    <dd class="mt-1 text-3xl font-semibold text-yellow-600"><?php echo $stats['total_pending']; ?></dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600"><?php echo $stats['total_approved']; ?></dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Rejected</dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600"><?php echo $stats['total_rejected']; ?></dd>
                </div>
            </div>
        </div>

        <!-- Applications -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Pending Applications - <?php echo $branch; ?> Year <?php echo $year; ?>
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Applications from students of your class waiting for your approval.
                </p>
            </div>

            <div class="border-t border-gray-200">
                <?php if ($applications->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while($app = $applications->fetch_assoc()): 
                            $request_data = json_decode($app['request_data'], true);
                        ?>
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-medium text-gray-900">
                                        <?php echo $app['student_name']; ?> (<?php echo $app['roll_no']; ?>)
                                    </h4>
                                    <p class="text-sm text-gray-500">
                                        <?php echo strtoupper($app['request_type']); ?> Application • 
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <?php echo strtoupper($app['request_type']); ?>
                                </span>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <?php if ($app['request_type'] == 'leave'): ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>From:</strong> <?php echo $request_data['from_date']; ?></div>
                                        <div><strong>To:</strong> <?php echo $request_data['to_date']; ?></div>
                                        <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                        <div><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                        <div class="col-span-2"><strong>Description:</strong> <?php echo $request_data['description']; ?></div>
                                    </div>
                                <?php elseif ($app['request_type'] == 'getpass'): ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Date:</strong> <?php echo $request_data['pass_date']; ?></div>
                                        <div><strong>Out Time:</strong> <?php echo $request_data['out_time']; ?></div>
                                        <div><strong>Return Time:</strong> <?php echo $request_data['return_time']; ?></div>
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div class="col-span-2"><strong>Destination:</strong> <?php echo $request_data['destination']; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                        <div><strong>Copies:</strong> <?php echo $request_data['copies']; ?></div>
                                        <div><strong>Urgency:</strong> <?php echo $request_data['urgency']; ?></div>
                                        <?php if (!empty($request_data['additional_info'])): ?>
                                        <div class="col-span-2"><strong>Additional Info:</strong> <?php echo $request_data['additional_info']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                <div class="mb-4">
                                    <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                        Your Remarks (Optional)
                                    </label>
                                    <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                              rows="3"
                                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                              placeholder="Add any remarks or instructions..."></textarea>
                                </div>
                                <div class="flex space-x-3">
                                    <button type="submit" name="action" value="approve" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        ✓ Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        ✗ Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No pending applications</h3>
                        <p class="mt-1 text-sm text-gray-500">All applications from your class have been reviewed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
