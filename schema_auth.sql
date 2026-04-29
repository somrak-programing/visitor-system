USE `visitor_system`;

CREATE TABLE IF NOT EXISTS `tb_users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Sales', 'PM', 'MD', 'Admin', 'Security') NOT NULL,
  `email` VARCHAR(150) NULL,
  `name` VARCHAR(100) NOT NULL
);

INSERT IGNORE INTO `tb_users` (`username`, `password`, `role`, `email`, `name`) VALUES
('sales_01', '1234', 'Sales', NULL, 'Nattapong K. (Sales)'),
('pm_01', '1234', 'PM', NULL, 'Somchai Sabai (Plant Manager)'),
('md_01', '1234', 'MD', NULL, 'Managing Director (MD)'),
('admin_01', 'admin', 'Admin', NULL, 'System Administrator');
