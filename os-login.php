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

// Process login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Fixed credentials for OS
    $valid_username = "os";
    $valid_password = "YCIP@123";
    
    if ($username !== $valid_username || $password !== $valid_password) {
        $error = "Invalid credentials! Please use correct username ,password: ";
    } else {
        // Set session variables
        $_SESSION["os_id"] = 1;
        $_SESSION["os_name"] = "Office Staff";
        $_SESSION["os_username"] = $username;
        
        // Redirect to dashboard
        header("Location: os-teacher-dashboard.php");
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office Staff Login - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .login-container {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-2">
                <a href="index.html" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">← Back to Home</a>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Office Staff Login</h1>
            <p class="text-gray-600 text-center mb-8">Leave Balance Verification</p>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                    <input type="text" id="username" name="username" value="os" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-yellow-500 text-center bg-gray-50 font-mono">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" value="" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-yellow-500 text-center font-mono bg-gray-50">

                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-yellow-600 to-orange-600 text-white py-3 px-4 rounded-lg hover:from-yellow-700 hover:to-orange-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50 font-semibold text-lg">
                    Login as Office Staff
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                <h3 class="font-semibold text-yellow-800 mb-2">Office Staff Access:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Verify teacher leave balance</li>
                    <li>• Check CL, Movement, On Duty availability</li>
                    <li>• Forward approved applications to Principal</li>
                    <li>• Maintain leave records</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>