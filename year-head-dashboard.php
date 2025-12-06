
<?php
session_start();

// Check if year head is logged in
if (!isset($_SESSION["year_head_id"])) {
    header("Location: year-head-login.php");
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

// Get filter parameters
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';

// Current year head's year
$current_year = $_SESSION["year_head_year"];

// Build query for applications in year head's year (only leave and getpass)
$query = "SELECT sr.*, s.name as student_name, s.roll_no, s.enrollment_no, s.branch
          FROM student_requests sr 
          JOIN students s ON sr.student_id = s.id 
          WHERE sr.year = ? AND sr.request_type IN ('leave', 'getpass')";

// Add filters
if (!empty($date_filter)) {
    $query .= " AND DATE(sr.submitted_at) = ?";
}
if (!empty($type_filter)) {
    $query .= " AND sr.request_type = ?";
}
if (!empty($branch_filter)) {
    $query .= " AND sr.branch = ?";
}

$query .= " ORDER BY sr.submitted_at DESC";

$stmt = $conn->prepare($query);

// Bind parameters based on filters
if (!empty($date_filter) && !empty($type_filter) && !empty($branch_filter)) {
    $stmt->bind_param("isss", $current_year, $date_filter, $type_filter, $branch_filter);
} elseif (!empty($date_filter) && !empty($type_filter)) {
    $stmt->bind_param("iss", $current_year, $date_filter, $type_filter);
} elseif (!empty($date_filter) && !empty($branch_filter)) {
    $stmt->bind_param("iss", $current_year, $date_filter, $branch_filter);
} elseif (!empty($type_filter) && !empty($branch_filter)) {
    $stmt->bind_param("iss", $current_year, $type_filter, $branch_filter);
} elseif (!empty($date_filter)) {
    $stmt->bind_param("is", $current_year, $date_filter);
} elseif (!empty($type_filter)) {
    $stmt->bind_param("is", $current_year, $type_filter);
} elseif (!empty($branch_filter)) {
    $stmt->bind_param("is", $current_year, $branch_filter);
} else {
    $stmt->bind_param("i", $current_year);
}

$stmt->execute();
$applications = $stmt->get_result();

// Get statistics for the year
$stats_query = "SELECT 
    COUNT(*) as total_applications,
    SUM(request_type = 'leave') as total_leaves,
    SUM(request_type = 'getpass') as total_getpass,
    SUM(hod_status = 'pending') as pending,
    SUM(hod_status = 'approved') as approved,
    SUM(hod_status = 'rejected') as rejected
    FROM student_requests 
    WHERE year = ? AND request_type IN ('leave', 'getpass')";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $current_year);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get branch-wise statistics
$branch_stats_query = "SELECT 
    branch,
    COUNT(*) as count,
    SUM(request_type = 'leave') as leaves,
    SUM(request_type = 'getpass') as getpass
    FROM student_requests 
    WHERE year = ? AND request_type IN ('leave', 'getpass')
    GROUP BY branch";
$branch_stats_stmt = $conn->prepare($branch_stats_query);
$branch_stats_stmt->bind_param("i", $current_year);
$branch_stats_stmt->execute();
$branch_stats = $branch_stats_stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Dashboard - ESMS</title>
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Year Head Dashboard - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="bg-pink-600 text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold">Year Head Dashboard</h1>
                    <p class="text-pink-100">
                        <?php echo $_SESSION["year_head_name"]; ?> | Year <?php echo $current_year; ?> Students
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="year-head-dashboard.php" class="bg-pink-500 text-white px-4 py-2 rounded hover:bg-pink-600">
                        Refresh
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Filter Applications</h3>
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="date" value="<?php echo $date_filter; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">Both Types</option>
                            <option value="leave" <?php echo $type_filter == 'leave' ? 'selected' : ''; ?>>Leave</option>
                            <option value="getpass" <?php echo $type_filter == 'getpass' ? 'selected' : ''; ?>>Get Pass</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="branch" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">All Departments</option>
                            <option value="CM" <?php echo $branch_filter == 'CM' ? 'selected' : ''; ?>>Computer</option>
                            <option value="EJ" <?php echo $branch_filter == 'EJ' ? 'selected' : ''; ?>>Electronics</option>
                            <option value="ME" <?php echo $branch_filter == 'ME' ? 'selected' : ''; ?>>Mechanical</option>
                            <option value="CE" <?php echo $branch_filter == 'CE' ? 'selected' : ''; ?>>Civil</option>
                            <option value="AI" <?php echo $branch_filter == 'AI' ? 'selected' : ''; ?>>AI</option>
                            <option value="EE" <?php echo $branch_filter == 'EE' ? 'selected' : ''; ?>>Electrical</option>
                            <option value="IT" <?php echo $branch_filter == 'IT' ? 'selected' : ''; ?>>IT</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-pink-500 text-white px-4 py-2 rounded hover:bg-pink-600">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Applications</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $stats['total_applications']; ?></dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Leave Applications</dt>
                    <dd class="mt-1 text-3xl font-semibold text-blue-600"><?php echo $stats['total_leaves']; ?></dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Gate Passes</dt>
                    <dd class="mt-1 text-3xl font-semibold text-purple-600"><?php echo $stats['total_getpass']; ?></dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600"><?php echo $stats['approved']; ?></dd>
                </div>
            </div>
        </div>

        <!-- Branch-wise Statistics -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Department-wise Statistics - Year <?php echo $current_year; ?></h3>
            </div>
            <div class="border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-6">
                    <?php while($branch_stat = $branch_stats->fetch_assoc()): ?>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-lg font-semibold text-gray-900"><?php echo $branch_stat['branch']; ?></div>
                        <div class="text-sm text-gray-500 mt-1">Total: <?php echo $branch_stat['count']; ?></div>
                        <div class="text-xs text-blue-600">Leaves: <?php echo $branch_stat['leaves']; ?></div>
                        <div class="text-xs text-purple-600">Passes: <?php echo $branch_stat['getpass']; ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Applications -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Year <?php echo $current_year; ?> Student Applications
                    <?php if ($date_filter): ?> - <?php echo $date_filter; ?><?php endif; ?>
                    <?php if ($type_filter): ?> (<?php echo ucfirst($type_filter); ?>)<?php endif; ?>
                    <?php if ($branch_filter): ?> - <?php echo $branch_filter; ?> Department<?php endif; ?>
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Leave and Gate Pass applications from Year <?php echo $current_year; ?> students.
                </p>
            </div>

            <div class="border-t border-gray-200">
                <?php if ($applications->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while($app = $applications->fetch_assoc()): 
                            $request_data = json_decode($app['request_data'], true);
                            
                            // Status badge color
                            $status_color = 'bg-gray-100 text-gray-800';
                            if ($app['hod_status'] == 'approved') {
                                $status_color = 'bg-green-100 text-green-800';
                            } elseif ($app['hod_status'] == 'rejected') {
                                $status_color = 'bg-red-100 text-red-800';
                            }

                            // Type badge color
                            $type_color = $app['request_type'] == 'leave' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800';
                        ?>
                        <div class="px-4 py-5 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-medium text-gray-900">
                                        <?php echo $app['student_name']; ?> (<?php echo $app['roll_no']; ?>)
                                    </h4>
                                    <p class="text-sm text-gray-500">
                                        <?php echo $app['branch']; ?> Department • 
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_color; ?>">
                                        <?php echo ucfirst($app['hod_status']); ?>
                                    </span>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $type_color; ?>">
                                            <?php echo strtoupper($app['request_type']); ?>
                                        </span>
                                    </div>
                                </div>
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
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Date:</strong> <?php echo $request_data['pass_date']; ?></div>
                                        <div><strong>Out Time:</strong> <?php echo $request_data['out_time']; ?></div>
                                        <div><strong>Return Time:</strong> <?php echo $request_data['return_time']; ?></div>
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div class="col-span-2"><strong>Destination:</strong> <?php echo $request_data['destination']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <div>
                                    <strong>Class Teacher:</strong> 
                                    <span class="<?php echo $app['class_teacher_status'] == 'approved' ? 'text-green-600' : ($app['class_teacher_status'] == 'rejected' ? 'text-red-600' : 'text-yellow-600'); ?>">
                                        <?php echo ucfirst($app['class_teacher_status']); ?>
                                    </span>
                                    <?php if ($app['class_teacher_remarks']): ?>
                                        - <?php echo $app['class_teacher_remarks']; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong>HOD:</strong> 
                                    <span class="<?php echo $app['hod_status'] == 'approved' ? 'text-green-600' : ($app['hod_status'] == 'rejected' ? 'text-red-600' : 'text-yellow-600'); ?>">
                                        <?php echo ucfirst($app['hod_status']); ?>
                                    </span>
                                    <?php if ($app['hod_remarks']): ?>
                                        - <?php echo $app['hod_remarks']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No applications found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php if ($date_filter || $type_filter || $branch_filter): ?>
                                Try changing your filters or 
                            <?php endif; ?>
                            No leave or gate pass applications from Year <?php echo $current_year; ?> students yet.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
