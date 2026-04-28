CREATE DATABASE IF NOT EXISTS `toolshare_hub`;
USE `toolshare_hub`;


CREATE TABLE `area` (
  `area_id` int(11) NOT NULL AUTO_INCREMENT,
  `area_name` varchar(100) NOT NULL,
  `district` varchar(100) NOT NULL,
  PRIMARY KEY (`area_id`)
);

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`)
);

CREATE TABLE `member` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `area_id` int(11) NOT NULL,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `total_loans` int(11) DEFAULT 0,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `profile_bio` text DEFAULT NULL,
  `role` enum('Admin','Member') DEFAULT 'Member',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `email` (`email`),
  KEY `area_id` (`area_id`),
  CONSTRAINT `member_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `area` (`area_id`)
);


CREATE TABLE `tool` (
  `tool_id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `condition` enum('New','Good','Fair','Needs Inspection') DEFAULT 'Good',
  `status` enum('Available','On Loan','Needs Inspection','Lost') DEFAULT 'Available',
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`tool_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_tool_owner` (`owner_id`),
  KEY `idx_tool_status` (`status`),
  CONSTRAINT `tool_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `tool_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`)
);

CREATE TABLE `borrow_request` (
  `req_id` int(11) NOT NULL AUTO_INCREMENT,
  `tool_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `requested_date` date NOT NULL,
  `duration_days` int(11) NOT NULL CHECK (`duration_days` > 0),
  `message` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `decision_by` int(11) DEFAULT NULL,
  `decision_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`req_id`),
  KEY `idx_borrow_tool` (`tool_id`),
  KEY `idx_borrow_requester` (`requester_id`),
  CONSTRAINT `borrow_request_ibfk_1` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`),
  CONSTRAINT `borrow_request_ibfk_2` FOREIGN KEY (`requester_id`) REFERENCES `member` (`member_id`)
);

CREATE TABLE `loan_record` (
  `loan_id` int(11) NOT NULL AUTO_INCREMENT,
  `tool_id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `req_id` int(11) DEFAULT NULL,
  `loan_date` date NOT NULL DEFAULT (curdate()),
  `expected_return` date NOT NULL,
  `status` enum('Active','Returned','Overdue','Lost') DEFAULT 'Active',
  PRIMARY KEY (`loan_id`),
  KEY `req_id` (`req_id`),
  KEY `idx_loan_borrower` (`borrower_id`),
  KEY `idx_loan_tool` (`tool_id`),
  KEY `idx_loan_status` (`status`),
  CONSTRAINT `loan_record_ibfk_1` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`),
  CONSTRAINT `loan_record_ibfk_2` FOREIGN KEY (`borrower_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `loan_record_ibfk_3` FOREIGN KEY (`req_id`) REFERENCES `borrow_request` (`req_id`)
);

CREATE TABLE `return_log` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `actual_return_date` date NOT NULL DEFAULT (curdate()),
  `condition_on_return` enum('New','Good','Fair','Damaged') NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `loan_id` (`loan_id`),
  CONSTRAINT `return_log_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_record` (`loan_id`)
);

CREATE TABLE `damage_report` (
  `damage_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('Minor','Moderate','Severe') NOT NULL,
  `reported_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`damage_id`),
  KEY `reported_by` (`reported_by`),
  KEY `idx_damage_loan` (`loan_id`),
  CONSTRAINT `damage_report_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_record` (`loan_id`),
  CONSTRAINT `damage_report_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `member` (`member_id`)
);

CREATE TABLE `review` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `loan_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewee_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uq_review_per_user_loan` (`loan_id`,`reviewer_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `idx_review_reviewee` (`reviewee_id`),
  CONSTRAINT `review_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_record` (`loan_id`),
  CONSTRAINT `review_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `member` (`member_id`),
  CONSTRAINT `review_ibfk_3` FOREIGN KEY (`reviewee_id`) REFERENCES `member` (`member_id`)
);

CREATE TABLE `tool_condition_log` (
  `cond_id` int(11) NOT NULL AUTO_INCREMENT,
  `tool_id` int(11) NOT NULL,
  `previous_condition` varchar(50) DEFAULT NULL,
  `new_condition` varchar(50) NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`cond_id`),
  KEY `idx_condlog_tool` (`tool_id`),
  CONSTRAINT `tool_condition_log_ibfk_1` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`)
);











