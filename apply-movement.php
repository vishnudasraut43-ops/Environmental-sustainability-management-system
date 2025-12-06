<?php
session_start();
if (!isset($_SESSION["teacher_id"])) {
    header("Location: teacher-login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "esms_portal";

$conn = new mysqli($servername, $username, $password, $dbname);
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
$teacher = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Movement Request - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="bg-red-600 text-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold">Apply Movement Request</h1>
                    <p class="text-red-100">Request for movement during working hours</p>
                </div>
                <div class="flex space-x-4">
                    <a href="teacher-dashboard.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" action="submit-teacher-request.php">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($teacher['name']); ?>">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($teacher['employee_id']); ?>">
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($teacher['department']); ?>">
                    <input type="hidden" name="request_type" value="movement">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Movement Date</label>
                        <input type="date" name="movement_date" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Time</label>
                            <input type="time" name="from_time" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Time</label>
                            <input type="time" name="to_time" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                        <input type="text" name="purpose" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                               placeholder="Brief purpose of movement">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destination</label>
                        <input type="text" name="destination" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                               placeholder="Where are you going?">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Remarks</label>
                        <textarea name="remarks" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                                  placeholder="Any additional remarks..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="teacher-dashboard.php" 
                           class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Submit Movement Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Movement Guidelines -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-800 mb-2">Movement Request Guidelines:</h3>
            <ul class="text-sm text-yellow-700 space-y-1">
                <li>• Maximum 4 movement requests allowed per month</li>
                <li>• Movement should be during working hours only</li>
                <li>• Provide accurate time and destination details</li>
                <li>• Emergency movements will be considered separately</li>
            </ul>
        </div>
    </div>
</body>
</html>