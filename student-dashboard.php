<?php
session_start();

// Check if student is logged in
if (!isset($_SESSION["student_id"])) {
    header("Location: student-login.php");
    exit();
}

// Display success/error messages
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $type = htmlspecialchars($_GET['type']);
    
    echo "<div class='message {$type}' style='margin: 20px auto; max-width: 1000px;'>
            {$message}
            <button onclick='this.parentElement.style.display=\"none\"' style='float: right; background: none; border: none; font-size: 20px; cursor: pointer;'>&times;</button>
          </div>";
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

// Handle certificate download
if (isset($_GET['download_certificate'])) {
    $certificate_id = $_GET['download_certificate'];
    
    $query = "SELECT * FROM bonafide_certificates WHERE id = ? AND student_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $certificate_id, $_SESSION["student_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $certificate = $result->fetch_assoc();
    $stmt->close();
    
    if ($certificate) {
        // Try absolute path first
        if (file_exists($certificate['certificate_file_path'])) {
            // Set headers for download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="bonafide_certificate_' . $certificate['enrollment_no'] . '.pdf"');
            header('Content-Length: ' . filesize($certificate['certificate_file_path']));
            readfile($certificate['certificate_file_path']);
            exit();
        } else {
            // Try with relative path if absolute path doesn't work
            $relative_path = 'certificates/' . basename($certificate['certificate_file_path']);
            if (file_exists($relative_path)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="bonafide_certificate_' . $certificate['enrollment_no'] . '.pdf"');
                header('Content-Length: ' . filesize($relative_path));
                readfile($relative_path);
                exit();
            } else {
                // If file doesn't exist, show error
                $error = "Certificate file not found! Please contact administrator.";
            }
        }
    } else {
        $error = "Certificate not found!";
    }
}

// Get student details
$student_id = $_SESSION["student_id"];
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Student not found, logout
    session_destroy();
    header("Location: student-login.php?message=" . urlencode("❌ Student session expired. Please login again.") . "&type=error");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Get student's approved bonafide certificates
$certificates_query = "SELECT bc.*, sr.submitted_at, sr.principal_updated_at 
                      FROM bonafide_certificates bc
                      JOIN student_requests sr ON bc.request_id = sr.id
                      WHERE bc.student_id = ? 
                      ORDER BY bc.issue_date DESC";
$stmt_cert = $conn->prepare($certificates_query);
$stmt_cert->bind_param("i", $student_id);
$stmt_cert->execute();
$certificates_result = $stmt_cert->get_result();

// Get student's all applications
$applications_query = "SELECT * FROM student_requests 
                      WHERE student_id = ? 
                      ORDER BY submitted_at DESC";
$stmt_apps = $conn->prepare($applications_query);
$stmt_apps->bind_param("i", $student_id);
$stmt_apps->execute();
$applications_result = $stmt_apps->get_result();

