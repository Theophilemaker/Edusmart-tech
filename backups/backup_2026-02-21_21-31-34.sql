-- EduSmart Pro Database Backup
-- Generated: 2026-02-21 21:31:34



CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO activity_logs VALUES ('1','4','Registration','New user registered as student','::1','','2026-02-20 13:05:44');
INSERT INTO activity_logs VALUES ('2','4','Login','User logged in','::1','','2026-02-20 13:18:28');
INSERT INTO activity_logs VALUES ('3','4','Login','User logged in','::1','','2026-02-20 14:06:17');
INSERT INTO activity_logs VALUES ('4','5','Registration','New user registered as student','::1','','2026-02-20 14:29:23');
INSERT INTO activity_logs VALUES ('5','4','Login','User logged in','::1','','2026-02-20 14:45:42');
INSERT INTO activity_logs VALUES ('6','7','Registration','New user registered as teacher','::1','','2026-02-20 16:52:41');
INSERT INTO activity_logs VALUES ('7','7','Login','User logged in successfully','::1','','2026-02-20 16:53:08');
INSERT INTO activity_logs VALUES ('8','4','Login','User logged in successfully','::1','','2026-02-20 17:09:08');
INSERT INTO activity_logs VALUES ('9','7','Logout','User logged out','::1','','2026-02-20 17:22:21');
INSERT INTO activity_logs VALUES ('10','4','Login','User logged in successfully','::1','','2026-02-20 17:23:42');
INSERT INTO activity_logs VALUES ('11','4','Logout','User logged out','::1','','2026-02-20 17:24:35');
INSERT INTO activity_logs VALUES ('12','4','Login','User logged in successfully','::1','','2026-02-20 20:30:17');
INSERT INTO activity_logs VALUES ('13','4','Logout','User logged out','::1','','2026-02-20 20:31:28');
INSERT INTO activity_logs VALUES ('14','7','Login','User logged in successfully','::1','','2026-02-20 20:49:26');
INSERT INTO activity_logs VALUES ('15','4','Login','User logged in successfully','::1','','2026-02-21 11:09:06');
INSERT INTO activity_logs VALUES ('16','4','Logout','User logged out','::1','','2026-02-21 11:18:45');
INSERT INTO activity_logs VALUES ('17','10','Registration','New user registered as teacher','::1','','2026-02-21 12:39:13');
INSERT INTO activity_logs VALUES ('18','10','Login','User logged in successfully','::1','','2026-02-21 12:39:39');
INSERT INTO activity_logs VALUES ('19','10','Login','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 13:49:29');
INSERT INTO activity_logs VALUES ('20','10','Logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 14:20:29');
INSERT INTO activity_logs VALUES ('21','13','Registration','New user registered as student','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 14:51:10');
INSERT INTO activity_logs VALUES ('22','13','Login','User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 14:51:32');
INSERT INTO activity_logs VALUES ('23','13','Logout','User logged out','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','2026-02-21 14:52:53');


