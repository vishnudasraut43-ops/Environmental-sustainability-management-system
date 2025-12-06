
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
    
    // Fixed password for all class teachers
    $fixed_password = "YCIP@123";
    
    if ($password !== $fixed_password) {
        $error = "Invalid password! Please use YCIP@123";
    } else {
        $query = "SELECT * FROM class_teachers WHERE username = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $teacher = $result->fetch_assoc();
                    
                    // Set session variables
                    $_SESSION["class_teacher_id"] = $teacher["id"];
                    $_SESSION["class_teacher_name"] = $teacher["name"];
                    $_SESSION["class_teacher_branch"] = $teacher["branch"];
                    $_SESSION["class_teacher_year"] = $teacher["year"];
                    
                    // Redirect to dashboard
                    header("Location: class-teacher-dashboard.php");
                    exit();
                } else {
                    $error = "Class Teacher not found! Please select your department and year from the list.";
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
    <title>Class Teacher Login - ESMS</title>
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
        .department-option {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .department-option:hover {
            border-color: #667eea;
            background-color: #f7fafc;
        }
        .department-option.selected {
            border-color: #667eea;
            background-color: #edf2f7;
        }
        .branch-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 5px;
        }
        .branch-cm { background: #dbeafe; color: #1e40af; }
        .branch-ej { background: #fce7f3; color: #be185d; }
        .branch-me { background: #dcfce7; color: #166534; }
        .branch-ce { background: #fef3c7; color: #92400e; }
        .branch-ai { background: #e0e7ff; color: #3730a3; }
        .branch-ee { background: #f3e8ff; color: #6b21a8; }
        .branch-it { background: #ffedd5; color: #c2410c; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="text-center mb-2">
                <a href="index.html" class="text-blue-500 hover:text-blue-700 text-sm font-medium">← Back to Home</a>
            </div>
            
            <h1 class="text-3xl font-bold text-gray-800 mb-2 text-center">Class Teacher Login</h1>
            <p class="text-gray-600 text-center mb-8">Select your department and year</p>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-3">Select Your Class</label>
                    
                    <!-- Computer Engineering -->
                    <div class="department-option" onclick="selectOption('CM1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-cm">CM</span>
                                <span class="font-semibold text-gray-800">Computer Engineering - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CM1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('CM2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-cm">CM</span>
                                <span class="font-semibold text-gray-800">Computer Engineering - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CM2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('CM3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-cm">CM</span>
                                <span class="font-semibold text-gray-800">Computer Engineering - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CM3</div>
                        </div>
                    </div>
                    
                    <!-- Electronics Engineering -->
                    <div class="department-option" onclick="selectOption('EJ1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ej">EJ</span>
                                <span class="font-semibold text-gray-800">Electronics Engineering - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EJ1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('EJ2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ej">EJ</span>
                                <span class="font-semibold text-gray-800">Electronics Engineering - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EJ2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('EJ3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ej">EJ</span>
                                <span class="font-semibold text-gray-800">Electronics Engineering - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EJ3</div>
                        </div>
                    </div>
                    
                    <!-- Mechanical Engineering -->
                    <div class="department-option" onclick="selectOption('ME1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-me">ME</span>
                                <span class="font-semibold text-gray-800">Mechanical Engineering - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">ME1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('ME2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-me">ME</span>
                                <span class="font-semibold text-gray-800">Mechanical Engineering - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">ME2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('ME3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-me">ME</span>
                                <span class="font-semibold text-gray-800">Mechanical Engineering - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">ME3</div>
                        </div>
                    </div>
                    
                    <!-- Civil Engineering -->
                    <div class="department-option" onclick="selectOption('CE1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ce">CE</span>
                                <span class="font-semibold text-gray-800">Civil Engineering - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CE1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('CE2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ce">CE</span>
                                <span class="font-semibold text-gray-800">Civil Engineering - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CE2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('CE3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ce">CE</span>
                                <span class="font-semibold text-gray-800">Civil Engineering - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">CE3</div>
                        </div>
                    </div>
                    
                    <!-- Artificial Intelligence -->
                    <div class="department-option" onclick="selectOption('AI1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ai">AI</span>
                                <span class="font-semibold text-gray-800">Artificial Intelligence - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">AI1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('AI2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ai">AI</span>
                                <span class="font-semibold text-gray-800">Artificial Intelligence - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">AI2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('AI3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ai">AI</span>
                                <span class="font-semibold text-gray-800">Artificial Intelligence - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">AI3</div>
                        </div>
                    </div>
                    
                    <!-- Electrical Engineering -->
                    <div class="department-option" onclick="selectOption('EE1')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ee">EE</span>
                                <span class="font-semibold text-gray-800">Electrical Engineering - 1st Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EE1</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('EE2')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ee">EE</span>
                                <span class="font-semibold text-gray-800">Electrical Engineering - 2nd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EE2</div>
                        </div>
                    </div>
                    
                    <div class="department-option" onclick="selectOption('EE3')">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="branch-badge branch-ee">EE</span>
                                <span class="font-semibold text-gray-800">Electrical Engineering - 3rd Year</span>
                            </div>
                            <div class="text-sm text-gray-500">EE3</div>
                        </div>
                    </div>
                    

                    
                    <input type="hidden" id="username" name="username" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" value="" required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 text-center font-mono bg-gray-50">
                    
                </div>
                
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 font-semibold text-lg">
                    Login as Class Teacher
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h3 class="font-semibold text-blue-800 mb-2">Login Instructions:</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Click on your department and year</li>
                    <li>• Password is type your Password: </li>
                    <li>• You will see applications from students of your class</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        let selectedOption = null;
        
        function selectOption(username) {
            // Remove selected class from all options
            document.querySelectorAll('.department-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Set the username value
            document.getElementById('username').value = username;
            selectedOption = username;
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!selectedOption) {
                e.preventDefault();
                alert('Please select your department and year');
                return false;
            }
        });
    </script>
</body>
</html>