INSERT INTO `area` (`area_id`, `area_name`, `district`) VALUES
(1, 'Dhanmondi', 'Dhaka'),
(2, 'Mirpur', 'Dhaka'),
(3, 'Gulshan', 'Dhaka'),
(4, 'Uttara', 'Dhaka'),
(5, 'Mohammadpur', 'Dhaka');
 
INSERT INTO `category` (`category_id`, `name`, `description`) VALUES
(1, 'Power Tools', 'Electric and battery-powered tools for heavy-duty tasks'),
(2, 'Hand Tools', 'Manual tools like wrenches, hammers, screwdrivers'),
(3, 'Garden Tools', 'Equipment for gardening, landscaping, and outdoor work'),
(4, 'Kitchen Appliances', 'Large kitchen equipment for cooking and food preparation'),
(5, 'Cleaning Equipment', 'Vacuums, pressure washers, floor-care machines'),
(6, 'Electrical', 'Multimeters, extension cords, electrical testing gear'),
(7, 'Plumbing', 'Pipe tools, drain snakes, plumbing kits'),
(8, 'Painting', 'Spray guns, rollers, masking supplies'),
(9, 'Automotive', 'Car jacks, tire inflators, polishers'),
(10, 'Outdoor & Camping', 'Tents, portable stoves, fishing and outdoor gear');
 