CREATE TABLE `announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text,
  `target_role` enum('all','students','teachers','parents') DEFAULT 'all',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `class_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('present','absent','late','holiday') DEFAULT 'present',
  `remarks` text,
  `marked_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `certificates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `certificate_number` varchar(50) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','issued') DEFAULT 'pending',
  `issued_by` int DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_number` (`certificate_number`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `section` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `capacity` int DEFAULT '30',
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  `category` varchar(100) DEFAULT NULL,
  `class_id` int DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `duration_hours` int DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `is_published` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `course_id` int DEFAULT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','completed','dropped') DEFAULT 'active',
  `progress_percentage` int DEFAULT '0',
  `completion_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `exam_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `submission_text` text,
  `answers` json DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `graded` tinyint(1) DEFAULT '0',
  `graded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `exam_submissions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `type` enum('mcq','essay','mixed') DEFAULT 'mcq',
  `duration_minutes` int DEFAULT NULL,
  `total_marks` int DEFAULT NULL,
  `pass_marks` int DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `lesson_uploads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` enum('pdf','doc','docx','ppt','pptx','txt','video') DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `lesson_uploads_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `lesson_uploads_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `lesson_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `viewed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_view` (`student_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_views_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_views_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `lessons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `content` text,
  `video_url` varchar(255) DEFAULT NULL,
  `pdf_url` varchar(255) DEFAULT NULL,
  `duration_minutes` int DEFAULT NULL,
  `order_index` int DEFAULT NULL,
  `is_free` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `type` enum('email','sms','both') DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO notifications VALUES ('1','','sms','SMS Notification','Welcome to EduSmart Pro Niragire Theophile!\nYour account has been created successfully.\nUsername: theophile\nPassword: 15@20004n\nLogin at: http://localhost/edusmart-pro/','sent','2026-02-20 13:05:41','2026-02-20 13:05:41');
INSERT INTO notifications VALUES ('3','','sms','SMS Notification','Welcome to EduSmart Pro mukesha anna!\nYour account has been created successfully.\nUsername: mukesha\nPassword: a12345\nLogin at: http://localhost/edusmart-pro/','sent','2026-02-20 14:29:21','2026-02-20 14:29:21');
INSERT INTO notifications VALUES ('4','','email','Welcome to EduSmart Pro - Your Account Details','\r\n                <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;\'>\r\n                    <div style=\'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;\'>\r\n                        <h1 style=\'margin: 0; font-size: 28px;\'>Welcome to EduSmart Pro!</h1>\r\n                    </div>\r\n                    <div style=\'background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;\'>\r\n                        <p>Dear <strong>mukesha anna</strong>,</p>\r\n                        <p>Your account has been created successfully. Here are your login details:</p>\r\n                        <div style=\'background: white; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #667eea;\'>\r\n                            <p><strong>Username:</strong> mukesha</p>\r\n                            <p><strong>Password:</strong> a12345</p>\r\n                            <p><strong>Role:</strong> Student</p>\r\n                            <p><strong>Admission No:</strong> STU20260005</p>\r\n                        </div>\r\n                        <p style=\'text-align: center;\'>\r\n                            <a href=\'http://localhost/edusmart-pro/auth/login.php\' style=\'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;\'>Login to Dashboard</a>\r\n                        </p>\r\n                        <p style=\'color: #666; font-size: 14px; margin-top: 30px;\'>Best regards,<br>EduSmart Pro Team</p>\r\n                    </div>\r\n                </div>\r\n                ','failed','2026-02-20 14:29:23','2026-02-20 14:29:23');


CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `payment_type` enum('admission','tuition','exam','course') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` enum('cash','card','online','bank_transfer') DEFAULT 'cash',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'completed',
  `invoice_number` varchar(50) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int DEFAULT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','true_false','essay') DEFAULT 'mcq',
  `options` json DEFAULT NULL,
  `correct_answer` text,
  `marks` int DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `total_marks_obtained` decimal(10,2) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `grade` varchar(2) DEFAULT NULL,
  `status` enum('pass','fail') DEFAULT 'fail',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `evaluated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `sms_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `message` text,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `provider` varchar(50) DEFAULT NULL,
  `response` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `admission_number` varchar(20) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `class_id` int DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('active','graduated','suspended') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO students VALUES ('1','13','STU20260013','','','','','','','','active');


CREATE TABLE `teachers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `qualification` text,
  `specialization` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO teachers VALUES ('1','7','TCH20260007','','','');
INSERT INTO teachers VALUES ('2','10','TCH20260010','','','');


CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `role` enum('super_admin','admin','teacher','student','parent') DEFAULT 'student',
  `profile_pic` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notification_preference` enum('email','sms','both') DEFAULT 'both',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO users VALUES ('2','john_teacher','john@edusmart.com','$2y$10$YourGeneratedHashHere','','','John Smith','','','teacher','','1','','2026-02-20 08:09:38','2026-02-21 12:13:18','both');
INSERT INTO users VALUES ('3','jane_student','jane@edusmart.com','$2y$10$YourGeneratedHashHere','','','Jane Doe','','','student','','1','','2026-02-20 08:09:38','2026-02-21 12:13:18','both');
INSERT INTO users VALUES ('4','theophile','theophilen298@gmail.com','$2y$10$YourGeneratedHashHere','','','Niragire Theophile','0793473101','','student','','1','2026-02-21 11:09:06','2026-02-20 13:05:41','2026-02-21 12:13:18','both');
INSERT INTO users VALUES ('5','mukesha','mukesha@gmail.com','$2y$10$YourGeneratedHashHere','','','mukesha anna','250793473101','','student','','1','','2026-02-20 14:29:21','2026-02-21 12:13:18','both');
INSERT INTO users VALUES ('7','kamana','kamana@gmail.com','$2y$10$YourGeneratedHashHere','','','kamana augustin','250793473101','','teacher','','1','2026-02-20 20:49:26','2026-02-20 16:52:41','2026-02-21 12:13:18','both');
INSERT INTO users VALUES ('10','anna','anna@gmail.com','$2y$12$VJ6VpEYnfcwzFqwUEvSq8.EsSYs6qGwmqKuoe4w0/5eaN3WIMU8hW','','','anna mukobwa','0721272615','','teacher','','1','2026-02-21 13:49:29','2026-02-21 12:39:13','2026-02-21 13:49:29','both');
INSERT INTO users VALUES ('13','kaka','kaka@gmail.com','$2y$10$p2qTbPV7HfHmXyDvVDVclu/TpdLzAM1Bsertp0D8u8YAgBayZFn/q','','','kaka  mukayi','0721272612','','student','','1','2026-02-21 14:51:32','2026-02-21 14:51:10','2026-02-21 14:51:32','both');
INSERT INTO users VALUES ('19','admin','admin@edusmart.com','admin123','','','System Administrator','','','admin','','1','','2026-02-21 15:28:45','2026-02-21 23:17:41','both');
