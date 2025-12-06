
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
    
    // Fixed password for all HODs
    $fixed_password = "YCIP@123";
    
    // Validate password
    if ($password !== $fixed_password) {
        $error = "Invalid password! Please use Correct password";
    } else {
        // Check if HOD exists
        $query = "SELECT * FROM hods WHERE username = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $hod = $result->fetch_assoc();
                    
                    // Set session variables
                    $_SESSION["hod_id"] = $hod["id"];
                    $_SESSION["hod_name"] = $hod["name"];
                    $_SESSION["hod_branch"] = $hod["branch"];
                    
                    // Redirect to dashboard
                    header("Location: hod-dashboard.php");
                    exit();
                } else {
                    $error = "HOD not found! Please check your department selection.";
                }
            } else {
                $error = "Login failed: " . $stmt->error;
            }
            $stmt->close();
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
    <title>HOD Login - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            max-width: 450px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-2">
                <a href="index.html" class="text-blue-500 hover:text-blue-700 text-sm font-medium">← Back to Home</a>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">HOD Login</h1>
            <p class="text-gray-600 text-center mb-8">Select your department</p>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                    <select id="username" name="username" required class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 bg-gray-50">
                        <option value="">Select Department</option>
                        <option value="hodcm">Computer Engineering (hodcm)</option>
                        <option value="hodej">Electronics Engineering (hodej)</option>
                        <option value="hodme">Mechanical Engineering (hodme)</option>
                        <option value="hodce">Civil Engineering (hodce)</option>
                        <option value="hodai">Artificial Intelligence (hodai)</option>
                        <option value="hodee">Electrical Engineering (hodee)</option>
                        <option value="hodit">Information Technology (hodit)</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" value="" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-center font-mono bg-gray-50">

                </div>
                
                <button type="submit" class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold text-lg">
                    Login as HOD
                </button>
            </form>
        </div>
    </div>
</body>
</html>
