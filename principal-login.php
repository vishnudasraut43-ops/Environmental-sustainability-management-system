<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "esms_portal";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Check in office_staff table for principal (using office_staff as principal for now)
    $query = "SELECT * FROM office_staff WHERE username = ? AND name LIKE '%principal%'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $principal = $result->fetch_assoc();
        
        // For demo, using simple password check. In production, use password_verify()
        if ($password == 'YCIP@123') {
            $_SESSION["principal_id"] = $principal['id'];
            $_SESSION["principal_name"] = $principal['name'];
            $_SESSION["principal_username"] = $principal['username'];
            
            header("Location: principal-dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Invalid username or you don't have principal access!";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal Login - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^2.0/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            position: relative;
        }
        
        .login-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #10b981, #3b82f6, #8b5cf6);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .logo i {
            font-size: 36px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 10;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f9fafb;
        }
        
        .form-input:focus {
            border-color: #4f46e5;
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(79, 70, 229, 0.3);
        }
        
        .demo-credentials {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
        }
        
        .demo-title {
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .demo-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(79, 70, 229, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(79, 70, 229, 0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container pulse">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h1 class="text-2xl font-bold">Principal Login</h1>
            <p class="mt-2 opacity-90">Education School Management System</p>
        </div>
        
        <div class="form-container">
            <form method="POST" action="">
                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input id="username" name="username" type="text" required 
                           class="form-input"
                           placeholder="Username" value="Principal">
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input id="password" name="password" type="password" required 
                           class="form-input"
                           placeholder="Password" value="">
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
                
                <div class="demo-credentials">
                    <div class="demo-title">
                        <i class="fas fa-info-circle"></i>
                        Demo Credentials
                    </div>
                    <div class="demo-item">
                        <span>Username:</span>
                        <span class="font-medium">os</span>
                    </div>
                    <div class="demo-item">
                        <span>Password:</span>
                        <span class="font-medium">principal123</span>
                    </div>
                </div>
            </form>
            
            <div class="footer">
                <p>ESMS &copy; <?php echo date('Y'); ?>. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            
            inputs.forEach(input => {
                // Add focus effect
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('i').style.color = '#4f46e5';
                });
                
                // Remove focus effect
                input.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.parentElement.querySelector('i').style.color = '#6b7280';
                    }
                });
            });
            
            // Remove pulse animation after first interaction
            const loginContainer = document.querySelector('.login-container');
            const removePulse = function() {
                loginContainer.classList.remove('pulse');
                document.removeEventListener('mousemove', removePulse);
                document.removeEventListener('keypress', removePulse);
            };
            
            document.addEventListener('mousemove', removePulse);
            document.addEventListener('keypress', removePulse);
        });
    </script>
</body>
</html>