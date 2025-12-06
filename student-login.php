<?php
// Start session at the VERY BEGINNING
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "esms_portal";

$conn = new mysqli($servername, $username, $password, $dbname);
// Create connection


// Check connection
if ($conn->connect_error) {
    // More detailed error message
    die("Database connection failed: " . $conn->connect_error . 
        ". Please check if MySQL is running in XAMPP.");
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST["form_type"] == "register") {
        // Registration form processing
        $name = trim($_POST["name"]);
        $roll_no = trim($_POST["roll_no"]);
        $enrollment_no = trim($_POST["enrollment_no"]);
        $branch = $_POST["branch"];
        $year = $_POST["year"];
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        // Validate passwords match
        if ($password !== $confirm_password) {
            $error_message = "Passwords do not match!";
        } else {
            // Check if roll number or email already exists
            $check_query = "SELECT id FROM students WHERE roll_no = ? OR email = ? OR enrollment_no = ?";
            $stmt = $conn->prepare($check_query);
            if ($stmt) {
                $stmt->bind_param("sss", $roll_no, $email, $enrollment_no);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "Roll number, enrollment number, or email already exists!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new student
                    $insert_query = "INSERT INTO students (name, roll_no, enrollment_no, branch, year, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($insert_query);
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssssiss", $name, $roll_no, $enrollment_no, $branch, $year, $email, $hashed_password);
                        
                        if ($stmt_insert->execute()) {
                            $success_message = "✅ Registration successful! You can now login.";
                            $switch_to_login = true;
                        } else {
                            $error_message = "❌ Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "❌ Database error: " . $conn->error;
                    }
                }
                $stmt->close();
            } else {
                $error_message = "❌ Database error: " . $conn->error;
            }
        }
    } elseif ($_POST["form_type"] == "login") {
        // Login form processing
        $roll_no = trim($_POST["roll_no"]);
        $password = $_POST["password"];
        
        // Check if student exists
        $query = "SELECT id, name, password FROM students WHERE roll_no = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("s", $roll_no);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $student = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $student["password"])) {
                    // Set session variables
                    $_SESSION["student_id"] = $student["id"];
                    $_SESSION["student_name"] = $student["name"];
                    $_SESSION["student_roll_no"] = $roll_no;
                    
                    // Redirect to dashboard
                    header("Location: student-dashboard.php");
                    exit();
                } else {
                    $error_message = "❌ Invalid password!";
                }
            } else {
                $error_message = "❌ Student not found! Please check your roll number.";
            }
            $stmt->close();
        } else {
            $error_message = "❌ Database error: " . $conn->error;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - ESMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { width: 100%; max-width: 400px; background: white; border-radius: 10px; box-shadow: 0 15px 30px rgba(0,0,0,0.2); overflow: hidden; }
        .form-container { padding: 30px; }
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 1px solid #eee; }
        .tab { flex: 1; text-align: center; padding: 15px; cursor: pointer; font-weight: 600; color: #666; transition: all 0.3s; }
        .tab.active { color: #764ba2; border-bottom: 3px solid #764ba2; }
        .form { display: none; }
        .form.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        input, select { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; transition: border 0.3s; }
        input:focus, select:focus { border-color: #764ba2; outline: none; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.3s; }
        button:hover { transform: translateY(-2px); }
        .message { padding: 10px; margin-top: 15px; border-radius: 5px; text-align: center; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-link { text-align: center; margin-top: 15px; }
        .back-link a { color: #764ba2; text-decoration: none; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="back-link">
                <a href="index.html">← Back to Home</a>
            </div>
            
            <div class="tabs">
                <div class="tab active" onclick="showForm('login')">Login</div>
                <div class="tab" onclick="showForm('register')">Register</div>
            </div>

            <!-- Login Form -->
            <div id="login-form" class="form active">
                <h2>Student Login</h2>
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="login">
                    <div class="input-group">
                        <label for="login_roll_no">Roll No</label>
                        <input type="text" id="login_roll_no" name="roll_no" required>
                    </div>
                    <div class="input-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
            </div>

            <!-- Registration Form -->
            <div id="register-form" class="form">
                <h2>Student Registration</h2>
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="register">
                    <div class="input-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="input-group">
                        <label for="roll_no">Roll No</label>
                        <input type="text" id="roll_no" name="roll_no" required>
                    </div>
                    <div class="input-group">
                        <label for="enrollment_no">Enrollment No</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" required>
                    </div>
                    <div class="input-group">
                        <label for="branch">Branch</label>
                        <select id="branch" name="branch" required>
                            <option value="">Select Branch</option>
                            <option value="CM">CM</option>
                            <option value="EJ">EJ</option>
                            <option value="ME">ME</option>
                            <option value="CE">CE</option>
                            <option value="AI">AI</option>
                            <option value="EE">EE</option>
                            
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="year">Year</label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit">Register</button>
                </form>
            </div>

            <?php
            // Display messages after forms
            if (isset($error_message)) {
                echo "<div class='message error'>$error_message</div>";
            }
            if (isset($success_message)) {
                echo "<div class='message success'>$success_message</div>";
            }
            ?>

            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #764ba2;">
                <h3 style="margin-bottom: 10px; color: #333; font-size: 16px;">Demo Student Credentials:</h3>
                <p><strong>Roll No:</strong> 2024CM001</p>
                <p><strong>Password:</strong> 123456</p>
            </div>
        </div>
    </div>

    <script>
        function showForm(formType) {
            // Hide all forms
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.remove('active');
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form and activate tab
            if (formType === 'login') {
                document.getElementById('login-form').classList.add('active');
                document.querySelectorAll('.tab')[0].classList.add('active');
            } else {
                document.getElementById('register-form').classList.add('active');
                document.querySelectorAll('.tab')[1].classList.add('active');
            }
        }

        <?php
        // Auto-switch to login after successful registration
        if (isset($switch_to_login) && $switch_to_login) {
            echo "showForm('login');";
        }
        ?>
    </script>
</body>
</html>