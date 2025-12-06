<?php
session_start();
if (!isset($_SESSION["teacher_id"])) {
    header("Location: teacher-login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "esms_portal";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get teacher details
$teacher_id = $_SESSION["teacher_id"];
$query = "SELECT * FROM teachers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

// Get all teachers for substitute selection (excluding current teacher)
$teachers_query = "SELECT id, name, employee_id FROM teachers WHERE id != ? ORDER BY name";
$teachers_stmt = $conn->prepare($teachers_query);
$teachers_stmt->bind_param("i", $teacher_id);
$teachers_stmt->execute();
$all_teachers = $teachers_stmt->get_result();

$stmt->close();
$teachers_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply On Duty Request - ESMS</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="bg-green-600 text-white shadow-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold">Apply On Duty Request</h1>
                    <p class="text-green-100">Apply for on-duty leave for official work</p>
                </div>
                <div class="flex space-x-4">
                    <a href="teacher-dashboard.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" action="submit-teacher-request.php" id="onDutyForm">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($teacher['name']); ?>">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($teacher['employee_id']); ?>">
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($teacher['department']); ?>">
                    <input type="hidden" name="request_type" value="on_duty">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                            <input type="date" name="from_date" id="from_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                            <input type="date" name="to_date" id="to_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Number of Days</label>
                        <input type="number" name="daysod" step="0.5" min="0.5" max="12" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., 2.5">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purpose</label>
                        <input type="text" name="purpose" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                               placeholder="Official purpose of on-duty">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Event Name</label>
                        <input type="text" name="event_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                               placeholder="Name of the event/conference/training">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Venue</label>
                        <input type="text" name="venue" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                               placeholder="Venue/location of the event">
                    </div>

                    <!-- Class Arrangement Section -->
                    <div class="mb-6 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Class Arrangement Details</h3>
                        
                        <!-- Lecture and Practical Checkboxes -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Select classes that need arrangement:</label>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="lecture_arrangement" name="lecture_arrangement" value="yes" 
                                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="lecture_arrangement" class="ml-2 block text-sm text-gray-900">
                                        Lecture Classes Need Arrangement
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="practical_arrangement" name="practical_arrangement" value="yes"
                                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="practical_arrangement" class="ml-2 block text-sm text-gray-900">
                                        Practical Classes Need Arrangement
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Lecture Substitute (Conditional) -->
                        <div id="lectureSubstituteSection" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lecture Taken By (Substitute Teacher)</label>
                            <select name="lecture_substitute" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                                <option value="">Select substitute teacher for lectures</option>
                                <?php while($sub_teacher = $all_teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $sub_teacher['id'] . '|' . htmlspecialchars($sub_teacher['name']); ?>">
                                        <?php echo htmlspecialchars($sub_teacher['name']); ?> (<?php echo htmlspecialchars($sub_teacher['employee_id']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Practical Substitute (Conditional) -->
                        <div id="practicalSubstituteSection" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Practical Taken By (Substitute Teacher)</label>
                            <select name="practical_substitute" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                                <option value="">Select substitute teacher for practicals</option>
                                <?php 
                                // Reset pointer for second dropdown
                                $all_teachers->data_seek(0);
                                while($sub_teacher = $all_teachers->fetch_assoc()): ?>
                                    <option value="<?php echo $sub_teacher['id'] . '|' . htmlspecialchars($sub_teacher['name']); ?>">
                                        <?php echo htmlspecialchars($sub_teacher['name']); ?> (<?php echo htmlspecialchars($sub_teacher['employee_id']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Subjects Covered (Conditional) -->
                        <div id="subjectsCoveredSection" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subjects/Lectures to be Covered</label>
                            <textarea name="subjects_covered" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                      placeholder="List the subjects and lectures that will be taken by substitute teachers..."></textarea>
                        </div>
                        
                        <!-- Practicals Covered (Conditional) -->
                        <div id="practicalsCoveredSection" class="mb-4 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Practical Sessions to be Covered</label>
                            <textarea name="practicals_covered" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                      placeholder="List the practical sessions that will be taken by substitute teachers..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                        <textarea name="additional_info" rows="4" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                  placeholder="Any additional information about the on-duty request..."></textarea>
                    </div>
                    
                    <!-- Calculated Days Display -->
                    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">On Duty Duration:</h4>
                        <p class="text-sm text-blue-700" id="daysDisplay">Select dates to calculate duration</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="teacher-dashboard.php" 
                           class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            Submit On Duty Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- On Duty Guidelines -->
        <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <h3 class="font-semibold text-green-800 mb-2">On Duty Request Guidelines:</h3>
            <ul class="text-sm text-green-700 space-y-1">
                <li>• Maximum 10 on-duty days allowed per academic year</li>
                <li>• Must arrange substitute teachers for all classes</li>
                <li>• Provide complete details of event and venue</li>
                <li>• Approval subject to department workload</li>
                <li>• Submit supporting documents if required</li>
            </ul>
        </div>
    </div>

    <script>
        // Calculate days between dates
        function calculateDays() {
            const fromDate = new Date(document.getElementById('from_date').value);
            const toDate = new Date(document.getElementById('to_date').value);
            
            if (fromDate && toDate && fromDate <= toDate) {
                const timeDiff = toDate.getTime() - fromDate.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                document.getElementById('daysDisplay').textContent = 
                    `Total On Duty Days: ${daysDiff} day(s) (From ${fromDate.toDateString()} to ${toDate.toDateString()})`;
            } else {
                document.getElementById('daysDisplay').textContent = 'Select valid dates to calculate duration';
            }
        }

        // Add event listeners for date changes
        document.getElementById('from_date').addEventListener('change', calculateDays);
        document.getElementById('to_date').addEventListener('change', calculateDays);

        // Toggle substitute sections based on checkbox selection
        document.getElementById('lecture_arrangement').addEventListener('change', function() {
            const lectureSection = document.getElementById('lectureSubstituteSection');
            const subjectsSection = document.getElementById('subjectsCoveredSection');
            
            if (this.checked) {
                lectureSection.classList.remove('hidden');
                subjectsSection.classList.remove('hidden');
            } else {
                lectureSection.classList.add('hidden');
                subjectsSection.classList.add('hidden');
            }
        });

        document.getElementById('practical_arrangement').addEventListener('change', function() {
            const practicalSection = document.getElementById('practicalSubstituteSection');
            const practicalsSection = document.getElementById('practicalsCoveredSection');
            
            if (this.checked) {
                practicalSection.classList.remove('hidden');
                practicalsSection.classList.remove('hidden');
            } else {
                practicalSection.classList.add('hidden');
                practicalsSection.classList.add('hidden');
            }
        });

        // Form validation
        document.getElementById('onDutyForm').addEventListener('submit', function(e) {
            const lectureArrangement = document.getElementById('lecture_arrangement').checked;
            const practicalArrangement = document.getElementById('practical_arrangement').checked;
            
            // Check if arrangement is needed but substitute not selected
            if (lectureArrangement) {
                const lectureSub = document.querySelector('select[name="lecture_substitute"]').value;
                const subjectsCovered = document.querySelector('textarea[name="subjects_covered"]').value;
                
                if (!lectureSub) {
                    e.preventDefault();
                    alert('Please select a substitute teacher for lectures.');
                    return false;
                }
                if (!subjectsCovered.trim()) {
                    e.preventDefault();
                    alert('Please provide details of subjects/lectures to be covered.');
                    return false;
                }
            }
            
            if (practicalArrangement) {
                const practicalSub = document.querySelector('select[name="practical_substitute"]').value;
                const practicalsCovered = document.querySelector('textarea[name="practicals_covered"]').value;
                
                if (!practicalSub) {
                    e.preventDefault();
                    alert('Please select a substitute teacher for practicals.');
                    return false;
                }
                if (!practicalsCovered.trim()) {
                    e.preventDefault();
                    alert('Please provide details of practical sessions to be covered.');
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>