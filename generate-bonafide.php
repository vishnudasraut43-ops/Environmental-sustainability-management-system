<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION["student_id"])) {
    header("Location: student-login.php");
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

$request_id = $_GET['request_id'];
$student_id = $_SESSION["student_id"];

// Verify the student owns this request and it's approved by principal
$query = "SELECT sr.*, s.* 
          FROM student_requests sr 
          JOIN students s ON sr.student_id = s.id 
          WHERE sr.id = ? AND sr.student_id = ? AND sr.principal_status = 'approved' 
          AND sr.request_type = 'bonafide'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $request_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Certificate not found or not approved by Principal.");
}

$data = $result->fetch_assoc();
$request_data = json_decode($data['request_data'], true);
$stmt->close();
$conn->close();

// Determine class year and branch name
$class_years = [1 => 'FY', 2 => 'SY', 3 => 'TY'];
$class_year = $class_years[$data['year']] ?? 'TY';

$branch_names = [
    'CM' => 'COMPUTER', 'EJ' => 'ELECTRONICS', 'ME' => 'MECHANICAL',
    'CE' => 'CIVIL', 'AI' => 'ARTIFICIAL INTELLIGENCE', 
    'EE' => 'ELECTRICAL', 'IT' => 'INFORMATION TECHNOLOGY'
];
$branch_full = $branch_names[$data['branch']] ?? 'COMPUTER';

// Generate certificate content
$certificate_number = date('Y') . '-' . (date('Y') + 1) . '/' . rand(4000, 5000);
$issue_date = date('d/m/Y');
$academic_year = date('Y') . '-' . (date('Y') + 1);

// Create HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .college-name { font-size: 20px; font-weight: bold; }
        .college-address { font-size: 14px; margin: 5px 0; }
        .certificate-title { font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; }
        .certificate-number { text-align: left; margin: 10px 0; }
        .certificate-text { margin: 20px 0; line-height: 1.6; }
        .student-details { margin: 15px 0; }
        .signature { text-align: right; margin-top: 50px; }
        .line { border-top: 1px solid #000; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="college-name">MSP MAND AL\'S</div>
        <div class="college-address">Yashwantrao, Chavan Institute of Polytechnic, Beed, Barshi Road, Beed-431122 (Maharashtra)</div>
        <div class="college-address">Phone: 02442-225324  Fax: 02442-225648  Email: principalycip@gmail.com</div>
    </div>
    
    <div class="line"></div>
    
    <div class="certificate-title">BONAFIDE CERTIFICATE</div>
    <div style="text-align: center; font-weight: bold; margin-bottom: 20px;">ORIGINAL COPY</div>
    
    <div class="certificate-number">
        <strong>Certificate No:</strong> ' . $certificate_number . '<br>
        <strong>Date:</strong> ' . $issue_date . '
    </div>
    
    <div class="certificate-text">
        This is to certify that Mr. <strong>' . strtoupper($data['name']) . '</strong> is/was a bonafide student of this College. 
        He is /was studying in class <strong>' . $class_year . ' ' . $branch_full . '</strong> during the year <strong>' . $academic_year . '</strong>.
    </div>
    
    <div class="student-details">
        <strong>Enrollment No/ PRN:</strong> ' . $data['enrollment_no'] . '<br>
        <strong>Date of Birth:</strong> ' . (!empty($data['date_of_birth']) ? date('d/m/Y', strtotime($data['date_of_birth'])) : 'Not specified') . '
    </div>
    
    <div class="signature">
        <div>Issuing Authority</div>
        <div style="margin-top: 40px;">Principal</div>
        <div>Yashwantrao Chavan Institute of Polytechnic</div>
        <div>Beed</div>
    </div>
</body>
</html>';

// Output as PDF for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="bonafide_certificate_' . $data['enrollment_no'] . '.pdf"');
echo $html;
?>