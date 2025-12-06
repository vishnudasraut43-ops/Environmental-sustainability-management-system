-- ESMS Portal Database Schema
-- Created for Environmental Sustainability Management System

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `esms_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `esms_portal`;

-- Table structure for table `students`
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `roll_no` varchar(20) NOT NULL UNIQUE,
  `enrollment_no` varchar(20) NOT NULL UNIQUE,
  `branch` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `year` int(1) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `date_of_birth` DATE NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `teachers`
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `employee_id` varchar(20) NOT NULL UNIQUE,
  `department` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `class_teachers`
CREATE TABLE `class_teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `branch` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `year` int(1) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `hods`
CREATE TABLE `hods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `branch` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `office_staff`
CREATE TABLE `office_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `year_heads`
CREATE TABLE `year_heads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `year` int(1) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `security_users`
CREATE TABLE `security_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `student_requests`
CREATE TABLE `student_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `request_type` enum('leave','getpass','bonafide') NOT NULL,
  `request_data` json NOT NULL,
  `branch` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `year` int(1) NOT NULL,
  `class_teacher_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `class_teacher_remarks` text,
  `class_teacher_updated_at` timestamp NULL,
  `hod_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hod_remarks` text,
  `hod_updated_at` timestamp NULL,
  `principal_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `principal_remarks` text,
  `principal_updated_at` timestamp NULL,
  `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `teacher_requests`
CREATE TABLE `teacher_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `teacher_name` varchar(100) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department` enum('CM','EJ','ME','CE','AI','EE','IT') NOT NULL,
  `request_type` enum('cl','movement','on_duty') NOT NULL,
  `request_data` json NOT NULL,
  `hod_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hod_remarks` text,
  `hod_updated_at` timestamp NULL,
  `os_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `os_remarks` text,
  `os_updated_at` timestamp NULL,
  `principal_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `principal_remarks` text,
  `principal_updated_at` timestamp NULL,
  `final_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `teacher_leave_balance`
CREATE TABLE `teacher_leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `academic_year` year NOT NULL,
  `cl_remaining` decimal(3,1) DEFAULT 12.0,
  `movement_remaining` int(11) DEFAULT 4,
  `on_duty_remaining` int(11) DEFAULT 10,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_year` (`teacher_id`, `academic_year`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `teacher_notifications`
CREATE TABLE `teacher_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`request_id`) REFERENCES `teacher_requests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `bonafide_certificates`
CREATE TABLE `bonafide_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_number` VARCHAR(50) NOT NULL,
  `issue_date` DATE NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `class_year` VARCHAR(50) NOT NULL,
  `student_name` VARCHAR(100) NOT NULL,
  `enrollment_no` VARCHAR(20) NOT NULL,
  `date_of_birth` DATE,
  `branch` VARCHAR(10) NOT NULL,
  `purpose` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`request_id`) REFERENCES `student_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data

-- Class Teachers
INSERT INTO `class_teachers` (`name`, `username`, `branch`, `year`) VALUES
('Prof. Computer Year 1', 'CM1', 'CM', 1),
('Prof. Computer Year 2', 'CM2', 'CM', 2),
('Prof. Computer Year 3', 'CM3', 'CM', 3),
('Prof. Electronics Year 1', 'EJ1', 'EJ', 1),
('Prof. Electronics Year 2', 'EJ2', 'EJ', 2),
('Prof. Electronics Year 3', 'EJ3', 'EJ', 3),
('Prof. Mechanical Year 1', 'ME1', 'ME', 1),
('Prof. Mechanical Year 2', 'ME2', 'ME', 2),
('Prof. Mechanical Year 3', 'ME3', 'ME', 3),
('Prof. Civil Year 1', 'CE1', 'CE', 1),
('Prof. Civil Year 2', 'CE2', 'CE', 2),
('Prof. Civil Year 3', 'CE3', 'CE', 3),
('Prof. AI Year 1', 'AI1', 'AI', 1),
('Prof. AI Year 2', 'AI2', 'AI', 2),
('Prof. AI Year 3', 'AI3', 'AI', 3),
('Prof. Electrical Year 1', 'EE1', 'EE', 1),
('Prof. Electrical Year 2', 'EE2', 'EE', 2),
('Prof. Electrical Year 3', 'EE3', 'EE', 3),
('Prof. IT Year 1', 'IT1', 'IT', 1),
('Prof. IT Year 2', 'IT2', 'IT', 2),
('Prof. IT Year 3', 'IT3', 'IT', 3);

