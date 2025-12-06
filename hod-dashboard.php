<?php
session_start();

// Check if HOD is logged in
if (!isset($_SESSION["hod_id"])) {
    header("Location: hod-login.php");
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
    $request_type = $_POST['request_type'];
    $application_type = $_POST['application_type']; // student or teacher
    
    if ($action == 'approve') {
        $hod_status = 'approved';
        // For bonafide student applications, send to principal
        if ($application_type == 'student' && $request_type == 'bonafide') {
            $principal_status = 'pending'; // Move to Principal
        } else {
            $principal_status = 'approved'; // Directly approved for other types
        }
    } else {
        $hod_status = 'rejected';
        $principal_status = 'rejected';
    }
    
    if ($application_type == 'student') {
        // Update student request
        $update_query = "UPDATE student_requests SET hod_status = ?, hod_remarks = ?, hod_updated_at = NOW(), principal_status = ? WHERE id = ?";
    } else {
        // Update teacher request
        $update_query = "UPDATE teacher_requests SET hod_status = ?, hod_remarks = ?, hod_updated_at = NOW() WHERE id = ?";
        $principal_status = null; // Not needed for teacher requests
    }
    
    $stmt = $conn->prepare($update_query);
    
    if ($application_type == 'student') {
        $stmt->bind_param("sssi", $hod_status, $remarks, $principal_status, $request_id);
    } else {
        $stmt->bind_param("ssi", $hod_status, $remarks, $request_id);
    }
    
    if ($stmt->execute()) {
        if ($action == 'approve' && $application_type == 'student' && $request_type == 'bonafide') {
            $success = "Bonafide application approved successfully! Forwarded to Principal.";
        } else {
            $success = ucfirst($application_type) . " application " . $hod_status . " successfully!";
        }
    } else {
        $error = "Error updating application: " . $stmt->error;
    }
    $stmt->close();
}

// Get HOD's department
$branch = $_SESSION["hod_branch"];

// Get pending student applications for this HOD's department
$student_query = "SELECT sr.*, s.name as student_name, s.roll_no, s.year
          FROM student_requests sr 
          JOIN students s ON sr.student_id = s.id 
          WHERE s.branch = ? AND sr.hod_status = 'pending'
          ORDER BY sr.submitted_at DESC";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("s", $branch);
$student_stmt->execute();
$student_applications = $student_stmt->get_result();

// Get pending teacher applications for this HOD's department
$teacher_query = "SELECT tr.*, t.name as teacher_name
                  FROM teacher_requests tr
                  JOIN teachers t ON tr.teacher_id = t.id 
                  WHERE t.department = ? AND tr.hod_status = 'pending'
                  ORDER BY tr.submitted_at DESC";
$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->bind_param("s", $branch);
$teacher_stmt->execute();
$teacher_applications = $teacher_stmt->get_result();

// Get history data if history tab is active
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'students';
$history_type = isset($_GET['history_type']) ? $_GET['history_type'] : 'student';

if ($active_tab == 'history') {
    if ($history_type == 'student') {
        // Student history query for HOD's department
        $student_history_query = "SELECT sr.*, s.name as student_name, s.roll_no, s.year, s.branch,
                                         sr.submitted_at, sr.hod_updated_at, sr.hod_remarks
                                  FROM student_requests sr 
                                  JOIN students s ON sr.student_id = s.id 
                                  WHERE s.branch = ? AND sr.hod_status != 'pending'
                                  ORDER BY sr.hod_updated_at DESC";
        $student_history_stmt = $conn->prepare($student_history_query);
        $student_history_stmt->bind_param("s", $branch);
        $student_history_stmt->execute();
        $student_history_result = $student_history_stmt->get_result();
    } else {
        // Teacher history query for HOD's department
        $teacher_history_query = "SELECT tr.*, t.name as teacher_name, t.employee_id,
                                         tr.submitted_at, tr.hod_updated_at, tr.hod_remarks
                                  FROM teacher_requests tr 
                                  JOIN teachers t ON tr.teacher_id = t.id 
                                  WHERE t.department = ? AND tr.hod_status != 'pending'
                                  ORDER BY tr.hod_updated_at DESC";
        $teacher_history_stmt = $conn->prepare($teacher_history_query);
        $teacher_history_stmt->bind_param("s", $branch);
        $teacher_history_stmt->execute();
        $teacher_history_result = $teacher_history_stmt->get_result();
    }
}