$stmt_cert->close();
$stmt_apps->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ESMS</title>
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
    <title>Student Dashboard - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .dashboard { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 15px 30px rgba(0,0,0,0.2); max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #eee; flex-wrap: wrap; }
        h1 { color: #333; margin-bottom: 10px; }
        .student-welcome { color: #666; font-size: 18px; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #764ba2; }
        .options-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .option-card { background: white; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s ease; cursor: pointer; }
        .option-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); border-color: #764ba2; }
        .option-icon { font-size: 48px; margin-bottom: 15px; color: #764ba2; }
        .option-title { font-size: 22px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .option-description { color: #666; font-size: 14px; line-height: 1.5; }
        .student-info { background: #f8f9fa; padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .info-item { margin-bottom: 15px; }
        .info-label { font-weight: 600; color: #555; display: block; margin-bottom: 5px; font-size: 14px; }
        .info-value { color: #333; font-size: 16px; padding: 8px 12px; background: white; border-radius: 6px; border-left: 4px solid #764ba2; }
        .logout { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.3s; text-decoration: none; display: inline-block; }
        .logout:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .actions { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        
        /* Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000; 
            justify-content: center; 
            align-items: center; 
        }
        .modal-content { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            width: 90%; 
            max-width: 500px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .modal-title { font-size: 24px; color: #333; font-weight: 600; }
        .close-modal { 
            background: none; 
            border: none; 
            font-size: 24px; 
            cursor: pointer; 
            color: #666; 
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-modal:hover { color: #333; background: #f1f1f1; border-radius: 50%; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
        .form-input, .form-textarea, .form-select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 16px; 
            transition: border-color 0.3s;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 3px rgba(118, 75, 162, 0.1);
        }
        .form-textarea { height: 100px; resize: vertical; }
        .submit-btn { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 6px; 
            font-size: 16px; 
            cursor: pointer; 
            width: 100%; 
            font-weight: 600;
            transition: transform 0.3s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Applications Section */
        .applications-section { margin-top: 40px; }
        .applications-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 20px; }
        .application-card { background: white; border-radius: 8px; padding: 20px; border-left: 4px solid #764ba2; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .application-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .application-type { font-weight: 600; color: #333; }
        .application-date { color: #666; font-size: 14px; }
        .application-status { display: flex; gap: 20px; margin-top: 10px; flex-wrap: wrap; }
        .status-item { display: flex; align-items: center; gap: 5px; }
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Certificate Download Section */
        .certificate-section { margin-top: 40px; }
        .certificate-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 20px; }
        .certificate-card { background: white; border-radius: 8px; padding: 20px; border-left: 4px solid #10b981; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .certificate-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .certificate-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn-download { background: #3b82f6; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background 0.3s; }
        .btn-download:hover { background: #2563eb; }
        .btn-view { background: #6b7280; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background 0.3s; }
        .btn-view:hover { background: #4b5563; }
        
        @media (max-width: 768px) {
            .dashboard { padding: 20px; }
            .header { flex-direction: column; text-align: center; gap: 15px; }
            .options-grid { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
            .certificate-actions { flex-direction: column; }
            .application-status { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <div>
                <h1>Student Dashboard</h1>
                <div class="student-welcome">
                    Welcome back, <strong><?php echo $student['name']; ?></strong>! 
                    (Roll No: <?php echo $student['roll_no']; ?>)
                </div>
            </div>
            <a href="logout.php" class="logout">Logout</a>
        </div>

        <!-- Three Options Grid -->
        <div class="options-grid">
            <div class="option-card" onclick="showModal('leaveModal')">
                <div class="option-icon">📝</div>
                <div class="option-title">LEAVE APPLICATION</div>
                <div class="option-description">Apply for leave from college. Submit your leave request with reason and duration.</div>
            </div>

            <div class="option-card" onclick="showModal('getpassModal')">
                <div class="option-icon">🎫</div>
                <div class="option-title">GET PASS</div>
                <div class="option-description">Generate gate pass for going outside campus during college hours.</div>
            </div>

            <div class="option-card" onclick="showModal('bonafideModal')">
                <div class="option-icon">📄</div>
                <div class="option-title">BONAFIDE CERTIFICATE</div>
                <div class="option-description">Request bonafide certificate for various purposes like scholarships, bank accounts, etc.</div>
            </div>
        </div>

        <!-- Approved Certificates Section -->
        <?php if ($certificates_result->num_rows > 0): ?>
        <div class="certificate-section">
            <h2 style="margin-bottom: 20px; color: #333;">📄 My Bonafide Certificates</h2>
            <p style="color: #666; margin-bottom: 20px;">Download your approved bonafide certificates. Certificates are available after principal approval.</p>
            
            <div class="certificate-grid">
                <?php while($cert = $certificates_result->fetch_assoc()): ?>
                <div class="certificate-card">
                    <div class="certificate-header">
                        <div>
                            <h3 class="application-type">Bonafide Certificate - <?php echo $cert['certificate_number']; ?></h3>
                            <span class="status-badge status-approved">✓ Approved</span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Student Name</span>
                                <div class="info-value"><?php echo $cert['student_name']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Enrollment No</span>
                                <div class="info-value"><?php echo $cert['enrollment_no']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Issue Date</span>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($cert['issue_date'])); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Academic Year</span>
                                <div class="info-value"><?php echo $cert['academic_year']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Purpose</span>
                                <div class="info-value"><?php echo $cert['purpose']; ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Class</span>
                                <div class="info-value"><?php echo $cert['class_year']; ?> - <?php echo $cert['branch']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="certificate-actions">
                        <a href="?download_certificate=<?php echo $cert['id']; ?>" class="btn-download">
                            📥 Download PDF
                        </a>
                        <a href="view-certificate.php?id=<?php echo $cert['id']; ?>" target="_blank" class="btn-view">
                            👁️ View Certificate
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Applications Status Section -->
        <div class="applications-section">
            <h2 style="margin-bottom: 20px; color: #333;">📋 Your Applications</h2>
            
            <?php if ($applications_result->num_rows > 0): ?>
                <div class="applications-grid">
                    <?php while($app = $applications_result->fetch_assoc()): 
                        $request_data = json_decode($app['request_data'], true);
                        
                        // Determine overall status
                        if ($app['final_status'] == 'approved') {
                            $overall_status = 'approved';
                        } elseif ($app['final_status'] == 'rejected' || $app['class_teacher_status'] == 'rejected' || $app['hod_status'] == 'rejected' || $app['principal_status'] == 'rejected') {
                            $overall_status = 'rejected';
                        } else {
                            $overall_status = 'pending';
                        }
                    ?>
                    <div class="application-card">
                        <div class="application-header">
                            <div class="application-type">
                                <?php echo strtoupper($app['request_type']); ?> - 
                                <?php echo isset($request_data['reason']) ? $request_data['reason'] : 
                                      (isset($request_data['purpose']) ? $request_data['purpose'] : 'Application'); ?>
                            </div>
                            <div class="application-date">
                                <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="application-status">
                            <div class="status-item">
                                <span>Class Teacher:</span>
                                <span class="status-badge status-<?php echo $app['class_teacher_status']; ?>">
                                    <?php echo ucfirst($app['class_teacher_status']); ?>
                                </span>
                            </div>
                            <div class="status-item">
                                <span>HOD:</span>
                                <span class="status-badge status-<?php echo $app['hod_status']; ?>">
                                    <?php echo ucfirst($app['hod_status']); ?>
                                </span>
                            </div>
                            <?php if ($app['request_type'] == 'bonafide'): ?>
                            <div class="status-item">
                                <span>Principal:</span>
                                <span class="status-badge status-<?php echo $app['principal_status']; ?>">
                                    <?php echo ucfirst($app['principal_status']); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Application Details -->
                        <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                            <?php if ($app['request_type'] == 'leave'): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 14px;">
                                    <div><strong>From:</strong> <?php echo $request_data['from_date']; ?></div>
                                    <div><strong>To:</strong> <?php echo $request_data['to_date']; ?></div>
                                    <div><strong>Days:</strong> <?php echo $request_data['days']; ?></div>
                                    <div><strong>Reason:</strong> <?php echo $request_data['reason']; ?></div>
                                    <div style="grid-column: 1 / -1;"><strong>Description:</strong> <?php echo $request_data['description']; ?></div>
                                </div>
                            <?php elseif ($app['request_type'] == 'getpass'): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 14px;">
                                    <div><strong>Date:</strong> <?php echo $request_data['pass_date']; ?></div>
                                    <div><strong>Out Time:</strong> <?php echo $request_data['out_time']; ?></div>
                                    <div><strong>Return Time:</strong> <?php echo $request_data['return_time']; ?></div>
                                    <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                    <div style="grid-column: 1 / -1;"><strong>Destination:</strong> <?php echo $request_data['destination']; ?></div>
                                </div>
                            <?php elseif ($app['request_type'] == 'bonafide'): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 14px;">
                                    <div><strong>Purpose:</strong> <?php echo $request_data['purpose']; ?></div>
                                    <div><strong>Required For:</strong> <?php echo $request_data['required_for']; ?></div>
                                    <div><strong>Copies:</strong> <?php echo $request_data['copies']; ?></div>
                                    <div><strong>Urgency:</strong> <?php echo $request_data['urgency']; ?></div>
                                    <div style="grid-column: 1 / -1;"><strong>Additional Info:</strong> <?php echo $request_data['additional_info']; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Remarks -->
                        <?php if ($app['class_teacher_remarks']): ?>
                        <div style="margin-top: 10px; font-size: 14px; color: #666;">
                            <strong>Class Teacher Remarks:</strong> <?php echo $app['class_teacher_remarks']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($app['hod_remarks']): ?>
                        <div style="margin-top: 5px; font-size: 14px; color: #666;">
                            <strong>HOD Remarks:</strong> <?php echo $app['hod_remarks']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($app['principal_remarks']): ?>
                        <div style="margin-top: 5px; font-size: 14px; color: #666;">
                            <strong>Principal Remarks:</strong> <?php echo $app['principal_remarks']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No applications submitted yet. Click on the options above to create your first application!</p>
            <?php endif; ?>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <h2 style="margin-bottom: 20px; color: #333;">Student Information</h2>
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Full Name</span><div class="info-value"><?php echo $student['name']; ?></div></div>
                <div class="info-item"><span class="info-label">Roll Number</span><div class="info-value"><?php echo $student['roll_no']; ?></div></div>
                <div class="info-item"><span class="info-label">Enrollment Number</span><div class="info-value"><?php echo $student['enrollment_no']; ?></div></div>
                <div class="info-item"><span class="info-label">Branch</span><div class="info-value"><?php echo $student['branch']; ?></div></div>
                <div class="info-item"><span class="info-label">Year</span><div class="info-value"><?php echo $student['year']; ?> Year</div></div>
                <div class="info-item"><span class="info-label">Email</span><div class="info-value"><?php echo $student['email']; ?></div></div>
            </div>
        </div>

        <div class="actions">
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <!-- LEAVE Application Modal -->
    <div id="leaveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Leave Application</h2>
                <button class="close-modal" onclick="hideModal('leaveModal')">×</button>
            </div>
            <form id="leaveForm" method="POST" action="submit-request.php">
                <input type="hidden" name="request_type" value="leave">
                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                <input type="hidden" name="student_name" value="<?php echo $student['name']; ?>">
                <input type="hidden" name="roll_no" value="<?php echo $student['roll_no']; ?>">
                
                <div class="form-group">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-input" name="from_date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-input" name="to_date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Number of Days</label>
                    <input type="number" class="form-input" name="days" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Leave</label>
                    <select class="form-select" name="reason" required>
                        <option value="">Select Reason</option>
                        <option value="Medical">Medical</option>
                        <option value="Personal">Personal</option>
                        <option value="Family Function">Family Function</option>
                        <option value="Emergency">Emergency</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Detailed Description</label>
                    <textarea class="form-textarea" name="description" placeholder="Please provide detailed reason..." required></textarea>
                </div>
                <button type="submit" class="submit-btn">Submit Leave Application</button>
            </form>
        </div>
    </div>

    <!-- GETPASS Modal -->
    <div id="getpassModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Get Gate Pass</h2>
                <button class="close-modal" onclick="hideModal('getpassModal')">×</button>
            </div>
            <form id="getpassForm" method="POST" action="submit-request.php">
                <input type="hidden" name="request_type" value="getpass">
                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                <input type="hidden" name="student_name" value="<?php echo $student['name']; ?>">
                <input type="hidden" name="roll_no" value="<?php echo $student['roll_no']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-input" name="pass_date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Out Time</label>
                    <input type="time" class="form-input" name="out_time" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Expected Return Time</label>
                    <input type="time" class="form-input" name="return_time" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Purpose</label>
                    <select class="form-select" name="purpose" required>
                        <option value="">Select Purpose</option>
                        <option value="Medical">Medical Appointment</option>
                        <option value="Bank Work">Bank Work</option>
                        <option value="Personal Work">Personal Work</option>
                        <option value="Stationary">Buy Stationary</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Destination</label>
                    <input type="text" class="form-input" name="destination" placeholder="Where are you going?" required>
                </div>
                <button type="submit" class="submit-btn">Generate Gate Pass</button>
            </form>
        </div>
    </div>

    <!-- BONAFIDE Modal -->
    <!-- BONAFIDE Modal -->
    <!-- BONAFIDE Modal -->
<div id="bonafideModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Bonafide Certificate Request</h2>
            <button class="close-modal" onclick="hideModal('bonafideModal')">×</button>
        </div>
        <form id="bonafideForm" method="POST" action="submit-request.php">
            <input type="hidden" name="request_type" value="bonafide">
            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
            <input type="hidden" name="student_name" value="<?php echo $student['name']; ?>">
            <input type="hidden" name="roll_no" value="<?php echo $student['roll_no']; ?>">
            <input type="hidden" name="enrollment_no" value="<?php echo $student['enrollment_no']; ?>">
            <input type="hidden" name="branch" value="<?php echo $student['branch']; ?>">
            <input type="hidden" name="year" value="<?php echo $student['year']; ?>">
            
            <!-- Date of Birth Field -->
            <!-- In the bonafide modal form -->
            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" 
                    class="form-input" 
                    name="date_of_birth" 
                    id="date_of_birth"
                    max="<?php echo date('Y-m-d'); ?>" 
                    value="<?php echo $student['date_of_birth'] ?? ''; ?>" 
                    required>
                <div class="text-xs text-gray-500 mt-1">
                    Please select your date of birth
                </div>
            </div>

            <!-- Rest of your form fields -->
            <div class="form-group">
                <label class="form-label">Purpose of Certificate</label>
                <select class="form-select" name="purpose" required>
                    <option value="">Select Purpose</option>
                    <option value="Scholarship">Scholarship</option>
                    <option value="Bank Account">Bank Account</option>
                    <option value="Passport">Passport Application</option>
                    <option value="Visa">Visa Application</option>
                    <option value="Education Loan">Education Loan</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        
            <div class="form-group">
                <label class="form-label">Required For</label>
                <input type="text" class="form-input" name="required_for" placeholder="e.g., Bank Name, Scholarship Name, etc." required>
            </div>
     
            <div class="form-group">
                <label class="form-label">Number of Copies</label>
                <input type="number" class="form-input" name="copies" min="1" max="5" value="1" required>
            </div>
        
            <div class="form-group">
                <label class="form-label">Additional Information</label>
                <textarea class="form-textarea" name="additional_info" placeholder="Any additional requirements or information..."></textarea>
            </div>

            <button type="submit" class="submit-btn">Request Bonafide Certificate</button>
        </form>
    </div>
</div>

        <script>
        // Modal functions
        function showModal(modalId) {
            console.log('Opening modal:', modalId);
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function hideModal(modalId) {
            console.log('Closing modal:', modalId);
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });

        // Set date restrictions
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            
            // For leave and getpass forms - set min to today
            document.querySelectorAll('#leaveModal input[type="date"], #getpassModal input[type="date"]').forEach(input => {
                input.min = today;
            });
            
            // For bonafide date of birth - set max to today (past dates only)
            const dobInput = document.getElementById('date_of_birth');
            if (dobInput) {
                dobInput.max = today;
            }

            // Add click event listeners to option cards (alternative method)
            document.querySelectorAll('.option-card').forEach(card => {
                card.addEventListener('click', function() {
                    const cardTitle = this.querySelector('.option-title').textContent;
                    if (cardTitle.includes('LEAVE')) {
                        showModal('leaveModal');
                    } else if (cardTitle.includes('GET PASS')) {
                        showModal('getpassModal');
                    } else if (cardTitle.includes('BONAFIDE')) {
                        showModal('bonafideModal');
                    }
                });
            });
        });

        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                successMessage.style.display = 'none';
            }
        }, 5000);
    </script>