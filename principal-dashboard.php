<?php
session_start();

// Check if Principal is logged in
if (!isset($_SESSION["principal_id"])) {
    header("Location: principal-login.php");
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
    $request_type = $_POST['request_type']; // student or teacher
    $action = $_POST['action'];
    $remarks = trim($_POST['remarks']);
    
    if ($action == 'approve') {
        $status = 'approved';
        
        // If it's a student bonafide request, generate certificate
        if ($request_type == 'student') {
            // Get student and request details
            $student_query = "SELECT sr.*, s.name as student_name, s.roll_no, s.enrollment_no, s.date_of_birth, s.branch, s.year
                            FROM student_requests sr 
                            JOIN students s ON sr.student_id = s.id 
                            WHERE sr.id = ?";
            $stmt = $conn->prepare($student_query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $student_result = $stmt->get_result();
            $student_data = $student_result->fetch_assoc();
            $stmt->close();
            
            if ($student_data) {
                $request_data = json_decode($student_data['request_data'], true);
                
                // Generate certificate
                $certificate_path = generateBonafideCertificate($student_data, $request_data, $conn);
                
                if ($certificate_path) {
                    // Update final status
                    $update_final = "UPDATE student_requests SET final_status = 'approved' WHERE id = ?";
                    $stmt_final = $conn->prepare($update_final);
                    $stmt_final->bind_param("i", $request_id);
                    $stmt_final->execute();
                    $stmt_final->close();
                    
                    $success = "Bonafide certificate approved and generated successfully!";
                } else {
                    $error = "Certificate approved but failed to generate certificate file.";
                }
            }
        }
    } else {
        $status = 'rejected';
    }
    
    // Update the principal status
    if ($request_type == 'student') {
        $update_query = "UPDATE student_requests SET principal_status = ?, principal_remarks = ?, principal_updated_at = NOW() WHERE id = ?";
    } else {
        $update_query = "UPDATE teacher_requests SET principal_status = ?, principal_remarks = ?, principal_updated_at = NOW() WHERE id = ?";
    }
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $status, $remarks, $request_id);
    
    if ($stmt->execute()) {
        if ($action == 'approve' && $request_type != 'student') {
            $success = "Application approved successfully!";
        } elseif ($action == 'reject') {
            $success = "Application rejected successfully!";
        }
    } else {
        $error = "Error updating application: " . $stmt->error;
    }
    $stmt->close();
}

