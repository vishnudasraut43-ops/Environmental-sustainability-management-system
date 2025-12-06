<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION["student_id"])) {
    header("Location: student-login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: student-dashboard.php");
    exit();
}

$certificate_id = $_GET['id'];

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "esms_portal";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT * FROM bonafide_certificates WHERE id = ? AND student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $certificate_id, $_SESSION["student_id"]);
$stmt->execute();
$result = $stmt->get_result();
$certificate = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$certificate || !file_exists($certificate['certificate_file_path'])) {
    die("Certificate not found!");
}

// Display PDF in browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="bonafide_certificate_' . $certificate['enrollment_no'] . '.pdf"');
readfile($certificate['certificate_file_path']);
exit();