<?php
session_start();

// Check if teacher is logged in
if (!isset($_SESSION["teacher_id"])) {
    header("Location: teacher-login.php");
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

// Mark notification as read
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_read'])) {
    $notification_id = $_POST['notification_id'];
    
    $update_query = "UPDATE teacher_notifications SET is_read = TRUE WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $notification_id, $_SESSION["teacher_id"]);
    $stmt->execute();
    $stmt->close();
    
    header("Location: teacher-notifications.php");
    exit();
}

// Mark all as read
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE teacher_notifications SET is_read = TRUE WHERE teacher_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $_SESSION["teacher_id"]);
    $stmt->execute();
    $stmt->close();
    
    header("Location: teacher-notifications.php");
    exit();
}

// Get teacher's notifications
$teacher_id = $_SESSION["teacher_id"];
$query = "SELECT * FROM teacher_notifications WHERE teacher_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$notifications = $stmt->get_result();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
                    <p class="text-gray-600">
                        <?php echo $_SESSION["teacher_name"]; ?> | 
                        Employee ID: <?php echo $_SESSION["teacher_employee_id"]; ?>
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="teacher-dashboard.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Back to Dashboard
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Mark All as Read -->
        <div class="mb-6 text-right">
            <form method="POST" action="" class="inline-block">
                <button type="submit" name="mark_all_read" 
                        class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Mark All as Read
                </button>
            </form>
        </div>

        <!-- Notifications List -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Your Notifications
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Application status updates and system notifications.
                </p>
            </div>

            <div class="border-t border-gray-200">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while($notification = $notifications->fetch_assoc()): ?>
                        <div class="px-4 py-4 sm:px-6 <?php echo $notification['is_read'] ? 'bg-white' : 'bg-blue-50'; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-lg font-medium text-gray-900">
                                            <?php echo $notification['title']; ?>
                                        </h4>
                                        <span class="text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600">
                                        <?php echo $notification['message']; ?>
                                    </p>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                <div class="ml-4">
                                    <form method="POST" action="">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="mark_read" 
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            Mark Read
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div class="ml-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        Read
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No notifications</h3>
                        <p class="mt-1 text-sm text-gray-500">You're all caught up! No new notifications.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mt-8 bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="teacher-dashboard.php" class="block text-center bg-blue-50 text-blue-700 px-4 py-3 rounded-lg hover:bg-blue-100 font-medium">
                        Back to Dashboard
                    </a>
                    <a href="logout.php" class="block text-center bg-gray-50 text-gray-700 px-4 py-3 rounded-lg hover:bg-gray-100 font-medium">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>