INSERT INTO `member` (`member_id`, `name`, `email`, `phone`, `password`, `area_id`, `avg_rating`, `total_loans`, `is_banned`, `profile_bio`, `role`, `created_at`) VALUES
(1, 'Kamal Hossain', 'admin@toolshare.com', '01711000055', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 0.00, 0, 0, '', 'Admin', '2024-01-01 08:00:00'),
(2, 'Rahim Uddin', 'rahim@email.com', '01711000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 5.00, 0, 0, NULL, 'Member', '2024-02-10 09:15:00'),
(3, 'Nasrin Akter', 'nasrin@email.com', '01711000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 3.67, 1, 0, NULL, 'Member', '2024-02-15 10:20:00'),
(4, 'Karim Sheikh', 'karim@email.com', '01711000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 5.00, 1, 0, NULL, 'Member', '2024-03-01 11:00:00'),
(5, 'Fatema Begum', 'fatema@email.com', '01711000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 0.00, 1, 0, NULL, 'Member', '2024-03-05 12:30:00'),
(6, 'Rafiqul Islam', 'rafiqul@email.com', '01711000006', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 3.00, 1, 0, NULL, 'Member', '2024-03-10 08:45:00'),
(7, 'Shirin Sultana', 'shirin@email.com', '01711000007', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 0.00, 1, 0, NULL, 'Member', '2024-03-12 09:00:00'),
(8, 'Abul Kalam', 'abul@email.com', '01711000008', '$2y$10$evC22VCf0qf72ybXWXRhPOB7XJt223l4SwrPSpg7PaZ9KZQOlalbq', 2, 3.50, 3, 0, 'I am a member', 'Member', '2024-03-20 14:00:00'),
(9, 'Ruksana Khanam', 'ruksana@email.com', '01711000009', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 4.00, 1, 0, NULL, 'Member', '2024-04-01 10:10:00'),
(10, 'Jahangir Alam', 'jahangir@email.com', '01711000010', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 2.50, 0, 0, NULL, 'Member', '2024-04-05 11:30:00'),
(11, 'Morsheda Khatun', 'morsheda@email.com', '01711000011', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 5.00, 1, 0, NULL, 'Member', '2024-04-10 08:00:00'),
(12, 'Sazzad Hossain', 'sazzad@email.com', '01711000012', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 0.00, 1, 1, NULL, 'Member', '2024-04-15 09:45:00'),
(13, 'Dilruba Yeasmin', 'dilruba@email.com', '01711000013', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 3.00, 1, 0, NULL, 'Member', '2024-05-01 10:00:00'),
(14, 'Monir Hossain', 'monir@email.com', '01711000014', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 4.00, 1, 0, NULL, 'Member', '2024-05-05 12:00:00'),
(15, 'Tahmina Parvin', 'tahmina@email.com', '01711000015', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 0.00, 1, 0, NULL, 'Member', '2024-05-10 13:00:00'),
(16, 'Rezaul Karim', 'rezaul@email.com', '01711000016', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 3.00, 1, 0, NULL, 'Member', '2024-06-01 08:30:00'),
(17, 'Sabina Islam', 'sabina@email.com', '01711000017', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 5.00, 1, 0, NULL, 'Member', '2024-06-10 09:00:00'),
(18, 'Farid Ahmed', 'farid@email.com', '01711000018', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 0.00, 1, 0, NULL, 'Member', '2024-06-15 10:00:00'),
(19, 'Lailufar Nessa', 'lailufar@email.com', '01711000019', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 4.00, 1, 0, NULL, 'Member', '2024-07-01 11:00:00'),
(20, 'Shahed Ali', 'shahed@email.com', '01711000020', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 2.00, 1, 0, NULL, 'Member', '2024-07-05 12:00:00'),
(21, 'Kohinoor Begum', 'kohinoor@email.com', '01711000021', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 0.00, 1, 0, NULL, 'Member', '2024-07-10 13:00:00'),
(22, 'Sabbir Hossain Osmani', 'osmani@gmail.com', '01631183761', '$2y$10$y6CKY1fK8lukrqNiT3R97u2rKmm4sAIl8pBHU1h5srCLdz.fPQTv6', 3, 4.00, 1, 0, NULL, 'Member', '2026-04-26 23:20:27');
 
INSERT INTO `tool` (`tool_id`, `owner_id`, `category_id`, `name`, `condition`, `status`, `description`, `created_at`) VALUES
(1, 2, 1, 'Bosch Corded Drill 750W', 'Good', 'Available', '750W corded drill, variable speed, includes 10-piece bit set.', '2024-02-12 10:00:00'),
(2, 3, 1, 'Makita Angle Grinder 125mm', 'Good', 'Available', '720W angle grinder with cutting and grinding discs included.', '2024-02-18 11:00:00'),
(3, 4, 1, 'Black+Decker Circular Saw', 'Fair', 'Available', '1200W circular saw, 185mm blade. Slight wear on grip but fully functional.', '2024-03-05 09:00:00'),
(4, 5, 1, 'Dewalt 18V Impact Driver', 'Good', 'Lost', 'Cordless impact driver with 2 batteries and fast charger.', '2024-03-08 10:30:00'),
(5, 6, 2, 'Stanley 128-Piece Tool Box Set', 'Good', 'Available', 'Complete home tool kit: hammers, screwdrivers, pliers, tape, level.', '2024-03-12 08:00:00'),
(6, 7, 2, 'Craftsman Metric Wrench Set (20pc)', 'New', 'On Loan', 'Full metric set 6mm-32mm. Chrome vanadium steel.', '2024-03-15 09:00:00'),
(7, 8, 2, 'Heavy-Duty Hammer & Chisel Kit', 'Good', 'Available', '1kg claw hammer + 6-piece cold chisel set.', '2024-03-22 10:00:00'),
(8, 9, 3, 'Stainless Steel Spade & Fork Set', 'Good', 'Available', 'Full-size spade and garden fork. Hardwood ash handles.', '2024-04-03 08:30:00'),
(9, 10, 3, 'Electric Hedge Trimmer 600W', 'Good', 'Available', '45cm blade, 600W motor. Dual safety switch.', '2024-04-08 09:00:00'),
(10, 11, 3, 'Garden Hose 25m with Adjustable Sprinkler', 'Fair', 'Lost', '25-metre heavy-duty hose with 8-pattern spray gun.', '2024-04-12 10:00:00'),
(11, 12, 4, 'Westinghouse Commercial Blender 2L', 'Good', 'On Loan', '1000W commercial blender, 2-litre jar.', '2024-04-18 09:30:00'),
(12, 13, 4, 'Walton Electric Rice Cooker 5L', 'Fair', 'Needs Inspection', 'Large 5L capacity, keep-warm function. Lid latch cracked.', '2024-04-20 10:00:00'),
(13, 14, 4, 'Panasonic Food Processor MK-F500', 'Good', 'Available', '550W food processor with slicing, grating, and blending attachments.', '2024-05-03 08:00:00'),
(14, 15, 5, 'Karcher K2 Pressure Washer', 'Good', 'Available', '1400W pressure washer, 110 bar.', '2024-05-08 09:00:00'),
(15, 16, 5, 'Philips Wet/Dry Vacuum 20L', 'Fair', 'Available', '1600W wet/dry vac, 20L tank. Suction hose has a small puncture.', '2024-05-12 10:00:00'),
(16, 17, 5, 'Beldray Floor Polisher', 'Good', 'Available', 'Dual-pad floor polisher, works on tiles and marble.', '2024-05-15 11:00:00'),
(17, 18, 6, 'Bosch Professional Corded Drill + Bit Set', 'Good', 'Available', '550W drill with full-size SDS chuck, 30-piece bit set.', '2024-06-03 08:00:00'),
(18, 19, 6, 'Fluke 117 Multimeter', 'Good', 'Available', 'True-RMS digital multimeter.', '2024-06-08 09:00:00'),
(19, 20, 6, 'Heavy-Duty Extension Cord 30m', 'Fair', 'Lost', '30-metre 3-pin extension cord, 13A.', '2024-06-12 10:00:00'),
(20, 21, 7, 'Heavy-Duty Pipe Wrench Set (3pc)', 'Good', 'On Loan', '10", 14" and 18" Rigid pipe wrenches.', '2024-06-15 11:00:00'),
(21, 2, 7, 'Electric Drain Snake 15m', 'Fair', 'Available', '15m motor-driven drum auger.', '2024-06-18 08:30:00'),
(22, 3, 8, 'Wagner Spray Paint Gun W990', 'Good', 'Available', '1300W HVLP spray station.', '2024-07-02 09:00:00'),
(23, 4, 8, 'Professional Paint Roller Set (12pc)', 'New', 'Available', 'Long-arm roller frame + 6 sleeves + tray + edging brush.', '2024-07-05 10:00:00'),
(24, 5, 8, 'Masking Tape + Canvas Drop Cloth Bundle', 'Good', 'Available', '10-roll multi-width masking tape + 3.5m x 3.5m canvas drop cloth.', '2024-07-08 11:00:00'),
(25, 6, 9, 'Hydraulic Trolley Car Jack 2 Ton', 'Good', 'On Loan', '2-ton hydraulic floor jack, lift range 13-33cm.', '2024-07-12 08:00:00'),
(26, 7, 9, 'Ring RAC635 Tire Inflator', 'Good', 'Available', 'Digital tyre inflator, auto shut-off at target pressure.', '2024-07-15 09:00:00'),
(28, 9, 10, 'Coleman 4-Person Camping Tent', 'Good', 'On Loan', 'Waterproof 4-person dome tent, 3000mm HH rating.', '2024-08-01 08:00:00'),
(29, 10, 10, 'Campingaz 2-Burner Portable Gas Stove', 'Good', 'Available', 'Folds flat for transport, windshields included.', '2024-08-05 09:00:00'),
(30, 11, 10, 'Abu Garcia Fishing Rod & Reel Combo Set', 'Good', 'Available', '2.4m medium-action rod, pre-spooled reel, tackle box.', '2024-08-10 10:00:00'),
(31, 22, 2, 'Ladder', 'Fair', 'Available', '10 m ladder', '2026-04-27 14:10:04');
 
INSERT INTO `borrow_request` (`req_id`, `tool_id`, `requester_id`, `requested_date`, `duration_days`, `message`, `status`, `decision_by`, `decision_at`, `created_at`) VALUES
(1, 3, 8, '2025-09-30', 7, 'Need the circular saw to cut timber for a home shelf project.', 'Approved', NULL, NULL, '2025-09-30 10:00:00'),
(2, 7, 14, '2025-10-04', 7, 'Planning to break old tiles in my kitchen renovation.', 'Approved', NULL, NULL, '2025-10-04 11:00:00'),
(3, 12, 6, '2025-10-09', 10, 'Need the big rice cooker for my daughter\'s wedding reception.', 'Approved', NULL, NULL, '2025-10-09 09:00:00'),
(4, 1, 17, '2025-10-31', 7, 'I need to drill wall anchors for a large bookshelf.', 'Approved', NULL, NULL, '2025-10-31 08:30:00'),
(5, 5, 11, '2025-11-14', 7, 'Moving to a new apartment — need a complete tool set.', 'Approved', NULL, NULL, '2025-11-14 10:00:00'),
(6, 9, 20, '2025-11-30', 9, 'Garden needs a full trim before winter.', 'Approved', NULL, NULL, '2025-11-30 09:30:00'),
(7, 15, 3, '2025-12-04', 7, 'Wet and dry vacuum needed for deep cleaning before guests.', 'Approved', NULL, NULL, '2025-12-04 11:00:00'),
(8, 2, 9, '2026-01-09', 7, 'Need the angle grinder to cut steel rods for a gate repair.', 'Approved', NULL, NULL, '2026-01-09 08:00:00'),
(9, 4, 18, '2026-02-28', 14, 'Drilling 40+ holes for curtain rods in our new flat.', 'Approved', NULL, NULL, '2026-02-28 10:00:00'),
(10, 10, 21, '2026-03-09', 14, 'Setting up a rooftop garden. Need hose for daily watering.', 'Approved', NULL, NULL, '2026-03-09 11:00:00'),
(11, 6, 4, '2026-04-19', 10, 'Need the wrench set for a plumbing repair job.', 'Approved', NULL, NULL, '2026-04-19 09:00:00'),
(12, 13, 16, '2026-04-20', 5, 'Want to borrow the food processor for spice prep.', 'Pending', NULL, NULL, '2026-04-20 10:30:00'),
(13, 26, 14, '2026-04-23', 3, 'My car tyre is low — need the inflator for the weekend.', 'Pending', NULL, NULL, '2026-04-23 11:00:00'),
(14, 29, 12, '2026-04-24', 7, 'Family trip to Sundarbans. Need the portable stove.', 'Rejected', NULL, NULL, '2026-04-24 09:00:00'),
(15, 30, 19, '2026-04-25', 5, 'Weekend fishing trip with friends.', 'Pending', NULL, NULL, '2026-04-25 12:00:00'),
(16, 8, 15, '2026-04-10', 5, 'Need the spade for some ground work on my terrace.', 'Rejected', NULL, NULL, '2026-04-10 09:00:00'),
(17, 14, 4, '2026-04-12', 3, 'Want to use the pressure washer to clean my car.', 'Rejected', NULL, NULL, '2026-04-12 10:00:00'),
(18, 17, 20, '2026-04-15', 4, 'Need the heavy drill for concrete anchors in my office.', 'Rejected', NULL, NULL, '2026-04-15 11:00:00'),
(19, 7, 22, '2026-04-26', 7, 'Plz', 'Cancelled', NULL, NULL, '2026-04-26 23:20:58'),
(20, 7, 22, '2026-04-26', 7, '', 'Approved', NULL, '2026-04-26 23:22:32', '2026-04-26 23:21:17'),
(21, 9, 8, '2026-04-26', 8, '', 'Approved', NULL, '2026-04-26 23:49:21', '2026-04-26 23:48:47'),
(22, 9, 8, '2026-04-26', 7, '', 'Approved', NULL, '2026-04-26 23:53:57', '2026-04-26 23:53:41'),
(23, 8, 2, '2026-04-26', 7, '', 'Pending', NULL, NULL, '2026-04-27 00:31:45');
 
INSERT INTO `loan_record` (`loan_id`, `tool_id`, `borrower_id`, `req_id`, `loan_date`, `expected_return`, `status`) VALUES
(1, 3, 8, 1, '2025-10-01', '2025-10-08', 'Returned'),
(2, 7, 14, 2, '2025-10-05', '2025-10-12', 'Returned'),
(3, 12, 6, 3, '2025-10-10', '2025-10-20', 'Returned'),
(4, 1, 17, 4, '2025-11-01', '2025-11-08', 'Returned'),
(5, 5, 11, 5, '2025-11-15', '2025-11-22', 'Returned'),
(6, 9, 20, 6, '2025-12-01', '2025-12-10', 'Returned'),
(7, 15, 3, 7, '2025-12-05', '2025-12-12', 'Returned'),
(8, 2, 9, 8, '2026-01-10', '2026-01-17', 'Returned'),
(9, 18, 7, NULL, '2026-02-01', '2026-02-08', 'Returned'),
(10, 22, 16, NULL, '2026-02-15', '2026-02-22', 'Returned'),
(11, 4, 18, 9, '2026-03-01', '2026-03-15', 'Lost'),
(12, 10, 21, 10, '2026-03-10', '2026-03-24', 'Lost'),
(13, 19, 12, NULL, '2026-04-01', '2026-04-10', 'Lost'),
(14, 25, 15, NULL, '2026-04-05', '2026-04-15', 'Active'),
(15, 28, 13, NULL, '2026-04-10', '2026-04-20', 'Active'),
(16, 6, 4, 11, '2026-04-20', '2026-04-30', 'Active'),
(17, 11, 19, NULL, '2026-04-22', '2026-05-02', 'Active'),
(18, 20, 5, NULL, '2026-04-23', '2026-05-03', 'Active'),
(19, 7, 22, 20, '2026-04-26', '2026-05-03', 'Returned'),
(20, 9, 8, 21, '2026-04-26', '2026-05-04', 'Returned'),
(21, 9, 8, 22, '2026-04-26', '2026-05-03', 'Returned');
 
INSERT INTO `return_log` (`return_id`, `loan_id`, `actual_return_date`, `condition_on_return`, `notes`) VALUES
(1, 1, '2025-10-07', 'Good', 'Returned a day early. Drill bits all present.'),
(2, 2, '2025-10-14', 'Good', 'Returned 2 days late but called ahead.'),
(3, 3, '2025-10-20', 'Damaged', 'Lid latch snapped off. Interior coating scratched.'),
(4, 4, '2025-11-10', 'Good', 'Returned 2 days late. All bits and accessories present.'),
(5, 5, '2025-11-22', 'Good', 'On time, clean, fully packed.'),
(6, 6, '2025-12-10', 'Fair', 'On time. Blade guard has a small dent.'),
(7, 7, '2025-12-15', 'Damaged', 'Returned 3 days late. Suction hose punctured.'),
(8, 8, '2026-01-17', 'Good', 'Exactly on time. Cutting discs replaced with new ones.'),
(9, 9, '2026-02-10', 'Good', 'Returned 2 days late. Multimeter reads accurately.'),
(10, 10, '2026-02-22', 'Fair', 'On time. Spray cup has paint residue.'),
(11, 19, '2026-04-26', 'Good', ''),
(12, 20, '2026-04-26', 'Good', ''),
(13, 21, '2026-04-26', 'Good', '');
 
INSERT INTO `damage_report` (`damage_id`, `loan_id`, `reported_by`, `description`, `severity`, `reported_at`) VALUES
(1, 3, 6, 'Rice cooker lid latch broken off. Interior non-stick coating scratched in 3 places.', 'Moderate', '2025-10-20 18:00:00'),
(2, 7, 3, 'Vacuum cleaner suction hose has a 2cm hole. Suction power dropped to 50%.', 'Minor', '2025-12-15 20:00:00'),
(3, 9, 7, 'Multimeter display shows occasional flickering on AC mode.', 'Minor', '2026-02-10 17:00:00'),
(4, 10, 16, 'Spray gun nozzle partially blocked with dried latex paint.', 'Minor', '2026-02-22 19:00:00'),
(5, 20, 8, 'Broken', 'Severe', '2026-04-26 23:50:15'),
(6, 21, 8, 'again broken', 'Severe', '2026-04-26 23:54:30');
 
INSERT INTO `review` (`review_id`, `loan_id`, `reviewer_id`, `reviewee_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 8, 4, 5, 'The circular saw was in perfect condition. Owner was responsive.', '2025-10-08 10:00:00'),
(2, 1, 4, 8, 5, 'Abul returned the tool a day early and left it cleaner. Perfect borrower!', '2025-10-08 11:00:00'),
(3, 2, 14, 8, 4, 'Good tool. Owner communicated well throughout.', '2025-10-15 09:00:00'),
(4, 2, 8, 14, 4, 'Returned 2 days late but gave advance notice.', '2025-10-15 10:00:00'),
(5, 3, 6, 13, 3, 'Tool worked fine. Lid broke accidentally — very sorry.', '2025-10-21 09:00:00'),
(6, 3, 13, 6, 2, 'Borrower broke the lid. Would not lend again without deposit.', '2025-10-21 10:00:00'),
(7, 4, 17, 2, 5, 'Bosch drill was flawless. All accessories present.', '2025-11-11 09:00:00'),
(8, 4, 2, 17, 5, 'Sabina returned 2 days late but gave notice and cleaned the chuck.', '2025-11-11 10:00:00'),
(9, 5, 11, 6, 4, 'Tool set was comprehensive and well-organised.', '2025-11-23 09:00:00'),
(10, 5, 6, 11, 5, 'Morsheda returned everything on time in perfect order.', '2025-11-23 10:00:00'),
(11, 6, 20, 10, 4, 'Hedge trimmer was powerful and did the job well.', '2025-12-11 09:00:00'),
(12, 7, 3, 16, 3, 'The vacuum worked but the hose was a bit stiff.', '2025-12-16 09:00:00'),
(13, 7, 16, 3, 2, 'Nasrin returned 3 days late and the hose was punctured.', '2025-12-16 10:00:00'),
(14, 8, 9, 3, 5, 'Angle grinder was well-maintained. Owner sourced replacement discs.', '2026-01-18 09:00:00'),
(15, 8, 3, 9, 4, 'Ruksana handled the tool safely and returned exactly on time.', '2026-01-18 10:00:00'),
(16, 9, 7, 19, 4, 'Multimeter was accurate. Lailufar was accommodating.', '2026-02-11 09:00:00'),
(17, 10, 16, 3, 4, 'Spray gun worked well for most of the job.', '2026-02-23 09:00:00'),
(18, 10, 3, 16, 3, 'Rezaul returned on time but left dried paint in the nozzle.', '2026-02-23 10:00:00'),
(19, 19, 22, 8, 4, '', '2026-04-26 23:23:51'),
(20, 19, 8, 22, 4, '', '2026-04-26 23:47:31'),
(21, 6, 10, 20, 2, '', '2026-04-26 23:49:35'),
(22, 20, 8, 10, 1, '', '2026-04-26 23:50:31'),
(23, 20, 10, 8, 1, '', '2026-04-26 23:51:17');
 
INSERT INTO `tool_condition_log` (`cond_id`, `tool_id`, `previous_condition`, `new_condition`, `changed_at`, `reason`) VALUES
(1, 3, 'New', 'Fair', '2025-08-01 10:00:00', 'Normal wear on grip and sole plate after 4 loans.'),
(2, 7, 'New', 'Good', '2025-09-15 11:00:00', 'First-use wear on hammer face. Fully functional.'),
(3, 10, 'Good', 'Fair', '2025-10-20 09:00:00', 'Outer coating of hose showing cracks from sun exposure.'),
(4, 12, 'Good', 'Needs Inspection', '2025-10-20 18:30:00', 'Lid latch broken. Awaiting admin review after Damage Report #1.'),
(5, 15, 'Good', 'Needs Inspection', '2025-12-15 20:30:00', 'Suction hose punctured. Awaiting admin review after Damage Report #2.'),
(6, 18, 'Good', 'Good', '2026-02-15 10:00:00', 'Admin inspected — loose battery contact resolved. Cleared.'),
(7, 22, 'Good', 'Good', '2026-03-01 11:00:00', 'Admin soaked nozzle overnight. Fully clear.'),
(8, 19, 'Good', 'Fair', '2026-01-10 09:00:00', 'Outer jacket of extension cord cracking from age. Electrically safe.');






DELIMITER $$
 
CREATE TRIGGER `after_loan_created` AFTER INSERT ON `loan_record` FOR EACH ROW BEGIN
    UPDATE Tool SET status = 'On Loan' WHERE tool_id = NEW.tool_id;
    UPDATE Member SET total_loans = total_loans + 1 WHERE member_id = NEW.borrower_id;
END$$
 
CREATE TRIGGER `after_loan_record_insert` AFTER INSERT ON `loan_record` FOR EACH ROW BEGIN
    UPDATE Borrow_Request
    SET status = 'Approved', decision_at = NOW()
    WHERE tool_id = NEW.tool_id
      AND requester_id = NEW.borrower_id
      AND status = 'Pending';
END$$
 
CREATE TRIGGER `after_return_log` AFTER INSERT ON `return_log` FOR EACH ROW BEGIN
    DECLARE v_tool_id    INT;
    DECLARE v_has_damage INT DEFAULT 0;
    SELECT tool_id INTO v_tool_id FROM Loan_Record WHERE loan_id = NEW.loan_id;
    SELECT COUNT(*) INTO v_has_damage FROM Damage_Report WHERE loan_id = NEW.loan_id;
    IF NEW.condition_on_return = 'Damaged' OR v_has_damage > 0 THEN
        UPDATE Tool SET status = 'Needs Inspection' WHERE tool_id = v_tool_id;
    ELSE
        UPDATE Tool SET status = 'Available' WHERE tool_id = v_tool_id;
    END IF;
    UPDATE Loan_Record SET status = 'Returned' WHERE loan_id = NEW.loan_id;
END$$
 
CREATE TRIGGER `after_damage_report` AFTER INSERT ON `damage_report` FOR EACH ROW BEGIN
    UPDATE Tool SET status = 'Needs Inspection'
    WHERE tool_id = (SELECT tool_id FROM Loan_Record WHERE loan_id = NEW.loan_id);
END$$
 
CREATE TRIGGER `after_tool_condition_update` AFTER UPDATE ON `tool` FOR EACH ROW BEGIN
    IF OLD.`condition` <> NEW.`condition` THEN
        INSERT INTO Tool_Condition_Log (tool_id, previous_condition, new_condition, reason)
        VALUES (NEW.tool_id, OLD.`condition`, NEW.`condition`,
                CONCAT('Status: ', OLD.status, ' -> ', NEW.status));
    END IF;
END$$
 
CREATE TRIGGER `after_review_insert` AFTER INSERT ON `review` FOR EACH ROW BEGIN
    UPDATE Member
    SET avg_rating = (
        SELECT ROUND(AVG(rating), 2)
        FROM Review WHERE reviewee_id = NEW.reviewee_id
    )
    WHERE member_id = NEW.reviewee_id;
END$$
 
DELIMITER ;