// Function to generate bonafide certificate
function generateBonafideCertificate($student_data, $request_data, $conn) {
    $certificate_number = "BON/" . date('Y') . "/" . str_pad($student_data['id'], 4, '0', STR_PAD_LEFT);
    $issue_date = date('Y-m-d');
    $academic_year = (date('m') > 6) ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');
    
    // Map year to class name
    $year_map = [
        1 => 'First Year',
        2 => 'Second Year', 
        3 => 'Third Year',
        4 => 'Final Year'
    ];
    $class_year = $year_map[$student_data['year']] ?? $student_data['year'] . ' Year';
    
    // Map branch codes to full names
    $branch_map = [
        'CM' => 'Computer Engineering',
        'EJ' => 'Electronics & Telecommunication Engineering',
        'ME' => 'Mechanical Engineering',
        'CE' => 'Civil Engineering',
        'AI' => 'Artificial Intelligence',
        'EE' => 'Electrical Engineering'
    ];
    $branch_full = $branch_map[$student_data['branch']] ?? $student_data['branch'];
    
    // Generate PDF certificate
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('ESMS Portal');
    $pdf->SetAuthor('College Principal');
    $pdf->SetTitle('Bonafide Certificate');
    $pdf->SetSubject('Bonafide Certificate for ' . $student_data['student_name']);
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Certificate border
    $pdf->SetLineWidth(1.5);
    $pdf->Rect(10, 10, 190, 277);
    
    // Add College Logo on top left
    $college_logo = __DIR__ . '/college-logo.jpg'; // Change this to your logo file path
    if (file_exists($college_logo)) {
        // Add logo on top left (adjust coordinates and size as needed)
        $pdf->Image($college_logo, 20, 15, 25, 25, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }
    
    // Add College Header Image if exists
    $header_image = __DIR__ . '/college-header.jpg';
    if (file_exists($header_image)) {
        $pdf->Image($header_image, 50, 15, 140, 0, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $pdf->SetY(40);
    } else {
        // College Logo and Header (text version if image not found)
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(18, 20);
        $pdf->Cell(0, 0, 'YASHWANTRAO CHAVAN INSTITUTE OF POLYTECHNIC, BEED.', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(10, 28);
        $pdf->Cell(0, 0, 'Barshi Road, Beed-431122 (Maharashtra)', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(10, 34);
        $pdf->Cell(0, 0, 'Phone: 02442-223324   Fax:02442-222648    Email: principal.ycip@gmail.com', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(28, 15);
        $pdf->Cell(0, 0, 'MSP MANDAL', 0, 1, 'C');
        $pdf->Cell(-10, 38, '___________________________________________________________________________________________________________', 0, 1, 'L');
        $pdf->SetY(60);
    }
    
    // Certificate Title
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetXY(0, 50);
    $pdf->Cell(0, 0, 'BONAFIDE CERTIFICATE', 0, 1, 'C');
    
    // Original copy text
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(0, 65);
    $pdf->Cell(0, 0, '**ORIGINAL COPY**', 0, 1, 'C');
    
    // Certificate number and date
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->SetXY(20, 80);
    $pdf->Cell(0, 8, 'Certificate No: ' . $certificate_number, 0, 1, 'L');
    $pdf->SetXY(150, 80);
    $pdf->Cell(0, 8, 'Date : ' . date('d-m-Y'), 0, 1, 'L');
    
    // Certificate Content
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetXY(20, 100);
    $pdf->MultiCell(0, 8, 'This is to certify that Mr./Ms. ' . $student_data['student_name'] . ' is a Bonafide Student of this college. He/She is studying in ' . $class_year . ' of ' . $branch_full . ' during the academic year ' . $academic_year . '.', 0, 'L');

    // Enrollment No
    $pdf->SetXY(20, 130);
    $pdf->MultiCell(0, 8, 'Enrollment No: ' . $student_data['enrollment_no'], 0, 'L');
    
    // Date of Birth
    $dob = null;
    if (!empty($student_data['date_of_birth'])) {
        $dob = $student_data['date_of_birth'];
    } elseif (!empty($request_data['date_of_birth'])) {
        $dob = $request_data['date_of_birth'];
    }

    if (!empty($dob)) {
        $pdf->SetXY(20, 140);
        $pdf->MultiCell(0, 8, 'Date of Birth: ' . date('d-m-Y', strtotime($dob)), 0, 'L');
    }

    // Add Principal's Digital Signature
    $signature_image = __DIR__ . '/principal-signature.jpg';
    $signature_y_position = 160;

    if (file_exists($signature_image)) {
        $pdf->Image($signature_image, 120, $signature_y_position, 60, 20, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $pdf->SetXY(150, $signature_y_position + 25);
        $pdf->Cell(0, 8, 'Principal', 0, 1, 'L');
    } else {
        $pdf->SetXY(120, $signature_y_position);
        $pdf->Cell(60, 8, '_________________________', 0, 1, 'L');
        $pdf->SetXY(140, $signature_y_position + 10);
        $pdf->Cell(60, 8, 'Principal', 0, 1, 'L');
    }
    
    // Use absolute path for certificates directory
    $certificates_dir = __DIR__ . '/certificates/';
    
    // Create certificates directory if it doesn't exist
    if (!is_dir($certificates_dir)) {
        mkdir($certificates_dir, 0777, true);
    }
    
    // Generate filename
    $filename = 'bonafide_' . $student_data['enrollment_no'] . '_' . time() . '.pdf';
    $filepath = $certificates_dir . $filename;
    
    // Save PDF file
    $pdf->Output($filepath, 'F');
    
    // Store certificate details in database
    $insert_cert = "INSERT INTO bonafide_certificates 
                   (request_id, student_id, certificate_number, issue_date, academic_year, 
                    class_year, student_name, enrollment_no, date_of_birth, branch, purpose, certificate_file_path) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_cert);
    $stmt->bind_param("iissssssssss", 
        $student_data['id'],
        $student_data['student_id'],
        $certificate_number,
        $issue_date,
        $academic_year,
        $class_year,
        $student_data['student_name'],
        $student_data['enrollment_no'],
        $student_data['date_of_birth'],
        $branch_full,
        $request_data['purpose'],
        $filepath
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        return $filepath;
    } else {
        $stmt->close();
        return false;
    }
}

// Get pending student applications for principal (only bonafide that are approved by HOD)
$student_query = "SELECT sr.*, s.name as student_name, s.roll_no, s.enrollment_no, s.year, s.branch,
                         s.date_of_birth, sr.submitted_at, sr.hod_updated_at, sr.hod_remarks
                  FROM student_requests sr 
                  JOIN students s ON sr.student_id = s.id 
                  WHERE sr.principal_status = 'pending' 
                  AND sr.hod_status = 'approved'
                  AND sr.request_type = 'bonafide'
                  ORDER BY sr.submitted_at DESC";
$student_result = $conn->query($student_query);

// Get pending teacher applications for principal
$teacher_query = "SELECT tr.*, t.name as teacher_name, t.employee_id, t.department
                  FROM teacher_requests tr 
                  JOIN teachers t ON tr.teacher_id = t.id 
                  WHERE tr.principal_status = 'pending' 
                  AND tr.hod_status = 'approved'
                  AND tr.os_status = 'approved'
                  ORDER BY tr.submitted_at DESC";
$teacher_result = $conn->query($teacher_query);

// Get history data if history tab is active
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'students';
$history_type = isset($_GET['history_type']) ? $_GET['history_type'] : 'student';
$history_branch = isset($_GET['branch']) ? $_GET['branch'] : '';
$history_year = isset($_GET['year']) ? $_GET['year'] : '';

if ($active_tab == 'history') {
    if ($history_type == 'student') {
        // Student history query with filters
        $student_history_query = "SELECT sr.*, s.name as student_name, s.roll_no, s.enrollment_no, s.year, s.branch,
                                         s.date_of_birth, sr.submitted_at, sr.principal_updated_at, sr.principal_remarks
                                  FROM student_requests sr 
                                  JOIN students s ON sr.student_id = s.id 
                                  WHERE sr.principal_status != 'pending' 
                                  AND sr.request_type = 'bonafide'";
        
        if (!empty($history_branch)) {
            $student_history_query .= " AND s.branch = '$history_branch'";
        }
        if (!empty($history_year)) {
            $student_history_query .= " AND s.year = '$history_year'";
        }
        
        $student_history_query .= " ORDER BY sr.principal_updated_at DESC";
        $student_history_result = $conn->query($student_history_query);
    } else {
        // Teacher history query
        $teacher_history_query = "SELECT tr.*, t.name as teacher_name, t.employee_id, t.department,
                                         tr.submitted_at, tr.principal_updated_at, tr.principal_remarks
                                  FROM teacher_requests tr 
                                  JOIN teachers t ON tr.teacher_id = t.id 
                                  WHERE tr.principal_status != 'pending'
                                  ORDER BY tr.principal_updated_at DESC";
        $teacher_history_result = $conn->query($teacher_history_query);
    }
}

// Get unique branches and years for filters
$branches_query = "SELECT DISTINCT branch FROM students ORDER BY branch";
$branches_result = $conn->query($branches_query);

$years_query = "SELECT DISTINCT year FROM students ORDER BY year";
$years_result = $conn->query($years_query);

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

    <!-- Principal Dashboard Header -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Principal Dashboard</h1>
                    <p class="text-gray-600">
                        <?php echo $_SESSION["principal_name"]; ?> | Principal
                    </p>
                </div>
                <div class="flex space-x-4">
                    <a href="?tab=history&history_type=student" 
                       class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-medium flex items-center gap-2">
                        <i class="fas fa-history mr-2"></i>
                        📊 History
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition duration-200 font-medium flex items-center gap-2">
                        <i class="fas fa-sign-out-alt"></i>
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

        <!-- Tab Navigation -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex">
                    <a href="?tab=students" 
                       class="<?php echo $active_tab == 'students' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                        📋 Student Applications (<?php echo $student_result->num_rows; ?>)
                    </a>
                    <a href="?tab=teachers" 
                       class="<?php echo $active_tab == 'teachers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> 
                              w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                        👨‍🏫 Teacher Applications (<?php echo $teacher_result->num_rows; ?>)
                    </a>
                </nav>
            </div>
        </div>

        <!-- Student Applications Tab -->
        <div id="students-tab" class="tab-content <?php echo $active_tab == 'students' ? 'active' : ''; ?>">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        📄 Pending Student Bonafide Applications
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Student bonafide applications approved by HOD and waiting for your approval.
                    </p>
                </div>

                <div class="border-t border-gray-200">
                    <?php if ($student_result->num_rows > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php while($app = $student_result->fetch_assoc()): 
                                $request_data = json_decode($app['request_data'], true);
                            ?>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900">
                                            <?php echo $app['student_name']; ?> (<?php echo $app['enrollment_no']; ?>)
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $app['branch']; ?> • Year: <?php echo $app['year']; ?> • 
                                            Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                        </p>
                                        <div class="flex items-center space-x-4 mt-2">
                                            <span class="status-badge status-approved">
                                                ✓ Approved by Class Teacher
                                            </span>
                                            <span class="status-badge status-approved">
                                                ✓ Approved by HOD
                                            </span>
                                            <span class="status-badge status-pending">
                                                ⏳ Waiting for Principal
                                            </span>
                                        </div>
                                        <?php if (!empty($app['hod_remarks'])): ?>
                                            <p class="text-sm text-gray-600 mt-2">
                                                <strong>HOD Remarks:</strong> <?php echo $app['hod_remarks']; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Application Details -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <h5 class="font-medium text-gray-900 mb-3">Application Details:</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                        <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                        <div><strong>Copies Needed:</strong> <?php echo $request_data['copies']; ?></div>
                                        <div><strong>Urgency:</strong> 
                                            <span class="<?php echo $request_data['urgency'] == 'Urgent' ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                                <?php echo $request_data['urgency']; ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($request_data['additional_info'])): ?>
                                            <div class="md:col-span-2">
                                                <strong>Additional Information:</strong> <?php echo $request_data['additional_info']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Student Information -->
                                <div class="bg-blue-50 rounded-lg p-4 mb-4">
                                    <h5 class="font-medium text-gray-900 mb-3">Student Information:</h5>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div><strong>Roll No:</strong> <?php echo $app['roll_no']; ?></div>
                                        <div><strong>Enrollment No:</strong> <?php echo $app['enrollment_no']; ?></div>
                                        <div><strong>Branch:</strong> <?php echo $app['branch']; ?></div>
                                        <div><strong>Year:</strong> <?php echo $app['year']; ?></div>
                                        <?php if (!empty($app['date_of_birth'])): ?>
                                            <div><strong>Date of Birth:</strong> <?php echo date('d-m-Y', strtotime($app['date_of_birth'])); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <form method="POST" action="">
                                    <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="request_type" value="student">
                                    <div class="mb-4">
                                        <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                            Your Remarks (Optional)
                                        </label>
                                        <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                                  rows="3"
                                                  class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                  placeholder="Add any remarks or comments..."></textarea>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button type="submit" name="action" value="approve" 
                                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Approve & Generate Certificate
                                        </button>
                                        <button type="submit" name="action" value="reject" 
                                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Reject Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="px-4 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No pending student applications</h3>
                            <p class="mt-1 text-sm text-gray-500">All student bonafide applications have been reviewed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Teacher Applications Tab -->
        <div id="teachers-tab" class="tab-content <?php echo $active_tab == 'teachers' ? 'active' : ''; ?>">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        👨‍🏫 Pending Teacher Applications
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Teacher applications approved by HOD and Office Staff, waiting for your approval.
                    </p>
                </div>

                <div class="border-t border-gray-200">
                    <?php if ($teacher_result->num_rows > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php while($app = $teacher_result->fetch_assoc()): 
                                $request_data = json_decode($app['request_data'], true);
                            ?>
                            <div class="px-4 py-5 sm:p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900">
                                            <?php echo $app['teacher_name']; ?> (<?php echo $app['employee_id']; ?>)
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            <?php echo $app['department']; ?> • 
                                            Submitted: <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                                        </p>
                                        <div class="flex items-center space-x-4 mt-2">
                                            <span class="status-badge status-approved">
                                                ✓ Approved by HOD
                                            </span>
                                            <span class="status-badge status-approved">
                                                ✓ Approved by Office Staff
                                            </span>
                                            <span class="status-badge status-pending">
                                                ⏳ Waiting for Principal
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Application Details -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <h5 class="font-medium text-gray-900 mb-3">Application Details:</h5>
                                    <?php if ($app['request_type'] == 'leave'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>Leave Type:</strong> <?php echo $request_data['leave_type']; ?></div>
                                            <div><strong>From Date:</strong> <?php echo $request_data['from_date']; ?></div>
                                            <div><strong>To Date:</strong> <?php echo $request_data['to_date']; ?></div>
                                            <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                            <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm">
                                            <strong>Request Type:</strong> <?php echo strtoupper($app['request_type']); ?><br>
                                            <?php if (isset($request_data['details'])): ?>
                                                <strong>Details:</strong> <?php echo $request_data['details']; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="">
                                    <input type="hidden" name="request_id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="request_type" value="teacher">
                                    <div class="mb-4">
                                        <label for="remarks_<?php echo $app['id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                            Your Remarks (Optional)
                                        </label>
                                        <textarea name="remarks" id="remarks_<?php echo $app['id']; ?>" 
                                                rows="3"
                                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 rounded-md"
                                                placeholder="Add any remarks or comments..."></textarea>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button type="submit" name="action" value="approve" 
                                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Approve Application
                                        </button>
                                        <button type="submit" name="action" value="reject" 
                                                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Reject Application
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="px-4 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No pending teacher applications</h3>
                            <p class="mt-1 text-sm text-gray-500">All teacher applications have been reviewed.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div id="history-tab" class="tab-content <?php echo $active_tab == 'history' ? 'active' : ''; ?>">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        📊 Application History
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        View historical data of all processed applications.
                    </p>
                </div>

                <!-- History Type Selection -->
                <div class="history-filters">
                    <div class="flex space-x-4 mb-4">
                        <a href="?tab=history&history_type=student" 
                           class="<?php echo $history_type == 'student' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> 
                                  px-4 py-2 rounded-md font-medium">
                            📝 Student History
                        </a>
                        <a href="?tab=history&history_type=teacher" 
                           class="<?php echo $history_type == 'teacher' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> 
                                  px-4 py-2 rounded-md font-medium">
                            👨‍🏫 Teacher History
                        </a>
                    </div>

                    <?php if ($history_type == 'student'): ?>
                    <!-- Student History Filters -->
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="hidden" name="tab" value="history">
                        <input type="hidden" name="history_type" value="student">
                        
                        <div>
                            <label for="branch" class="block text-sm font-medium text-gray-700">Branch</label>
                            <select name="branch" id="branch" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">All Branches</option>
                                <?php while($branch = $branches_result->fetch_assoc()): ?>
                                    <option value="<?php echo $branch['branch']; ?>" <?php echo $history_branch == $branch['branch'] ? 'selected' : ''; ?>>
                                        <?php echo $branch['branch']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" id="year" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">All Years</option>
                                <?php while($year = $years_result->fetch_assoc()): ?>
                                    <option value="<?php echo $year['year']; ?>" <?php echo $history_year == $year['year'] ? 'selected' : ''; ?>>
                                        Year <?php echo $year['year']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                Apply Filters
                            </button>
                            <a href="?tab=history&history_type=student" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                                Clear
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="border-t border-gray-200">
                    <?php if ($history_type == 'student'): ?>
                        <!-- Student History Content -->
                        <?php if (isset($student_history_result) && $student_history_result->num_rows > 0): ?>
                            <div class="divide-y divide-gray-200">
                                <?php while($app = $student_history_result->fetch_assoc()): 
                                    $request_data = json_decode($app['request_data'], true);
                                ?>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo $app['student_name']; ?> (<?php echo $app['enrollment_no']; ?>)
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                <?php echo $app['branch']; ?> • Year: <?php echo $app['year']; ?> • 
                                                Processed: <?php echo date('M j, Y g:i A', strtotime($app['principal_updated_at'])); ?>
                                            </p>
                                            <div class="flex items-center space-x-4 mt-2">
                                                <span class="status-badge <?php echo $app['principal_status'] == 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                                    <?php echo $app['principal_status'] == 'approved' ? '✓ Approved' : '✗ Rejected'; ?> by Principal
                                                </span>
                                            </div>
                                            <?php if (!empty($app['principal_remarks'])): ?>
                                                <p class="text-sm text-gray-600 mt-2">
                                                    <strong>Principal Remarks:</strong> <?php echo $app['principal_remarks']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <h5 class="font-medium text-gray-900 mb-3">Application Details:</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                            <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                            <div><strong>Copies Needed:</strong> <?php echo $request_data['copies']; ?></div>
                                            <div><strong>Urgency:</strong> <?php echo $request_data['urgency']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="px-4 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No student history found</h3>
                                <p class="mt-1 text-sm text-gray-500">No processed student applications match your criteria.</p>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Teacher History Content -->
                        <?php if (isset($teacher_history_result) && $teacher_history_result->num_rows > 0): ?>
                            <div class="divide-y divide-gray-200">
                                <?php while($app = $teacher_history_result->fetch_assoc()): 
                                    $request_data = json_decode($app['request_data'], true);
                                ?>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo $app['teacher_name']; ?> (<?php echo $app['employee_id']; ?>)
                                            </h4>
                                            <p class="text-sm text-gray-500">
                                                <?php echo $app['department']; ?> • 
                                                Processed: <?php echo date('M j, Y g:i A', strtotime($app['principal_updated_at'])); ?>
                                            </p>
                                            <div class="flex items-center space-x-4 mt-2">
                                                <span class="status-badge <?php echo $app['principal_status'] == 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                                    <?php echo $app['principal_status'] == 'approved' ? '✓ Approved' : '✗ Rejected'; ?> by Principal
                                                </span>
                                            </div>
                                            <?php if (!empty($app['principal_remarks'])): ?>
                                                <p class="text-sm text-gray-600 mt-2">
                                                    <strong>Principal Remarks:</strong> <?php echo $app['principal_remarks']; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <h5 class="font-medium text-gray-900 mb-3">Application Details:</h5>
                                        <?php if ($app['request_type'] == 'leave'): ?>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div><strong>Leave Type:</strong> <?php echo $request_data['leave_type']; ?></div>
                                                <div><strong>From Date:</strong> <?php echo $request_data['from_date']; ?></div>
                                                <div><strong>To Date:</strong> <?php echo $request_data['to_date']; ?></div>
                                                <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                                <div class="md:col-span-2"><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-sm">
                                                <strong>Request Type:</strong> <?php echo strtoupper($app['request_type']); ?><br>
                                                <?php if (isset($request_data['details'])): ?>
                                                    <strong>Details:</strong> <?php echo $request_data['details']; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="px-4 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No teacher history found</h3>
                                <p class="mt-1 text-sm text-gray-500">No processed teacher applications found.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
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