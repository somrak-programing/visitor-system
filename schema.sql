CREATE DATABASE IF NOT EXISTS `visitor_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `visitor_system`;

-- Table: Main Request Information
CREATE TABLE IF NOT EXISTS `tb_visitor_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `purpose` VARCHAR(100) NOT NULL,
  `tour_date` DATE NOT NULL,
  `routing_course` TEXT,
  `souvenir` VARCHAR(255),
  `status` ENUM('Pending PM', 'Pending MD', 'Final Approved', 'Rejected') DEFAULT 'Pending PM',
  `reject_reason` TEXT DEFAULT NULL,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: External Visitors list
CREATE TABLE IF NOT EXISTS `tb_visitors` (
  `visitor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT,
  `fullname` VARCHAR(255) NOT NULL,
  `job_title` VARCHAR(255),
  FOREIGN KEY (`request_id`) REFERENCES `tb_visitor_requests`(`request_id`) ON DELETE CASCADE
);

-- Table: Agenda & Schedule
CREATE TABLE IF NOT EXISTS `tb_schedules` (
  `schedule_id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `activity` VARCHAR(255) NOT NULL,
  `remark` TEXT,
  FOREIGN KEY (`request_id`) REFERENCES `tb_visitor_requests`(`request_id`) ON DELETE CASCADE
);

-- Table: Internal TCP Members (Hosts)
CREATE TABLE IF NOT EXISTS `tb_tcp_members` (
  `member_id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT,
  `employee_name` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`request_id`) REFERENCES `tb_visitor_requests`(`request_id`) ON DELETE CASCADE
);
