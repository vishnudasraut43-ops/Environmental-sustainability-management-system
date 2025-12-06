
<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "esms_portal";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST["form_type"] == "register") {
        // Registration form processing
        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]);
        $employee_id = trim($_POST["employee_id"]);
        $department = $_POST["department"];
        $password = $_POST["password"];
        $confirm_password = $_POST["confirm_password"];

        // Validate passwords match
        if ($password !== $confirm_password) {
            $error_message = "Passwords do not match!";
        } else {
            // Check if employee ID or email already exists
            $check_query = "SELECT id FROM teachers WHERE employee_id = ? OR email = ?";
            $stmt = $conn->prepare($check_query);
            if ($stmt) {
                $stmt->bind_param("ss", $employee_id, $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = "Employee ID or email already exists!";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new teacher
                    $insert_query = "INSERT INTO teachers (name, email, employee_id, department, password) VALUES (?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($insert_query);
                    
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssss", $name, $email, $employee_id, $department, $hashed_password);
                        
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
        $employee_id = trim($_POST["employee_id"]);
        $password = $_POST["password"];
        
        // Check if teacher exists
        $query = "SELECT id, name, password, department FROM teachers WHERE employee_id = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $teacher = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $teacher["password"])) {
                    // Set session variables
                    $_SESSION["teacher_id"] = $teacher["id"];
                    $_SESSION["teacher_name"] = $teacher["name"];
                    $_SESSION["teacher_employee_id"] = $employee_id;
                    $_SESSION["teacher_department"] = $teacher["department"];
                    
                    // Redirect to dashboard
                    header("Location: teacher-dashboard.php");
                    exit();
                } else {
                    $error_message = "❌ Invalid password!";
                }
            } else {
                $error_message = "❌ Teacher not found! Please check your Employee ID.";
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
    <title>Teacher Login - ESMS</title>
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
                <h2>Teacher Login</h2>
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="login">
                    <div class="input-group">
                        <label for="login_employee_id">Teacher ID</label>
                        <input type="text" id="login_employee_id" name="employee_id" required>
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
                <h2>Teacher Registration</h2>
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="register">
                    <div class="input-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="employee_id">Teacher ID</label>
                        <input type="text" id="employee_id" name="employee_id" required>
                    </div>
                    <div class="input-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="CM">Computer Engineering</option>
                            <option value="EJ">Electronics Engineering</option>
                            <option value="ME">Mechanical Engineering</option>
                            <option value="CE">Civil Engineering</option>
                            <option value="AI">Artificial Intelligence</option>
                            <option value="EE">Electrical Engineering</option>
                            <option value="IT">Information Technology</option>
                        </select>
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
                <h3 style="margin-bottom: 10px; color: #333; font-size: 16px;">Demo Teacher Credentials:</h3>
                <p><strong>Teacher ID:</strong> T2024CM001</p>
                <p><strong>Password:</strong> password</p>
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