-- HODs
INSERT INTO `hods` (`name`, `username`, `branch`) VALUES
('Dr. Computer HOD', 'hodcm', 'CM'),
('Dr. Electronics HOD', 'hodej', 'EJ'),
('Dr. Mechanical HOD', 'hodme', 'ME'),
('Dr. Civil HOD', 'hodce', 'CE'),
('Dr. AI HOD', 'hodai', 'AI'),
('Dr. Electrical HOD', 'hodee', 'EE'),
('Dr. IT HOD', 'hodit', 'IT');

-- Office Staff
INSERT INTO `office_staff` (`name`, `username`, `password`) VALUES
('Office Staff', 'os', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Year Heads
INSERT INTO `year_heads` (`name`, `username`, `year`) VALUES
('Year Head First Year', 'yearhead_1', 1),
('Year Head Second Year', 'yearhead_2', 2),
('Year Head Third Year', 'yearhead_3', 3);

-- Security Users
INSERT INTO `security_users` (`name`, `username`, `password`) VALUES
('Security Officer', 'security', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Demo Students
INSERT INTO `students` (`name`, `roll_no`, `enrollment_no`, `branch`, `year`, `email`, `password`, `date_of_birth`) VALUES
('Demo Student CM', '2024CM001', 'EN2024CM001', 'CM', 2, 'demo.cm@esms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2005-05-15'),
('Demo Student EJ', '2024EJ001', 'EN2024EJ001', 'EJ', 2, 'demo.ej@esms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2005-06-20'),
('Demo Student ME', '2024ME001', 'EN2024ME001', 'ME', 2, 'demo.me@esms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2005-07-10'),
('Raut Vishnudas Abhiman', '2024CM002', '2324000185', 'CM', 3, 'vishnudas.raut@esms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2007-10-23');

-- Demo Teachers
INSERT INTO `teachers` (`name`, `email`, `employee_id`, `department`, `password`) VALUES
('Demo Teacher CM', 'demo.teacher.cm@esms.edu', 'T2024CM001', 'CM', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Demo Teacher EJ', 'demo.teacher.ej@esms.edu', 'T2024EJ001', 'EJ', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Demo Teacher ME', 'demo.teacher.me@esms.edu', 'T2024ME001', 'ME', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Prof. Computer Senior', 'computer.senior@esms.edu', 'T2024CM002', 'CM', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Demo Teacher Leave Balance
INSERT INTO `teacher_leave_balance` (`teacher_id`, `academic_year`, `cl_remaining`, `movement_remaining`, `on_duty_remaining`) VALUES
(1, 2024, 12.0, 4, 10),
(2, 2024, 10.5, 3, 8),
(3, 2024, 8.0, 2, 5),
(4, 2024, 12.0, 4, 10);

-- Sample Student Requests
INSERT INTO `student_requests` (`student_id`, `request_type`, `request_data`, `branch`, `year`, `class_teacher_status`, `hod_status`, `principal_status`) VALUES
(1, 'leave', '{"from_date": "2024-01-15", "to_date": "2024-01-17", "days": "3", "reason": "Family function", "description": "Attending cousin marriage in native place"}', 'CM', 2, 'approved', 'pending', 'pending'),
(1, 'getpass', '{"pass_date": "2024-01-10", "out_time": "14:00", "return_time": "18:00", "purpose": "Medical checkup", "destination": "City Hospital"}', 'CM', 2, 'approved', 'pending', 'pending'),
(4, 'bonafide', '{"purpose": "Education Loan", "required_for": "State Bank of India", "copies": "2", "urgency": "Normal", "additional_info": "Required for education loan application"}', 'CM', 3, 'approved', 'approved', 'pending'),
(2, 'bonafide', '{"purpose": "Scholarship", "required_for": "Government Scholarship", "copies": "1", "urgency": "Urgent", "additional_info": "Scholarship deadline approaching"}', 'EJ', 2, 'approved', 'approved', 'approved');

-- Sample Teacher Requests
INSERT INTO `teacher_requests` (`teacher_id`, `teacher_name`, `employee_id`, `department`, `request_type`, `request_data`, `hod_status`, `os_status`, `principal_status`) VALUES
(1, 'Demo Teacher CM', 'T2024CM001', 'CM', 'cl', '{"from_date": "2024-01-20", "to_date": "2024-01-22", "days": "3", "reason": "Personal work", "description": "Need to visit native place for family work"}', 'approved', 'pending', 'pending'),
(1, 'Demo Teacher CM', 'T2024CM001', 'CM', 'movement', '{"movement_date": "2024-01-12", "from_time": "10:00", "to_time": "12:00", "purpose": "Bank work", "destination": "SBI Main Branch", "remarks": "Need to submit documents"}', 'approved', 'approved', 'pending'),
(2, 'Demo Teacher EJ', 'T2024EJ001', 'EJ', 'on_duty', '{"from_date": "2024-02-01", "to_date": "2024-02-03", "purpose": "Workshop", "event_name": "Advanced Electronics Workshop", "venue": "IIT Mumbai", "additional_info": "Faculty development program"}', 'pending', 'pending', 'pending');

-- Sample Bonafide Certificates
INSERT INTO `bonafide_certificates` (`request_id`, `student_id`, `certificate_number`, `issue_date`, `academic_year`, `class_year`, `student_name`, `enrollment_no`, `date_of_birth`, `branch`, `purpose`) VALUES
(4, 2, '2024-2025/4767', '2024-01-09', '2024-2025', 'TY ELECTRONICS', 'Demo Student EJ', 'EN2024EJ001', '2005-06-20', 'EJ', 'Scholarship');

-- Sample Teacher Notifications
INSERT INTO `teacher_notifications` (`teacher_id`, `request_id`, `title`, `message`) VALUES
(1, 1, 'Application Status', 'Your CL application has been approved by HOD and forwarded to Office Staff'),
(1, 2, 'Application Status', 'Your Movement application has been approved by Office Staff and forwarded to Principal');

-- Indexes for better performance
CREATE INDEX idx_student_requests_branch_year ON student_requests(branch, year);
CREATE INDEX idx_student_requests_status ON student_requests(class_teacher_status, hod_status, principal_status);
CREATE INDEX idx_teacher_requests_status ON teacher_requests(hod_status, os_status, principal_status);
CREATE INDEX idx_teacher_requests_teacher ON teacher_requests(teacher_id);
CREATE INDEX idx_notifications_teacher ON teacher_notifications(teacher_id, is_read);
CREATE INDEX idx_bonafide_request ON bonafide_certificates(request_id);
CREATE INDEX idx_bonafide_student ON bonafide_certificates(student_id);

COMMIT;
-- Update office_staff to include principal role
UPDATE office_staff SET name = 'Principal Office' WHERE username = 'os';

-- Or create a separate principal account if needed
INSERT INTO office_staff (name, username, password) 
VALUES ('College Principal', 'principal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Add certificate_file_path to bonafide_certificates table
ALTER TABLE bonafide_certificates ADD COLUMN certificate_file_path VARCHAR(255) NULL;

-- Add final_status to student_requests for tracking completion
ALTER TABLE student_requests ADD COLUMN final_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';

-- Update existing bonafide requests that are approved by principal
UPDATE student_requests SET final_status = 'approved' 
WHERE request_type = 'bonafide' AND principal_status = 'approved';

ALTER TABLE teacher_leave_balance 
ADD COLUMN month INT NOT NULL AFTER teacher_id,
ADD COLUMN year INT NOT NULL AFTER month,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing records to current month (optional)
UPDATE teacher_leave_balance 
SET month = MONTH(CURDATE()), year = YEAR(CURDATE())
WHERE month IS NULL OR year IS NULL;

-- Add cl_taken column to teacher_requests table
ALTER TABLE teacher_requests ADD COLUMN cl_taken DECIMAL(3,1) DEFAULT 0;

-- Update existing CL requests to calculate days taken
UPDATE teacher_requests 
SET cl_taken = (
    SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(request_data, '$.days')) AS DECIMAL(3,1))
) 
WHERE request_type = 'cl' AND hod_status = 'approved';

-- Remove the old teacher_leave_balance table since we're tracking differently now
DROP TABLE IF EXISTS teacher_leave_balance;