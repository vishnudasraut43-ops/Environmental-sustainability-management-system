
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
    $password = $_POST['password'];
    
    // Fixed password for security
    $fixed_password = "YCIP@123";
    
    if ($password !== $fixed_password) {
        $error = "Invalid password! Please use security123";
    } else {
        $query = "SELECT * FROM security_users WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $security = $result->fetch_assoc();
            $_SESSION["security_id"] = $security["id"];
            $_SESSION["security_name"] = $security["name"];
            header("Location: security-dashboard.php");
            exit();
        } else {
            $error = "Security user not found!";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Login - ESMS</title>
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
                <a href="index.html" class="text-orange-500 hover:text-orange-700 text-sm font-medium">← Back to Home</a>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Security Login</h1>
            <p class="text-gray-600 text-center mb-8">Gate Security Access</p>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                    <input type="text" id="username" name="username" value="security" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500 text-center bg-gray-50 font-mono">
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" value="" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-orange-500 text-center font-mono bg-gray-50">

                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-orange-500 to-yellow-500 text-white py-3 px-4 rounded-lg hover:from-orange-600 hover:to-yellow-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 font-semibold text-lg">
                    Login as Security
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-orange-50 rounded-lg border border-orange-200">
                <h3 class="font-semibold text-orange-800 mb-2">Security Access:</h3>
                <ul class="text-sm text-orange-700 space-y-1">
                    <li>• View approved leave applications</li>
                    <li>• Check gate pass approvals</li>
                    <li>• Department-wise student tracking</li>
                    <li>• Date-wise filtering</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