$student_stmt->close();
$teacher_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .college-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 4px solid #f59e0b;
        }
        .college-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .college-subtitle {
            font-size: 16px;
            opacity: 0.9;
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
        
        /* Tab Styles */
        .tab-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .tab-header {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .tab-button {
            padding: 16px 24px;
            border: none;
            background: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-button:hover {
            background: #edf2f7;
        }
        .tab-button.active {
            background: white;
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
        .tab-content {
            display: none;
            padding: 0;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Application Cards */
        .application-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 16px;
            background: white;
            transition: box-shadow 0.3s ease;
        }
        .application-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .application-header {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .application-body {
            padding: 16px;
        }
        .application-footer {
            padding: 16px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .student-stat { border-left: 4px solid #3b82f6; }
        .teacher-stat { border-left: 4px solid #10b981; }
        .pending-stat { border-left: 4px solid #f59e0b; }
        .history-filters {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- College Header -->
    <div class="college-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="college-name">YASHWANTRAO CHAVAN INSTITUTE OF POLYTECHNIC, BEED</div>
            <div class="college-subtitle">Approved by AICTE, New Delhi & DTE, Mumbai | Affiliated to MSBTE of Mumbai</div>
            <div class="college-subtitle">Barshi Road, Beed-431122 (Maharashtra)</div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Dashboard Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">HOD Dashboard</h1>
                    <p class="text-gray-600 mt-2">
                        <i class="fas fa-user-tie mr-2"></i>
                        <?php echo $_SESSION["hod_name"]; ?> | 
                        Head of Department - <?php echo $_SESSION["hod_branch"]; ?>
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="?tab=history&history_type=student" 
                       class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-medium flex items-center gap-2">
                        <i class="fas fa-history mr-2"></i>
                        History
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition duration-200 font-medium flex items-center gap-2">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card student-stat">
                <div class="stat-number text-blue-600">
                    <?php echo $student_applications->num_rows; ?>
                </div>
                <div class="text-gray-600 font-medium">
                    <i class="fas fa-user-graduate mr-2"></i>
                    Pending Student Applications
                </div>
            </div>
            <div class="stat-card teacher-stat">
                <div class="stat-number text-green-600">
                    <?php echo $teacher_applications->num_rows; ?>
                </div>
                <div class="text-gray-600 font-medium">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                    Pending Teacher Applications
                </div>
            </div>
            <div class="stat-card pending-stat">
                <div class="stat-number text-orange-600">
                    <?php echo $student_applications->num_rows + $teacher_applications->num_rows; ?>
                </div>
                <div class="text-gray-600 font-medium">
                    <i class="fas fa-clock mr-2"></i>
                    Total Pending Applications
                </div>
            </div>
        </div>

        <!-- Tab Container -->
        <div class="tab-container">
            <div class="tab-header">
                <button class="tab-button <?php echo $active_tab == 'students' ? 'active' : ''; ?>" 
                        onclick="switchTab('students')">
                    <i class="fas fa-user-graduate"></i>
                    Student Applications
                    <?php if ($student_applications->num_rows > 0 && $active_tab != 'history'): ?>
                        <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                            <?php echo $student_applications->num_rows; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <button class="tab-button <?php echo $active_tab == 'teachers' ? 'active' : ''; ?>" 
                        onclick="switchTab('teachers')">
                    <i class="fas fa-chalkboard-teacher"></i>
                    Teacher Applications
                    <?php if ($teacher_applications->num_rows > 0 && $active_tab != 'history'): ?>
                        <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full">
                            <?php echo $teacher_applications->num_rows; ?>
                        </span>
                    <?php endif; ?>
                </button>
                
            </div>

            <!-- Student Applications Tab -->
            <div id="students-tab" class="tab-content <?php echo $active_tab == 'students' ? 'active' : ''; ?>">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-user-graduate mr-2"></i>
                        Student Applications - <?php echo $branch; ?> Department
                    </h2>

                    <?php if ($student_applications->num_rows > 0): ?>
                        <div class="space-y-6">
                            <?php while($app = $student_applications->fetch_assoc()): 
                                $request_data = json_decode($app['request_data'], true);
                            ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo $app['student_name']; ?> 
                                                <span class="text-gray-600">(<?php echo $app['roll_no']; ?>)</span>
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                Year: <?php echo $app['year']; ?> • 
                                                <?php echo strtoupper($app['request_type']); ?> • 
                                                Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                            </p>
                                            <?php if ($app['class_teacher_status'] == 'approved' && !empty($app['class_teacher_remarks'])): ?>
                                                <p class="text-sm text-blue-600 mt-1">
                                                    <strong>Class Teacher Remarks:</strong> <?php echo $app['class_teacher_remarks']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                            <?php echo $app['request_type'] == 'bonafide' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <i class="fas <?php echo $app['request_type'] == 'bonafide' ? 'fa-file-certificate' : 'fa-paper-plane'; ?> mr-1"></i>
                                            <?php echo strtoupper($app['request_type']); ?>
                                            <?php if ($app['request_type'] == 'bonafide'): ?>
                                                <span class="ml-1 text-xs">(→ Principal)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="application-body">
                                    <?php if ($app['request_type'] == 'leave'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>From:</strong> <?php echo $request_data['from_date']; ?></div>
                                            <div><strong>To:</strong> <?php echo $request_data['to_date']; ?></div>
                                            <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                            <div><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                            <div class="md:col-span-2"><strong>Description:</strong> <?php echo $request_data['description']; ?></div>
                                        </div>
                                    <?php elseif ($app['request_type'] == 'getpass'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>Date:</strong> <?php echo $request_data['pass_date']; ?></div>
                                            <div><strong>Out Time:</strong> <?php echo $request_data['out_time']; ?></div>
                                            <div><strong>Return Time:</strong> <?php echo $request_data['return_time']; ?></div>
                                            <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                            <div class="md:col-span-2"><strong>Destination:</strong> <?php echo $request_data['destination']; ?></div>
                                        </div>
                                    <?php elseif ($app['request_type'] == 'bonafide'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                            <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                            <div><strong>Copies:</strong> <?php echo $request_data['copies']; ?></div>
                                            <div><strong>Urgency:</strong> <?php echo $request_data['urgency']; ?></div>
                                            <div class="md:col-span-2"><strong>Additional Info:</strong> <?php echo $request_data['additional_info']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="application-footer">
                                    <form method="POST" action="">
                                        <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="request_type" value="<?php echo $app['request_type']; ?>">
                                        <input type="hidden" name="application_type" value="student">
                                        <div class="mb-4">
                                            <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-comment mr-1"></i>Your Remarks
                                            </label>
                                            <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                                      rows="3"
                                                      class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                      placeholder="Add your remarks..."></textarea>
                                        </div>
                                        <div class="flex space-x-3">
                                            <button type="submit" name="action" value="approve" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <i class="fas fa-check mr-2"></i>
                                                <?php echo $app['request_type'] == 'bonafide' ? 'Approve (Send to Principal)' : 'Approve'; ?>
                                            </button>
                                            <button type="submit" name="action" value="reject" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-user-graduate text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No pending student applications</h3>
                            <p class="text-gray-500 mt-2">All student applications have been reviewed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Teacher Applications Tab -->
            <div id="teachers-tab" class="tab-content <?php echo $active_tab == 'teachers' ? 'active' : ''; ?>">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                        Teacher Applications - <?php echo $branch; ?> Department
                    </h2>

                    <?php if ($teacher_applications->num_rows > 0): ?>
                        <div class="space-y-6">
                            <?php while($app = $teacher_applications->fetch_assoc()): 
                                $request_data = json_decode($app['request_data'], true);
                            ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo $app['teacher_name']; ?>
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                <?php echo strtoupper($app['request_type']); ?> • 
                                                Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                <?php echo $app['request_type'] == 'leave' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <i class="fas <?php echo $app['request_type'] == 'leave' ? 'fa-calendar-alt' : 'fa-paper-plane'; ?> mr-1"></i>
                                                <?php echo strtoupper($app['request_type']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                                <i class="fas fa-chalkboard-teacher mr-1"></i>
                                                TEACHER
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="application-body">
                                    <?php if ($app['request_type'] == 'cl'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>From Date:</strong> <?php echo $request_data['from_date']; ?></div>
                                            <div><strong>To Date:</strong> <?php echo $request_data['to_date']; ?></div>
                                            <div><strong>Total Days:</strong> <?php echo $request_data['days']; ?></div>
                                            <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                            <?php if (!empty($request_data['description'])): ?>
                                                <div class="md:col-span-2"><strong>Description:</strong> <?php echo $request_data['description']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($app['request_type'] == 'movement'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>Date:</strong> <?php echo $request_data['movement_date'] ?? 'N/A'; ?></div>
                                            <div><strong>From Time:</strong> <?php echo $request_data['from_time'] ?? 'N/A'; ?></div>
                                            <div class="md:col-span-2"><strong>To Time:</strong> <?php echo $request_data['to_time'] ?? 'N/A'; ?></div>
                                            <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['purpose'] ?? 'No reason provided'; ?></div>
                                            <?php if (!empty($request_data['details'])): ?>
                                                <div class="md:col-span-2"><strong>Details:</strong> <?php echo $request_data['destination']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($app['request_type'] == 'on_duty'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>From Date:</strong> <?php echo $request_data['from_date'] ?? 'N/A'; ?></div>
                                            <div><strong>TO Date:</strong> <?php echo $request_data['to_date'] ?? 'N/A'; ?></div>
                                            <div><strong>Time:</strong> <?php echo $request_data['time'] ?? 'N/A'; ?></div>
                                            <div><strong>Total Days:</strong> <?php echo $request_data['daysod']; ?></div>
                                            <div><strong>Duration:</strong> <?php echo $request_data['duration'] ?? 'N/A'; ?></div>
                                            <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['purpose'] ?? 'No reason provided'; ?></div>
                                            <?php if (!empty($request_data['details'])): ?>
                                                <div class="md:col-span-2"><strong>Details:</strong> <?php echo $request_data['details']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($request_data['event_name'])): ?>
                                                <div class="md:col-span-2"><strong>Event Name:</strong> <?php echo $request_data['event_name']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($request_data['venue'])): ?>
                                                <div class="md:col-span-2"><strong>Venue:</strong> <?php echo $request_data['venue']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm">
                                            <strong>Request Details:</strong> 
                                            <?php 
                                            if (isset($request_data['details']) && !empty($request_data['details'])) {
                                                echo $request_data['details'];
                                            } elseif (isset($request_data['reason']) && !empty($request_data['reason'])) {
                                                echo $request_data['reason'];
                                            } else {
                                                echo 'No additional details provided.';
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="application-footer">
                                    <form method="POST" action="">
                                        <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="request_type" value="<?php echo $app['request_type']; ?>">
                                        <input type="hidden" name="application_type" value="teacher">
                                        <div class="mb-4">
                                            <label for="teacher_remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-comment mr-1"></i>Your Remarks
                                            </label>
                                            <textarea name="remarks" id="teacher_remarks_<?php echo $app['id']; ?>" 
                                                      rows="3"
                                                      class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                      placeholder="Add your remarks..."></textarea>
                                        </div>
                                        <div class="flex space-x-3">
                                            <button type="submit" name="action" value="approve" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <i class="fas fa-check mr-2"></i>
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" 
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-chalkboard-teacher text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No pending teacher applications</h3>
                            <p class="text-gray-500 mt-2">All teacher applications have been reviewed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- History Tab -->
            <div id="history-tab" class="tab-content <?php echo $active_tab == 'history' ? 'active' : ''; ?>">
                <div class="p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-history mr-2"></i>
                        Application History - <?php echo $branch; ?> Department
                    </h2>

                    <!-- History Type Selection -->
                    <div class="history-filters">
                        <div class="flex space-x-4 mb-4">
                            <a href="?tab=history&history_type=student" 
                               class="<?php echo $history_type == 'student' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> 
                                      px-4 py-2 rounded-md font-medium">
                                <i class="fas fa-user-graduate mr-2"></i>
                                Student History
                            </a>
                            <a href="?tab=history&history_type=teacher" 
                               class="<?php echo $history_type == 'teacher' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> 
                                      px-4 py-2 rounded-md font-medium">
                                <i class="fas fa-chalkboard-teacher mr-2"></i>
                                Teacher History
                            </a>
                        </div>
                    </div>

                    <?php if ($history_type == 'student'): ?>
                        <!-- Student History Content -->
                        <?php if (isset($student_history_result) && $student_history_result->num_rows > 0): ?>
                            <div class="space-y-6">
                                <?php while($app = $student_history_result->fetch_assoc()): 
                                    $request_data = json_decode($app['request_data'], true);
                                ?>
                                <div class="application-card">
                                    <div class="application-header">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-lg font-medium text-gray-900">
                                                    <?php echo $app['student_name']; ?> 
                                                    <span class="text-gray-600">(<?php echo $app['roll_no']; ?>)</span>
                                                </h4>
                                                <p class="text-sm text-gray-500">
                                                    Year: <?php echo $app['year']; ?> • 
                                                    <?php echo strtoupper($app['request_type']); ?> • 
                                                    Processed: <?php echo date('M j, Y g:i A', strtotime($app['hod_updated_at'])); ?>
                                                </p>
                                                <div class="flex items-center space-x-4 mt-2">
                                                    <span class="status-badge <?php echo $app['hod_status'] == 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                                        <?php echo $app['hod_status'] == 'approved' ? '✓ Approved' : '✗ Rejected'; ?> by HOD
                                                    </span>
                                                </div>
                                                <?php if (!empty($app['hod_remarks'])): ?>
                                                    <p class="text-sm text-gray-600 mt-2">
                                                        <strong>Your Remarks:</strong> <?php echo $app['hod_remarks']; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                <?php echo $app['request_type'] == 'bonafide' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <i class="fas <?php echo $app['request_type'] == 'bonafide' ? 'fa-file-certificate' : 'fa-paper-plane'; ?> mr-1"></i>
                                                <?php echo strtoupper($app['request_type']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="application-body">
                                        <?php if ($app['request_type'] == 'leave'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>From:</strong> <?php echo $request_data['from_date']; ?></div>
                                                <div><strong>To:</strong> <?php echo $request_data['to_date']; ?></div>
                                                <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                                <div><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                            </div>
                                        <?php elseif ($app['request_type'] == 'getpass'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>Date:</strong> <?php echo $request_data['pass_date']; ?></div>
                                                <div><strong>Out Time:</strong> <?php echo $request_data['out_time']; ?></div>
                                                <div><strong>Return Time:</strong> <?php echo $request_data['return_time']; ?></div>
                                                <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                            </div>
                                        <?php elseif ($app['request_type'] == 'bonafide'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                                <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                                <div><strong>Copies:</strong> <?php echo $request_data['copies']; ?></div>
                                                <div><strong>Urgency:</strong> <?php echo $request_data['urgency']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-user-graduate text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900">No student history found</h3>
                                <p class="text-gray-500 mt-2">No processed student applications in your department.</p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Teacher History Content -->
                        <?php if (isset($teacher_history_result) && $teacher_history_result->num_rows > 0): ?>
                            <div class="space-y-6">
                                <?php while($app = $teacher_history_result->fetch_assoc()): 
                                    $request_data = json_decode($app['request_data'], true);
                                ?>
                                <div class="application-card">
                                    <div class="application-header">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="text-lg font-medium text-gray-900">
                                                    <?php echo $app['teacher_name']; ?>
                                                </h4>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo strtoupper($app['request_type']); ?> • 
                                                    Processed: <?php echo date('M j, Y g:i A', strtotime($app['hod_updated_at'])); ?>
                                                </p>
                                                <div class="flex items-center space-x-4 mt-2">
                                                    <span class="status-badge <?php echo $app['hod_status'] == 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                                        <?php echo $app['hod_status'] == 'approved' ? '✓ Approved' : '✗ Rejected'; ?> by HOD
                                                    </span>
                                                </div>
                                                <?php if (!empty($app['hod_remarks'])): ?>
                                                    <p class="text-sm text-gray-600 mt-2">
                                                        <strong>Your Remarks:</strong> <?php echo $app['hod_remarks']; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                                    <?php echo $app['request_type'] == 'leave' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <i class="fas <?php echo $app['request_type'] == 'leave' ? 'fa-calendar-alt' : 'fa-paper-plane'; ?> mr-1"></i>
                                                    <?php echo strtoupper($app['request_type']); ?>
                                                </span>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-chalkboard-teacher mr-1"></i>
                                                    TEACHER
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="application-body">
                                        <?php if ($app['request_type'] == 'cl'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>From Date:</strong> <?php echo $request_data['from_date']; ?></div>
                                                <div><strong>To Date:</strong> <?php echo $request_data['to_date']; ?></div>
                                                <div><strong>Total Days:</strong> <?php echo $request_data['days']; ?></div>
                                                <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                            </div>
                                        <?php elseif ($app['request_type'] == 'movement'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>Date:</strong> <?php echo $request_data['movement_date'] ?? 'N/A'; ?></div>
                                                <div><strong>From Time:</strong> <?php echo $request_data['from_time'] ?? 'N/A'; ?></div>
                                                <div><strong>To Time:</strong> <?php echo $request_data['to_time'] ?? 'N/A'; ?></div>
                                                <div class="md:col-span-2"><strong>Purpose:</strong> <?php echo $request_data['purpose'] ?? 'No reason provided'; ?></div>
                                            </div>
                                        <?php elseif ($app['request_type'] == 'on_duty'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>From Date:</strong> <?php echo $request_data['from_date'] ?? 'N/A'; ?></div>
                                                <div><strong>To Date:</strong> <?php echo $request_data['to_date'] ?? 'N/A'; ?></div>
                                                <div><strong>Total Days:</strong> <?php echo $request_data['daysod']; ?></div>
                                                <div class="md:col-span-2"><strong>Purpose:</strong> <?php echo $request_data['purpose'] ?? 'No reason provided'; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <i class="fas fa-chalkboard-teacher text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900">No teacher history found</h3>
                                <p class="text-gray-500 mt-2">No processed teacher applications in your department.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url);
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
        
        // Initialize tab based on URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'students';
            switchTab(tab);
        });
    </script>
</body>
</html>