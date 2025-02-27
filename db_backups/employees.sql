-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 22, 2025 at 05:50 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hrms`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `emp_id` varchar(50) NOT NULL,
  `mach_id` varchar(50) DEFAULT NULL,
  `branch` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` varchar(1) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `join_date` date NOT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `office_email` varchar(100) DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `login_access` tinyint(1) DEFAULT 0,
  `user_image` varchar(255) DEFAULT 'resources/userimg/default-image.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `emp_id`, `mach_id`, `branch`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `phone`, `join_date`, `designation`, `office_phone`, `office_email`, `exit_date`, `login_access`, `user_image`) VALUES
(19, '101', '2', '1', 'Sagar', '', 'Khatiwada', 'M', 'sagar.khatiwada@gmail.com', '9863629512', '2025-01-25', 'MIS Manager', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(20, '102', '3', '1', 'Pawan', 'Kumar', 'Karki', 'M', 'pawan.karki@primeexpress.com.np', '9851273944', '2025-01-25', 'Operation Manager', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(21, '103', '4', '1', 'Rama', '', 'GC', 'F', 'rama.gc@primeexpress.com.np', '9800000000', '2025-01-25', 'Office Helper', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(22, '104', '5', '1', 'Dilliram', '', 'Kadariya', 'M', 'dilliram.kadariya@primeexpress.com.np', '9851273953', '2025-01-25', 'Marketing Manager', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(23, '105', '7', '1', 'Bikash', '', 'Karki', 'M', 'bikash.karki@primeexpress.com.np', '9800000000', '2025-01-25', 'Cashier', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(24, '106', '8', '1', 'Parbata', '', 'Niroula', 'M', 'parbata.niroula@primeexpress.com.np', '9851273974', '2025-01-25', 'Account Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(25, '107', '9', '1', 'Ram ', 'Chandra ', 'Baral', 'M', 'ramchandra.baral@primeexpress.com.np', '9851273941', '2025-01-25', 'Account Manager', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(26, '108', '10', '1', 'Samjhana', '', 'Bhandari', 'F', 'samjhana.bhandari@primeexpress.com.np', '9851273969', '2025-01-25', 'Customer Relationship Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(27, '109', '11', '1', 'Nirajan', '', 'Karki', 'M', 'nirajan.karki@primeexpress.com.np', '9800000000', '2025-01-25', 'Delivery Assistant', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(28, '110', '12', '1', 'Mahesh', '', 'Thapa', 'M', 'mahesh.thapa@primeexpress.com.np', '9851273950', '2025-01-25', 'Market Representative', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(29, '111', '13', '1', 'Imesh', 'Raj', 'Bhandari', 'M', 'imeshraj.bhandari@primeexpress.com.np', '9851273969', '2025-01-25', 'Customer Relationship Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(30, '112', '15', '1', 'Krishna', '', 'Rawal', 'M', 'krishna.rawal@primeexpress.com.np', '9851273952', '2025-01-25', 'Driver', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(31, '113', '18', '1', 'Krishna', 'Bahadur', 'Raut', 'M', 'krishna.rawat@primeexpress.com.np', '9851273968', '2025-01-25', 'Delivery Assistant', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(32, '114', '19', '1', 'Tej', '', 'Limbu', 'M', 'tej.limbu@primeexpress.com.np', '9800000000', '2025-01-25', 'Delivery Assistant', NULL, NULL, '2025-02-21', 0, 'resources/userimg/default-image.jpg'),
(33, '115', '20', '1', 'Bhupendra', '', 'Karki', 'M', 'bhupendra.karki@primeexpress.com.np', '9800000000', '2025-01-25', 'Office Helper', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(34, '116', '22', '1', 'Sagar', '', 'Basnet', 'M', 'sagar.basnet@primeexpress.com.np', '9851273980', '2025-01-25', 'Driver', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(35, '117', '24', '1', 'Samjhana', '', 'Dahal', 'F', 'samjhana.dahal@primeexpress.com.np', '9851273976', '2025-01-25', 'Customer Relationship Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(36, '118', '21', '1', 'Durga', 'Prasad', 'Dhimal', 'M', 'durga.dhimal@primeexpress.com.np', '9851412944', '2025-01-25', 'Driver', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(37, '119', '23', '1', 'Sundar', '', 'Giri', 'M', 'sundar.giri@primeexpress.com.np', '9800000000', '2025-01-25', 'Driver', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(38, '601', '', '6', 'Prakash', '', 'Gurung', 'M', 'prakash.gurung@primeexpress.com.np', '9851359976', '2025-01-25', 'Sales Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(39, '602', '', '6', 'Indrakala', '', 'KC', 'F', 'indrakala.kc@primeexpress.com.np', '9851359676', '2025-01-25', 'Sales Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(40, '603', '', '6', 'Sita', '', 'Rijal', 'F', 'sita.rijal@primeexpress.com.np', '9851360947', '2025-01-25', 'Sales Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(42, '501', '', '5', 'Ganga', '', 'Basnet', 'F', 'ganga.basnet@primeexpress.com.np', '9851360941', '2025-01-25', 'Sales Officer', NULL, NULL, NULL, 0, 'resources/userimg/default-image.jpg'),
(44, '502', '', '5', 'Sukra', 'Raj', 'Limbu', 'M', 'sukraraj.limbu@primeexpress.com.np', '9851360942', '2025-01-25', 'Sales Officer', NULL, NULL, NULL, 1, 'resources/userimg/default-image.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
