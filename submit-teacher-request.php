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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $teacher_id = $_POST['teacher_id'];
    $teacher_name = $_POST['teacher_name'];
    $employee_id = $_POST['employee_id'];
    $department = $_POST['department'];
    $request_type = $_POST['request_type'];
    
    // Prepare request data based on type
    $request_data = [];
    
    if ($request_type == 'cl') {
        $request_data = [
            'from_date' => $_POST['from_date'],
            'to_date' => $_POST['to_date'],
            'days' => $_POST['days'],
            'reason' => $_POST['reason'],
            'description' => $_POST['description'] ?? ''
        ];
        $cl_taken = floatval($_POST['days']);
    } elseif ($request_type == 'movement') {
        $request_data = [
            'movement_date' => $_POST['movement_date'],
            'from_time' => $_POST['from_time'],
            'to_time' => $_POST['to_time'],
            'purpose' => $_POST['purpose'],
            'destination' => $_POST['destination'],
            'remarks' => $_POST['remarks'] ?? ''
        ];
        $cl_taken = 0;
    } elseif ($request_type == 'on_duty') {
        $request_data = [
            'from_date' => $_POST['from_date'],
            'to_date' => $_POST['to_date'],
            'daysod' => $_POST['daysod'],
            'purpose' => $_POST['purpose'],
            'event_name' => $_POST['event_name'],
            'venue' => $_POST['venue'],
            'additional_info' => $_POST['additional_info'] ?? '',
            'lecture_required' => isset($_POST['lecture_required']) ? 'yes' : 'no',
            'practical_required' => isset($_POST['practical_required']) ? 'yes' : 'no',
            'lecture_substitute' => $_POST['lecture_substitute'] ?? '',
            'practical_substitute' => $_POST['practical_substitute'] ?? '',
            'subjects_covered' => $_POST['subjects_covered'] ?? '',
            'practicals_covered' => $_POST['practicals_covered'] ?? ''
        ];
        $cl_taken = 0;
    }
    
    $json_data = json_encode($request_data);
    
    // Insert into database
    $sql = "INSERT INTO teacher_requests (teacher_id, teacher_name, employee_id, department, request_type, request_data, cl_taken, submitted_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssd", $teacher_id, $teacher_name, $employee_id, $department, $request_type, $json_data, $cl_taken);
    
    if ($stmt->execute()) {
        header("Location: teacher-dashboard.php?message=" . urlencode("✅ Application submitted successfully!") . "&type=success");
    } else {
        header("Location: teacher-dashboard.php?message=" . urlencode("❌ Error submitting application: " . $stmt->error) . "&type=error");
    }
    
    $stmt->close();
}

$conn->close();
?>