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

// Process student application approval/rejection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks']);
    
    if ($action == 'approve') {
        $status = 'approved';
        // For bonafide applications, send to principal
        if ($_POST['request_type'] == 'bonafide') {
            $next_status = 'pending'; // Move to Principal
        } else {
            $next_status = 'approved'; // Directly approved for other types
        }
    } else {
        $status = 'rejected';
        $next_status = 'rejected';
    }
    
    $update_query = "UPDATE student_requests SET hod_status = ?, hod_remarks = ?, hod_updated_at = NOW(), principal_status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssi", $status, $remarks, $next_status, $request_id);
    
    if ($stmt->execute()) {
        // Create notification for student
        $request_query = "SELECT student_id, request_type FROM student_requests WHERE id = ?";
        $req_stmt = $conn->prepare($request_query);
        $req_stmt->bind_param("i", $request_id);
        $req_stmt->execute();
        $req_result = $req_stmt->get_result();
        $request_data = $req_result->fetch_assoc();
        $req_stmt->close();
        
        $notification_query = "INSERT INTO student_notifications (student_id, request_id, title, message) 
                              VALUES (?, ?, 'HOD Action', ?)";
        $notification_stmt = $conn->prepare($notification_query);
        $notification_message = "Your " . strtoupper($request_data['request_type']) . " application has been " . $status . " by HOD.";
        if (!empty($remarks)) {
            $notification_message .= " Remarks: " . $remarks;
        }
        $notification_stmt->bind_param("iis", $request_data['student_id'], $request_id, $notification_message);
        $notification_stmt->execute();
        $notification_stmt->close();
        
        $success = "Application " . $status . " successfully!";
    } else {
        $error = "Error updating application: " . $stmt->error;
    }
    $stmt->close();
}

// Get pending student applications for this HOD's department
$branch = $_SESSION["hod_branch"];

$query = "SELECT sr.*, s.name as student_name, s.roll_number, s.year
          FROM student_requests sr 
          JOIN students s ON sr.student_id = s.id 
          WHERE s.department = ? AND sr.hod_status = 'pending'
          ORDER BY sr.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $branch);
$stmt->execute();
$applications = $stmt->get_result();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Student Applications - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">HOD Dashboard - Student Applications</h1>
                    <p class="text-gray-600">
                        <?php echo $_SESSION["hod_name"]; ?> | 
                        Head of Department - <?php echo $_SESSION["hod_branch"]; ?>
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="hod-teacher-dashboard.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Teacher Applications
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

        <!-- Applications -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Pending Student Applications - <?php echo $branch; ?> Department
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Student applications waiting for your approval.
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
                                        <?php echo $app['student_name']; ?> (<?php echo $app['roll_number']; ?>)
                                    </h4>
                                    <p class="text-sm text-gray-500">
                                        Year: <?php echo $app['year']; ?> • 
                                        <?php echo strtoupper($app['request_type']); ?> • 
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                    <?php echo $app['request_type'] == 'bonafide' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo strtoupper($app['request_type']); ?>
                                </span>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <?php if ($app['request_type'] == 'bonafide'): ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div><strong>Language:</strong> <?php echo $request_data['language']; ?></div>
                                        <div class="col-span-2"><strong>Additional Details:</strong> <?php echo $request_data['additional_details'] ?: 'None'; ?></div>
                                    </div>
                                <?php elseif ($app['request_type'] == 'tc'): ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Reason for Leaving:</strong> <?php echo $request_data['reason']; ?></div>
                                        <div><strong>Joining College:</strong> <?php echo $request_data['joining_college']; ?></div>
                                        <div class="col-span-2"><strong>Remarks:</strong> <?php echo $request_data['remarks']; ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div><strong>Certificate Type:</strong> <?php echo $request_data['certificate_type']; ?></div>
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div class="col-span-2"><strong>Details:</strong> <?php echo $request_data['details']; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <form method="POST" action="">
                                <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                <input type="hidden" name="request_type" value="<?php echo $app['request_type']; ?>">
                                <div class="mb-4">
                                    <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                        Your Remarks
                                    </label>
                                    <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                              rows="3"
                                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                              placeholder="Add your remarks..."></textarea>
                                </div>
                                <div class="flex space-x-3">
                                    <button type="submit" name="action" value="approve" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <?php echo $app['request_type'] == 'bonafide' ? '✓ Approve (Send to Principal)' : '✓ Approve'; ?>
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
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No pending student applications</h3>
                        <p class="mt-1 text-sm text-gray-500">All student applications have been reviewed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>