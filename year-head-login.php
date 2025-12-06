
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
    
    $query = "SELECT * FROM year_heads WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $year_head = $result->fetch_assoc();
        // Simple password check (in production, use password_verify)
        if ($password === 'YCIP@123') { // Default password
            $_SESSION["year_head_id"] = $year_head["id"];
            $_SESSION["year_head_name"] = $year_head["name"];
            $_SESSION["year_head_year"] = $year_head["year"];
            header("Location: year-head-dashboard.php");
            exit();
        } else {
            $error = "Invalid password! Please use Correct'password'";
        }
    } else {
        $error = "Year head not found!";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Year Head Login - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .login-container {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
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
                <a href="index.html" class="text-pink-600 hover:text-pink-800 text-sm font-medium">← Back to Home</a>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Year Head Login</h1>
            <p class="text-gray-600 text-center mb-8">Year-wise Student Applications Access</p>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Select Year</label>
                    <select id="username" name="username" required 
                            class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500 bg-gray-50">
                        <option value="">Select Year Head</option>
                        <option value="yearhead_1">First Year Head</option>
                        <option value="yearhead_2">Second Year Head</option>
                        <option value="yearhead_3">Third Year Head</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" value="" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500 text-center font-mono bg-gray-50">
                    
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-pink-500 to-rose-500 text-white py-3 px-4 rounded-lg hover:from-pink-600 hover:to-rose-600 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-opacity-50 font-semibold text-lg">
                    Login as Year Head
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-pink-50 rounded-lg border border-pink-200">
                <h3 class="font-semibold text-pink-800 mb-2">Year Head Access:</h3>
                <ul class="text-sm text-pink-700 space-y-1">
                    <li>• View student leave applications by year</li>
                    <li>• Monitor gate pass requests by year</li>
                    <li>• Date-wise filtering and analytics</li>
                    <li>• Year-wise student tracking</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
