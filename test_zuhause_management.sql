-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2026 at 08:58 AM
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
-- Database: `test_zuhause_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `position` varchar(100) NOT NULL,
  `sidebar_source` varchar(20) DEFAULT 'position',
  `branch` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `system_level` varchar(20) NOT NULL DEFAULT 'User',
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deactivated_sidebars` text DEFAULT NULL,
  `sidebar_access` text DEFAULT NULL,
  `account_sidebar_access` text DEFAULT NULL,
  `revert_button_access` varchar(20) DEFAULT 'enabled',
  `transfer_button_access` varchar(20) DEFAULT 'enabled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `first_name`, `last_name`, `position`, `sidebar_source`, `branch`, `password`, `system_level`, `status`, `created_at`, `deactivated_sidebars`, `sidebar_access`, `account_sidebar_access`, `revert_button_access`, `transfer_button_access`) VALUES
(14, 'Superadmin', 'Super', ' Admin', 'Superadmin', 'position', 'ZUHAUSE HEAD OFFICE, ZUHAUSE INFANTA, ZUHAUSE LOPEZ, ZUHAUSE LUCBAN, ZUHAUSE LUCENA, ZUHAUSE MAUBAN, ZUHAUSE TAYABAS', '@02SadminG', 'Super-Admin', 'Activated', '2026-02-16 01:38:41', NULL, '', NULL, 'enabled', 'enabled');

-- --------------------------------------------------------

--
-- Table structure for table `active_sessions`
--

CREATE TABLE `active_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `area_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `area_name`, `status`, `created_at`) VALUES
(6, 'QUEZON', 'Active', '2026-06-29 01:44:26');

-- --------------------------------------------------------

--
-- Table structure for table `booklet_invoice_usage`
--

CREATE TABLE `booklet_invoice_usage` (
  `id` int(11) NOT NULL,
  `booklet_id` int(11) NOT NULL,
  `branch_code` varchar(10) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `used_at` datetime NOT NULL,
  `used_by` varchar(100) NOT NULL,
  `page_type` varchar(50) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `transaction_type` varchar(50) DEFAULT NULL COMMENT 'salesentry, preorder, etc.',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '1=currently in use, 0=transaction completed',
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booklet_numbers`
--

CREATE TABLE `booklet_numbers` (
  `id` int(11) NOT NULL,
  `branch_code` varchar(50) NOT NULL,
  `booklet_no` varchar(50) DEFAULT NULL,
  `beginning_number` varchar(50) DEFAULT NULL,
  `ending_number` varchar(50) DEFAULT NULL,
  `page_type` varchar(50) NOT NULL DEFAULT 'salesentry' COMMENT 'salesentry, preorder, stocktransfer, etc.',
  `booklet_format` varchar(50) NOT NULL COMMENT 'numeric, date_suffix, or custom',
  `current_number` varchar(100) NOT NULL COMMENT 'Current invoice number',
  `prefix` varchar(50) DEFAULT NULL COMMENT 'Optional prefix for invoice number',
  `suffix` varchar(50) DEFAULT NULL COMMENT 'Optional suffix for invoice number',
  `description` text DEFAULT NULL COMMENT 'Optional description or notes',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `last_used_date` datetime DEFAULT NULL,
  `last_used_by` varchar(100) DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `complete_date` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `return_by` varchar(100) DEFAULT NULL,
  `return_branch` varchar(50) DEFAULT NULL,
  `transfer_date` datetime DEFAULT NULL,
  `transfer_by` varchar(100) DEFAULT NULL,
  `transfer_from_branch` varchar(50) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `branch_code` varchar(50) NOT NULL,
  `branch_manager` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `area` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `brand_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `brand_name`, `status`, `created_at`) VALUES
(47, 'TOUGH MAMA', 'Active', '2026-08-14 00:19:55'),
(48, 'VIEWPLUS', 'Active', '2026-08-14 00:21:16'),
(49, 'SHARP', 'Active', '2026-08-14 00:21:39'),
(50, 'EVEREST', 'Active', '2026-08-14 00:22:04'),
(51, 'CONDURA', 'Active', '2026-08-14 00:22:25'),
(52, 'ASTRON', 'Active', '2026-08-14 00:22:36'),
(53, 'WHIRLPOOL', 'Active', '2026-08-14 00:23:21'),
(54, 'TCL', 'Active', '2026-08-14 00:24:17'),
(55, 'FUJIDENZO', 'Active', '2026-08-14 00:24:54'),
(56, 'HIFUTURE', 'Active', '2026-08-14 00:35:20');

-- --------------------------------------------------------

--
-- Table structure for table `claimed_items`
--

CREATE TABLE `claimed_items` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `unclaimed_freebie_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_contact` varchar(100) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `claimed_by` varchar(100) DEFAULT NULL,
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_code` varchar(10) DEFAULT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `status` enum('claimed','void') DEFAULT 'claimed' COMMENT 'Status of claimed item',
  `voided_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when item was voided',
  `voided_by` varchar(100) DEFAULT NULL COMMENT 'User who voided the item',
  `void_reason` text DEFAULT NULL COMMENT 'Reason for voiding'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dealers`
--

CREATE TABLE `dealers` (
  `id` int(11) NOT NULL,
  `dealer_name` varchar(255) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_codes`
--

CREATE TABLE `family_codes` (
  `id` int(11) NOT NULL,
  `family_code` varchar(255) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `family_code` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `srp` decimal(10,2) DEFAULT 0.00,
  `commission` decimal(10,2) DEFAULT 0.00,
  `has_commission` tinyint(1) DEFAULT 0,
  `tc_commission` decimal(10,2) DEFAULT 0.00,
  `points` decimal(10,2) DEFAULT 0.00,
  `has_points` tinyint(1) DEFAULT 0,
  `has_freebies` tinyint(1) DEFAULT 0,
  `has_discount` tinyint(1) DEFAULT 0,
  `has_serial` tinyint(1) DEFAULT 0,
  `has_voucher` tinyint(1) DEFAULT 0,
  `has_others_bank` tinyint(1) DEFAULT 0,
  `voucher_amount` decimal(10,2) DEFAULT 0.00,
  `stock_qty` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `freebies` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_bank_branches`
--

CREATE TABLE `item_bank_branches` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_branch_prices`
--

CREATE TABLE `item_branch_prices` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `srp` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_discount_branches`
--

CREATE TABLE `item_discount_branches` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `discount_editable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_freebies`
--

CREATE TABLE `item_freebies` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `freebie_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_prices`
--

CREATE TABLE `item_prices` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `branch` varchar(100) NOT NULL DEFAULT '',
  `price_type` varchar(50) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `item_serial_branches`
--

CREATE TABLE `item_serial_branches` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `serial_editable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `others_bank`
--

CREATE TABLE `others_bank` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `others_bank`
--

INSERT INTO `others_bank` (`id`, `bank_name`, `status`, `created_at`) VALUES
(2, 'Security Bank', 'Active', '2026-08-13 09:23:52'),
(3, 'Sea Bank', 'Active', '2026-08-13 09:57:23');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `sidebar_access` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `sidebar_access`) VALUES
(5, 'Manager', 'Sales Entry,Stock Transfer,Upgrade Unit,Refund,Claim Item,Purchase Order,Void Sales Report,Upgrade Unit Report,Receive Direct Delivery,Void Sales,Promoter Registration,Dealer Registration,Supplier Registration,Brand Registration,Family Code Registration,Department Registration,Group Registration,Item Registration'),
(7, 'CSC', 'Purchase Order,Payment Details Report,Void Sales Report,Upgrade Unit Report,Refund Report,Transfer Approval,Void Sales,Account Registration,User Activation,Position Registration,Sidebar Per Account,Promoter Registration,Area Registration,Branch Registration,Dealer Registration,Supplier Registration,Brand Registration,Family Code Registration,Department Registration,Group Registration,Item Registration,Bank Registration,Terminal Issuer Registration,Terminal ID Registration'),
(8, 'Superadmin', ''),
(9, 'PROMOTER', '');

-- --------------------------------------------------------

--
-- Table structure for table `preorders`
--

CREATE TABLE `preorders` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `contact_no` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT '',
  `assisted_by` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `total_qty` int(11) NOT NULL DEFAULT 0,
  `discount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_data` text DEFAULT NULL,
  `branch_code` varchar(10) NOT NULL,
  `encoder` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `claimed_invoice_no` varchar(50) DEFAULT NULL COMMENT 'Invoice number when claimed',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `preorder_items`
--

CREATE TABLE `preorder_items` (
  `id` int(11) NOT NULL,
  `preorder_id` int(11) NOT NULL,
  `family_code` varchar(100) DEFAULT NULL COMMENT 'Family code like HONDA CLICK 160',
  `item_description` varchar(255) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_payment` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `dr_number` varchar(100) DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

CREATE TABLE `promos` (
  `id` int(11) NOT NULL,
  `promo_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `discount_type` varchar(100) NOT NULL,
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `motor_model` varchar(255) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `free_item` varchar(255) DEFAULT NULL,
  `branch` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promoters`
--

CREATE TABLE `promoters` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(100) NOT NULL,
  `brand` varchar(255) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promo_items`
--

CREATE TABLE `promo_items` (
  `id` int(11) NOT NULL,
  `promo_id` int(11) NOT NULL,
  `motor_model` varchar(255) DEFAULT '',
  `discount_type` varchar(50) DEFAULT 'Free',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `promo_item` varchar(255) DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_company` varchar(255) DEFAULT NULL,
  `brand_type` varchar(20) DEFAULT 'single',
  `selected_brands` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `terms` varchar(50) DEFAULT NULL,
  `payment_due_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_qty` int(11) DEFAULT 0,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Pending',
  `canceled_by` varchar(150) DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `canceled_by_branch` varchar(50) DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_by` varchar(150) DEFAULT NULL COMMENT 'User who created the purchase order - connects to workflow history',
  `created_by_branch` varchar(10) DEFAULT NULL COMMENT 'Branch code where purchase order was created - connects to workflow history',
  `branch_code` varchar(10) DEFAULT NULL COMMENT 'Branch code where purchase order was created - connects to workflow history',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by` varchar(150) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `received_by_branch` varchar(10) DEFAULT NULL COMMENT 'Branch code where purchase order was received - connects to workflow history',
  `declined_by` varchar(150) DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  `declined_by_branch` varchar(10) DEFAULT NULL COMMENT 'Branch code where purchase order was declined - connects to workflow history',
  `completed_by` varchar(150) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by_branch` varchar(10) DEFAULT NULL,
  `incomplete_by` varchar(150) DEFAULT NULL,
  `incomplete_at` datetime DEFAULT NULL,
  `incomplete_by_branch` varchar(10) DEFAULT NULL,
  `receiving_remarks` text DEFAULT NULL,
  `reason_to_modify` text DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `closed_by` varchar(100) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by_branch` varchar(10) DEFAULT NULL,
  `closed_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_allocations`
--

CREATE TABLE `purchase_order_allocations` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `branch_code` varchar(50) NOT NULL,
  `family_code` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `invoice_number` varchar(100) DEFAULT NULL,
  `receive_date` date DEFAULT NULL,
  `cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'Waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_model` varchar(255) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `serial_number` text DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `receiving_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_allocations`
--

INSERT INTO `purchase_order_allocations` (`id`, `po_id`, `po_number`, `branch_name`, `branch_code`, `family_code`, `quantity`, `received_qty`, `invoice_number`, `receive_date`, `cost`, `status`, `created_at`, `updated_at`, `item_model`, `item_description`, `serial_number`, `received_by`, `received_at`, `receiving_remarks`) VALUES
(71, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'ASTRON ORBIT FAN', 18, 18, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:30:57', '2026-08-14 02:03:18', 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', 'OF18-000953\nOF18-001034\nOF18-001383\nOF18-001685\nOF18-001692\nOF18-003207\nOF18-001831\nOF18-002101\nOF18-003079\nOF18-001900\nOF18-003224\nOF18-003261\nOF18-000545\nOF18-000525\nOF18-000260\nOF18-000241\nVENUS-000346\nVENUS-000763', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(72, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'ASTRON STAND FAN', 12, 12, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:31:08', '2026-08-14 02:03:18', 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', 'SF16-000412\nSF16-002851\nSF16-002849\nSF16-000009', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(73, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'WHIRLPOOL WASH MACHINE', 3, 3, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:31:15', '2026-08-14 02:03:18', 'WHIRLPOOL-NWDC6503BN-6.5KG-TWINTUB-WM-W-FREE-GS101', 'WHIRLPOOL NWDC6503BN 6.5KG TWINTUB WM W FREE GS101MB', '2544X060319\n2544X060082\n2544X060066', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(74, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'ASTRON RICECOOKER', 3, 3, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:31:22', '2026-08-14 02:03:18', 'ASTRON-GRC-1828-1.8L-RICECOOKER-10CUPS', 'ASTRON GRC 1828 1.8L RICECOOKER 10CUPS', '2318280212891\n2318280212893\nGRC182807409', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(75, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'CONDURA WASHING MACHINE', 2, 2, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:31:27', '2026-08-14 02:03:18', 'CONDURA-CWM8.5TTGT-TWINTUB-WM-GRAY-WHITE', 'CONDURA CWM8.5TTGT TWINTUB WM GRAY WHITE', '200101126100000143\n200101126100000145', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(76, 156, 'PO-2026-001', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'WHIRLPOOL AIR PURIFIER', 1, 1, 'INITIALSTOCKS1', NULL, 0.00, 'Waiting', '2026-08-14 01:31:33', '2026-08-14 02:03:18', 'WHIRLPOOL-AP625W-AIR-PURIFIER-POWER-SHIELD-FLT', 'WHIRLPOOL AP625W AIR PURIFIER POWER SHIELD FLT', '432034001317', 'HOFFICE', '2026-08-14 10:03:18', 'test'),
(77, 157, 'PO-2026-002', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 4, 4, 'initialstockstest2', NULL, 0.00, 'Waiting', '2026-08-14 05:09:44', '2026-08-14 05:12:31', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '041HE639FCCG0695\n041HE639FCCG0693', 'INFANTA', '2026-08-14 13:12:31', NULL),
(78, 157, 'PO-2026-002', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE MINI W SPEAKER', 4, 4, 'initialstockstest2', NULL, 0.00, 'Waiting', '2026-08-14 05:09:47', '2026-08-14 05:12:31', 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-BLK', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER BLK', 'PO235HB548B11PK1534', 'INFANTA', '2026-08-14 13:12:31', NULL),
(79, 157, 'PO-2026-002', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE OUTDOOR W SPEAKER', 2, 2, 'initialstockstest2', NULL, 0.00, 'Waiting', '2026-08-14 05:09:51', '2026-08-14 05:12:31', 'HIFUTURE-GRAVITY-OUTDOOR-WIRELESS-SPEAKER-BLACK', 'HIFUTURE GRAVITY OUTDOOR WIRELESS SPEAKER BLACK', 'PO184HB443B4BL0351', 'INFANTA', '2026-08-14 13:12:31', NULL),
(80, 157, 'PO-2026-002', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE PORTABLE W SPEAKER', 3, 3, 'initialstockstest2', NULL, 0.00, 'Waiting', '2026-08-14 05:09:57', '2026-08-14 05:12:31', 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-BLACK', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER BLACK', 'PO0194HB447B3RD0681', 'INFANTA', '2026-08-14 13:12:31', NULL),
(81, 158, 'PO-2026-003', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'ASTRON STAND FAN', 15, 15, 'test', NULL, 0.00, 'Waiting', '2026-08-14 06:01:20', '2026-08-14 06:04:05', 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', 'TESTHO12360\nTESTHO1223\nTESTHO1253', 'HOFFICE', '2026-08-14 14:04:05', ''),
(82, 159, 'PO-2026-004', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, 'test123123', NULL, 0.00, 'Waiting', '2026-08-14 10:18:36', '2026-08-17 03:38:53', NULL, NULL, '123123123', 'INFANTA', '2026-08-17 11:38:53', NULL),
(83, 159, 'PO-2026-004', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'HIFUTURE EARPHONES', 1, 1, '1251252525', NULL, 0.00, 'Waiting', '2026-08-14 10:18:52', '2026-08-17 03:39:43', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '12512512525', 'HOFFICE', '2026-08-17 11:39:43', NULL),
(84, 160, 'PO-2026-005', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, 'tesssst', NULL, 0.00, 'Waiting', '2026-08-14 10:24:00', '2026-08-17 03:38:40', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '123f12312312', 'INFANTA', '2026-08-17 11:38:40', NULL),
(85, 160, 'PO-2026-005', 'ZUHAUSE HEAD OFFICE', 'ZUHO', 'HIFUTURE EARPHONES', 1, 1, 'tessst', NULL, 0.00, 'Waiting', '2026-08-14 10:24:07', '2026-08-17 03:39:33', NULL, NULL, '1252525', 'HOFFICE', '2026-08-17 11:39:33', NULL),
(86, 161, 'PO-2026-006', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 16, 16, 'tesstt', NULL, 0.00, 'Waiting', '2026-08-17 07:54:00', '2026-08-17 07:57:19', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', 'I1D2H3DOI12H3D1\n3DH1O2I3HD12I3DH1\nD3H12ID3H12ODI3H1\nDH31O2IDH31OID23H1\nHD231POI2DH31OD23\nH123PDOI1H23DOI1H2D\n12DP3I1HD2OI3HD1D\n3D1P2I3HD12PO3DH12\nD3H12D3HD12I3HD1\nHI3D12O3IHD1X3OIH123HIXD12I3HXD1\nHX3D12DX3IH12I3HXD123H1\n2D3H12DH31XD23HI1\nD23H1D2I3HD123HDX\n123HD1\nH3D123HD1231\n1251251251252', 'USERINF', '2026-08-17 15:57:19', NULL),
(98, 165, 'PO-2026-010', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, 'tessst', NULL, 0.00, 'Waiting', '2026-08-17 10:10:58', '2026-08-17 10:12:10', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', 'testtttt1111111', 'USERINF', '2026-08-17 18:12:10', ''),
(99, 165, '', 'ZUHAUSE INFANTA', '', 'HIFUTURE PORTABLE W SPEAKER', 1, 1, 'tessst', NULL, 0.00, 'Waiting', '2026-08-17 10:11:19', '2026-08-17 10:12:10', 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', 'tesssssstt22222', 'USERINF', '2026-08-17 18:12:10', NULL),
(116, 176, 'PO-2026-021', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, '1231', NULL, 0.00, 'Waiting', '2026-08-17 12:49:34', '2026-08-17 13:08:07', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '5136521', 'USERINF', '2026-08-17 21:08:07', NULL),
(117, 177, 'PO-2026-022', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE MINI W SPEAKER', 1, 1, 'tessst', NULL, 0.00, 'Waiting', '2026-08-17 21:38:07', '2026-08-18 00:19:22', 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-BLK', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER BLK', '12412412412', 'USERINF', '2026-08-18 08:19:22', ''),
(118, 178, 'PO-2026-023', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, 'tessst', NULL, 0.00, 'Waiting', '2026-08-18 05:43:27', '2026-08-18 05:44:18', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '1111111111111', 'USERINF', '2026-08-18 13:44:18', NULL),
(119, 179, 'PO-2026-024', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 1, 'tesst', NULL, 0.00, 'Waiting', '2026-08-18 05:48:32', '2026-08-18 06:44:39', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', '12313123134', 'USERINF', '2026-08-18 14:44:39', NULL),
(120, 180, 'PO-2026-025', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 0, NULL, NULL, 0.00, 'Waiting', '2026-08-18 05:53:07', '2026-08-18 05:56:11', 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'testtt'),
(121, 181, 'PO-2026-026', 'ZUHAUSE INFANTA', 'ZUHINFA', 'HIFUTURE EARPHONES', 1, 0, NULL, NULL, 0.00, 'Waiting', '2026-08-18 05:55:33', '2026-08-18 05:55:48', NULL, NULL, NULL, NULL, NULL, '123123123123');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_edit_history`
--

CREATE TABLE `purchase_order_edit_history` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `edit_reason` text NOT NULL,
  `edited_by` varchar(150) NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `item_no` int(11) DEFAULT NULL,
  `family_code` varchar(100) DEFAULT NULL,
  `item_model` varchar(255) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `allocated_quantity` int(11) DEFAULT 0,
  `cost` decimal(12,2) DEFAULT 0.00,
  `total` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_qty` int(11) DEFAULT NULL,
  `is_receive_added` tinyint(1) DEFAULT 0,
  `receiving_branch` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rddeliveries`
--

CREATE TABLE `rddeliveries` (
  `id` int(11) NOT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `inventory_sites` varchar(100) DEFAULT NULL,
  `branch_code` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rddelivery_items`
--

CREATE TABLE `rddelivery_items` (
  `id` int(11) NOT NULL,
  `rddelivery_id` int(11) DEFAULT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `received_quantity` int(11) DEFAULT NULL,
  `serials` text DEFAULT NULL,
  `locked` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receive_dd`
--

CREATE TABLE `receive_dd` (
  `po_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `date_receive` date NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `invoice_date` date NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `imei` varchar(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `branch` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `original_sales_id` int(11) DEFAULT NULL,
  `refund_date` date DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_qty` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `branch_code` varchar(10) DEFAULT NULL,
  `encoder` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refund_items`
--

CREATE TABLE `refund_items` (
  `id` int(11) NOT NULL,
  `refund_id` int(11) DEFAULT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `sale_date` date NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `assisted_by` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `total_qty` int(11) DEFAULT 0,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `points` decimal(10,2) DEFAULT 0.00,
  `commission` decimal(10,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_entry`
--

CREATE TABLE `sales_entry` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `assisted_by` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `reason_to_modify` text DEFAULT NULL,
  `total_qty` int(11) DEFAULT 0,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `points` decimal(10,2) DEFAULT 0.00,
  `commission` decimal(10,2) DEFAULT 0.00,
  `payment_data` text DEFAULT NULL,
  `freebie_status` varchar(50) DEFAULT NULL,
  `branch_code` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `late_created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `encoder` varchar(150) DEFAULT NULL COMMENT 'User who encoded the transaction',
  `cash_payments` decimal(12,2) DEFAULT 0.00 COMMENT 'Cash amount received',
  `card_bank_type` varchar(100) DEFAULT NULL COMMENT 'Card or bank type used for payment',
  `voucher_number` varchar(100) DEFAULT NULL,
  `voucher_amount` decimal(10,2) DEFAULT 0.00,
  `commission_encoder` decimal(12,2) DEFAULT 0.00 COMMENT 'Commission for the encoder',
  `status` enum('pending','completed','voided','cancelled') DEFAULT 'completed' COMMENT 'Sales entry status',
  `upgrade` varchar(10) DEFAULT NULL,
  `original_invoice_no` varchar(50) DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_entry_freebies`
--

CREATE TABLE `sales_entry_freebies` (
  `id` int(11) NOT NULL,
  `sales_entry_id` int(11) NOT NULL,
  `freebie_description` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_entry_items`
--

CREATE TABLE `sales_entry_items` (
  `id` int(11) NOT NULL,
  `sales_entry_id` int(11) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `dr_number` varchar(100) DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_freebies`
--

CREATE TABLE `sales_freebies` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `freebie_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_items`
--

CREATE TABLE `sales_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sidebar_restrictions`
--

CREATE TABLE `sidebar_restrictions` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `restricted_sidebar` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skip_receipt_requests`
--

CREATE TABLE `skip_receipt_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(50) NOT NULL,
  `invoice_no` varchar(100) NOT NULL,
  `branch_code` varchar(10) NOT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `requested_by` varchar(255) NOT NULL,
  `requested_by_user` varchar(255) DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Reverted') DEFAULT 'Pending',
  `approved_by` varchar(255) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `rejected_by` varchar(255) DEFAULT NULL,
  `rejected_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reverted_by` varchar(255) DEFAULT NULL,
  `reverted_date` datetime DEFAULT NULL,
  `revert_reason` text DEFAULT NULL,
  `is_reverted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_on_hand`
--

CREATE TABLE `stock_on_hand` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `family_code` varchar(100) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `branch` varchar(100) NOT NULL,
  `dr_date` date DEFAULT NULL COMMENT 'Date item was delivered/received at branch',
  `dr_number` varchar(100) DEFAULT NULL COMMENT 'DR/PO number',
  `system_entry_date` date NOT NULL COMMENT 'Date item was first entered into the system (for IOU calculation)',
  `item_type` varchar(50) NOT NULL DEFAULT 'Unit' COMMENT 'Unit, Accessories, IMEI',
  `status` varchar(30) NOT NULL DEFAULT 'Available' COMMENT 'Available, Reserved, Sold',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stock on Hand tracking table';

--
-- Dumping data for table `stock_on_hand`
--

INSERT INTO `stock_on_hand` (`id`, `item_code`, `description`, `group_name`, `department`, `brand`, `family_code`, `imei`, `quantity`, `branch`, `dr_date`, `dr_number`, `system_entry_date`, `item_type`, `status`, `created_at`, `updated_at`) VALUES
(457, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-000953', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(458, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001034', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(459, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001383', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(460, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001685', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(461, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001692', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(462, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-003207', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(463, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001831', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(464, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-002101', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(465, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-003079', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(466, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-001900', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(467, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-003224', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(468, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-003261', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(469, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-000545', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(470, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-000525', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(471, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-000260', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(472, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'OF18-000241', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(473, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'VENUS-000346', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(474, 'ASTRON-VENUS-IOF-1853-IDL-ORBIT-FAN', 'ASTRON VENUS IOF 1853 IDL ORBIT FAN', NULL, NULL, NULL, 'ASTRON ORBIT FAN', 'VENUS-000763', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(477, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-002352', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(478, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-002810', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(479, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-002831', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(480, 'ASTRON-LION-SF-1631-STANDFAN-16', 'ASTRON LION SF 1631 STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', '000728', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(481, 'ASTRON-RUSH-16-STANDFAN', 'ASTRON RUSH 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', '002311', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(482, 'ASTRON-CONDOR-SF-0605-16-STANDFAN', 'ASTRON CONDOR SF 0605 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', '003364', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(483, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-000412', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(484, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-002851', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(485, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-002849', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(486, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'SF16-000009', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(487, 'WHIRLPOOL-NWDC6503BN-6.5KG-TWINTUB-WM-W-FREE-GS101', 'WHIRLPOOL NWDC6503BN 6.5KG TWINTUB WM W FREE GS101MB', NULL, NULL, NULL, 'WHIRLPOOL WASH MACHINE', '2544X060319', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(488, 'WHIRLPOOL-NWDC6503BN-6.5KG-TWINTUB-WM-W-FREE-GS101', 'WHIRLPOOL NWDC6503BN 6.5KG TWINTUB WM W FREE GS101MB', NULL, NULL, NULL, 'WHIRLPOOL WASH MACHINE', '2544X060082', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(489, 'WHIRLPOOL-NWDC6503BN-6.5KG-TWINTUB-WM-W-FREE-GS101', 'WHIRLPOOL NWDC6503BN 6.5KG TWINTUB WM W FREE GS101MB', NULL, NULL, NULL, 'WHIRLPOOL WASH MACHINE', '2544X060066', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(490, 'WHIRLPOOL-AP625W-AIR-PURIFIER-POWER-SHIELD-FLT', 'WHIRLPOOL AP625W AIR PURIFIER POWER SHIELD FLT', NULL, NULL, NULL, 'WHIRLPOOL AIR PURIFIER', '432034001317', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(491, 'CONDURA-CWM8.5TTGT-TWINTUB-WM-GRAY-WHITE', 'CONDURA CWM8.5TTGT TWINTUB WM GRAY WHITE', NULL, NULL, NULL, 'CONDURA WASHING MACHINE', '200101126100000143', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(492, 'CONDURA-CWM8.5TTGT-TWINTUB-WM-GRAY-WHITE', 'CONDURA CWM8.5TTGT TWINTUB WM GRAY WHITE', NULL, NULL, NULL, 'CONDURA WASHING MACHINE', '200101126100000145', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(493, 'ASTRON-GRC-1828-1.8L-RICECOOKER-10CUPS', 'ASTRON GRC 1828 1.8L RICECOOKER 10CUPS', NULL, NULL, NULL, 'ASTRON RICECOOKER', '2318280212891', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(494, 'ASTRON-GRC-1828-1.8L-RICECOOKER-10CUPS', 'ASTRON GRC 1828 1.8L RICECOOKER 10CUPS', NULL, NULL, NULL, 'ASTRON RICECOOKER', '2318280212893', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(495, 'ASTRON-GRC-1828-1.8L-RICECOOKER-10CUPS', 'ASTRON GRC 1828 1.8L RICECOOKER 10CUPS', NULL, NULL, NULL, 'ASTRON RICECOOKER', 'GRC182807409', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-001', '2026-08-14', 'IMEI', 'Active', '2026-08-14 02:03:18', '2026-08-14 02:03:18'),
(502, 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-BLK', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER BLK', NULL, NULL, NULL, 'HIFUTURE MINI W SPEAKER', 'PO234HB548B11BK2821', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(503, 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-BLUE', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER BLUE', NULL, NULL, NULL, 'HIFUTURE MINI W SPEAKER', 'PO234HB548B11BL0392', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(504, 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-GREY', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER GREY', NULL, NULL, NULL, 'HIFUTURE MINI W SPEAKER', 'PO234HB548B11GR0977', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(505, 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-PINK', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER PINK', NULL, NULL, NULL, 'HIFUTURE MINI W SPEAKER', 'PO235HB548B11PK1534', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(506, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-BLACK', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER BLACK', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', 'PO202HB538B3BK1797', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(507, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-BLUE', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER BLUE', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', 'PO206HB540B3BL0873', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(508, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', 'PO0194HB447B3RD0681', 1, 'ZUHAUSE INFANTA', '2026-08-14', 'PO-2026-002', '2026-08-14', 'IMEI', 'Active', '2026-08-14 05:12:31', '2026-08-14 05:12:31'),
(509, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1212', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(510, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1215', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(511, 'ASTRON-SF-1635-JAGUAR-STANDFAN-16', 'ASTRON SF 1635 JAGUAR STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1218', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(512, 'ASTRON-LION-SF-1631-STANDFAN-16', 'ASTRON LION SF 1631 STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1234', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(513, 'ASTRON-LION-SF-1631-STANDFAN-16', 'ASTRON LION SF 1631 STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1235', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(514, 'ASTRON-LION-SF-1631-STANDFAN-16', 'ASTRON LION SF 1631 STANDFAN 16', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1236', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(517, 'ASTRON-RUSH-16-STANDFAN', 'ASTRON RUSH 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1239', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(521, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO12360', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(522, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1223', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(523, 'ASTRON-OLYMPUS-16-STANDFAN', 'ASTRON OLYMPUS 16 STANDFAN', NULL, NULL, NULL, 'ASTRON STAND FAN', 'TESTHO1253', 1, 'ZUHAUSE HEAD OFFICE', '2026-08-14', 'PO-2026-003', '2026-08-14', 'IMEI', 'Active', '2026-08-14 06:04:05', '2026-08-14 06:04:05'),
(546, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'H123PDOI1H23DOI1H2D', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(547, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '12DP3I1HD2OI3HD1D', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(548, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '3D1P2I3HD12PO3DH12', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(549, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'D3H12D3HD12I3HD1', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(550, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'HI3D12O3IHD1X3OIH123HIXD12I3HXD1', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(551, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'HX3D12DX3IH12I3HXD123H1', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(552, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '2D3H12DH31XD23HI1', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(553, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'D23H1D2I3HD123HDX', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(554, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '123HD1', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(555, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'H3D123HD1231', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:55:17', '2026-08-17 07:55:17'),
(556, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '1251251251252', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-006', '2026-08-17', 'IMEI', 'Active', '2026-08-17 07:57:19', '2026-08-17 07:57:19'),
(566, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-GOLD', 'HIFUTURE FLEXCLIP OPEN EARPHONE GOLD', NULL, NULL, NULL, 'HIFUTURE EARPHONES', 'testtttt1111111', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-010', '2026-08-17', 'IMEI', 'Active', '2026-08-17 10:12:10', '2026-08-17 10:12:10'),
(567, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', 'tesssssstt22222', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-010', '2026-08-17', 'IMEI', 'Active', '2026-08-17 10:12:10', '2026-08-17 10:12:10'),
(568, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '5136521', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-021', '2026-08-17', 'IMEI', 'Active', '2026-08-17 13:08:07', '2026-08-17 13:08:07'),
(569, 'CONDURA-CWM8.5TTGT-TWINTUB-WM-GRAY-WHITE', 'CONDURA CWM8.5TTGT TWINTUB WM GRAY WHITE', NULL, NULL, NULL, 'CONDURA WASHING MACHINE', '5454', 1, 'ZUHAUSE INFANTA', '2026-08-17', 'PO-2026-021', '2026-08-17', 'IMEI', 'Active', '2026-08-17 13:08:07', '2026-08-17 13:08:07'),
(570, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '124124CD123', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-022', '2026-08-18', 'IMEI', 'Active', '2026-08-18 00:19:22', '2026-08-18 05:25:00'),
(571, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '12412412412125125', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-022', '2026-08-18', 'IMEI', 'Active', '2026-08-18 00:19:22', '2026-08-18 00:19:22'),
(572, 'HIFUTURE-POCKET-S-MINI-WIRELESS-SPEAKER-BLK', 'HIFUTURE POCKET S MINI WIRELESS SPEAKER BLK', NULL, NULL, NULL, 'HIFUTURE MINI W SPEAKER', '12412412412', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-022', '2026-08-18', 'IMEI', 'Active', '2026-08-18 00:19:22', '2026-08-18 00:19:22'),
(584, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '1111111111111', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-023', '2026-08-18', 'IMEI', 'Active', '2026-08-18 05:44:18', '2026-08-18 05:44:18'),
(585, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', '222222222222223', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-023', '2026-08-18', 'IMEI', 'Active', '2026-08-18 05:44:18', '2026-08-18 05:45:33'),
(586, 'HIFUTURE-FLEXCLIP-OPEN-EARPHONE-BLACK', 'HIFUTURE FLEXCLIP OPEN EARPHONE BLACK', NULL, NULL, NULL, 'HIFUTURE EARPHONES', '12313123134', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-024', '2026-08-18', 'IMEI', 'Active', '2026-08-18 05:50:17', '2026-08-18 05:50:36'),
(587, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', '122312312312312', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-024', '2026-08-18', 'IMEI', 'Active', '2026-08-18 05:50:17', '2026-08-18 05:50:36'),
(588, 'HIFUTURE-RIPPLE-PORTABLE-WIRELESS-SPEAKER-RED', 'HIFUTURE RIPPLE PORTABLE WIRELESS SPEAKER RED', NULL, NULL, NULL, 'HIFUTURE PORTABLE W SPEAKER', '124124124124214', 1, 'ZUHAUSE INFANTA', '2026-08-18', 'PO-2026-024', '2026-08-18', 'IMEI', 'Active', '2026-08-18 06:44:39', '2026-08-18 06:44:39');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `st_number` varchar(50) NOT NULL,
  `st_date` date NOT NULL,
  `branch_from` varchar(100) DEFAULT NULL,
  `branch_to` varchar(255) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `prepared_by` varchar(100) DEFAULT NULL,
  `approver` varchar(100) DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `disapproved_by` varchar(100) DEFAULT NULL,
  `received_date` datetime DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `total_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `st_number` varchar(50) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `item_description` text DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_ids`
--

CREATE TABLE `terminal_ids` (
  `id` int(11) NOT NULL,
  `terminal_id` varchar(255) NOT NULL,
  `terminal_issuer` varchar(255) NOT NULL,
  `branches` text NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terminal_ids`
--

INSERT INTO `terminal_ids` (`id`, `terminal_id`, `terminal_issuer`, `branches`, `status`, `created_at`) VALUES
(3, '1234567', 'BDO', 'ZUHAUSE HEAD OFFICE, ZUHAUSE INFANTA', 'Active', '2026-08-14 06:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_issuers`
--

CREATE TABLE `terminal_issuers` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terminal_issuers`
--

INSERT INTO `terminal_issuers` (`id`, `bank_name`, `status`, `created_at`) VALUES
(8, 'BDO', 'Active', '2026-08-14 06:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `unclaimed_freebies`
--

CREATE TABLE `unclaimed_freebies` (
  `id` int(11) NOT NULL,
  `sales_entry_id` int(11) NOT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `item_code` varchar(100) NOT NULL,
  `item_description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `note` text DEFAULT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `claimed_at` timestamp NULL DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when item was voided',
  `voided_by` varchar(100) DEFAULT NULL COMMENT 'User who voided the item',
  `void_reason` text DEFAULT NULL COMMENT 'Reason for voiding',
  `status` varchar(50) DEFAULT 'unclaimed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upgrades`
--

CREATE TABLE `upgrades` (
  `id` int(11) NOT NULL,
  `upgrade_no` varchar(50) NOT NULL,
  `original_invoice_no` varchar(50) NOT NULL,
  `new_invoice_no` varchar(50) DEFAULT NULL,
  `reason` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `less_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_data` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upgrade_new_items`
--

CREATE TABLE `upgrade_new_items` (
  `id` int(11) NOT NULL,
  `upgrade_id` int(11) NOT NULL,
  `item_code` varchar(100) DEFAULT NULL,
  `item_description` varchar(255) NOT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `upgrade_old_items`
--

CREATE TABLE `upgrade_old_items` (
  `id` int(11) NOT NULL,
  `upgrade_id` int(11) NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `position` varchar(100) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `active_sessions`
--
ALTER TABLE `active_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`session_id`),
  ADD KEY `user_id_index` (`user_id`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `area_name` (`area_name`);

--
-- Indexes for table `booklet_invoice_usage`
--
ALTER TABLE `booklet_invoice_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_invoice` (`branch_code`,`invoice_number`),
  ADD KEY `idx_booklet` (`booklet_id`),
  ADD KEY `idx_branch` (`branch_code`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_active_branch` (`is_active`,`branch_code`);

--
-- Indexes for table `booklet_numbers`
--
ALTER TABLE `booklet_numbers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_code` (`branch_code`),
  ADD KEY `status` (`status`),
  ADD KEY `page_type` (`page_type`),
  ADD KEY `idx_is_locked` (`is_locked`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brand_name` (`brand_name`);

--
-- Indexes for table `claimed_items`
--
ALTER TABLE `claimed_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_no` (`invoice_no`),
  ADD KEY `idx_unclaimed_freebie` (`unclaimed_freebie_id`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_imei` (`imei`),
  ADD KEY `idx_claimed_at` (`claimed_at`),
  ADD KEY `idx_claimed_by` (`claimed_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `dealers`
--
ALTER TABLE `dealers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `family_codes`
--
ALTER TABLE `family_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `family_code` (`family_code`),
  ADD KEY `brand_id` (`brand_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_name` (`group_name`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`);

--
-- Indexes for table `item_bank_branches`
--
ALTER TABLE `item_bank_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_bank_branch` (`item_id`,`branch_name`);

--
-- Indexes for table `item_branch_prices`
--
ALTER TABLE `item_branch_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_branch_price` (`item_id`,`branch_name`);

--
-- Indexes for table `item_discount_branches`
--
ALTER TABLE `item_discount_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_branch` (`item_id`,`branch_name`);

--
-- Indexes for table `item_freebies`
--
ALTER TABLE `item_freebies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `item_prices`
--
ALTER TABLE `item_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_branch_price` (`item_id`,`branch`,`price_type`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `item_serial_branches`
--
ALTER TABLE `item_serial_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_branch_serial` (`item_id`,`branch_name`);

--
-- Indexes for table `others_bank`
--
ALTER TABLE `others_bank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_name` (`bank_name`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_name` (`position_name`);

--
-- Indexes for table `preorders`
--
ALTER TABLE `preorders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`);

--
-- Indexes for table `preorder_items`
--
ALTER TABLE `preorder_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `preorder_id` (`preorder_id`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_imei` (`imei`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_family_code` (`family_code`);

--
-- Indexes for table `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promoters`
--
ALTER TABLE `promoters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `promo_items`
--
ALTER TABLE `promo_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `promo_id` (`promo_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `idx_branch_code` (`branch_code`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_by_branch` (`created_by_branch`),
  ADD KEY `idx_received_by_branch` (`received_by_branch`),
  ADD KEY `idx_declined_by_branch` (`declined_by_branch`);

--
-- Indexes for table `purchase_order_allocations`
--
ALTER TABLE `purchase_order_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_id` (`po_id`),
  ADD KEY `idx_po_number` (`po_number`),
  ADD KEY `idx_branch_name` (`branch_name`),
  ADD KEY `idx_family_code` (`family_code`);

--
-- Indexes for table `purchase_order_edit_history`
--
ALTER TABLE `purchase_order_edit_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_id` (`po_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`);

--
-- Indexes for table `rddeliveries`
--
ALTER TABLE `rddeliveries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rddelivery_items`
--
ALTER TABLE `rddelivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rddelivery_id` (`rddelivery_id`);

--
-- Indexes for table `receive_dd`
--
ALTER TABLE `receive_dd`
  ADD PRIMARY KEY (`po_number`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_supplier` (`supplier`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_date_receive` (`date_receive`),
  ADD KEY `idx_branch` (`branch`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `refund_items`
--
ALTER TABLE `refund_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`);

--
-- Indexes for table `sales_entry`
--
ALTER TABLE `sales_entry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_branch_code` (`branch_code`);

--
-- Indexes for table `sales_entry_freebies`
--
ALTER TABLE `sales_entry_freebies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_entry_id` (`sales_entry_id`);

--
-- Indexes for table `sales_entry_items`
--
ALTER TABLE `sales_entry_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_entry_id` (`sales_entry_id`),
  ADD KEY `idx_imei` (`imei`);

--
-- Indexes for table `sales_freebies`
--
ALTER TABLE `sales_freebies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `sidebar_restrictions`
--
ALTER TABLE `sidebar_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_restriction` (`account_id`,`restricted_sidebar`);

--
-- Indexes for table `skip_receipt_requests`
--
ALTER TABLE `skip_receipt_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD KEY `idx_invoice` (`invoice_no`),
  ADD KEY `idx_branch` (`branch_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `stock_on_hand`
--
ALTER TABLE `stock_on_hand`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_branch` (`branch`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_group_name` (`group_name`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_brand` (`brand`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `st_number` (`st_number`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_ids`
--
ALTER TABLE `terminal_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `terminal_id` (`terminal_id`);

--
-- Indexes for table `terminal_issuers`
--
ALTER TABLE `terminal_issuers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_name` (`bank_name`);

--
-- Indexes for table `unclaimed_freebies`
--
ALTER TABLE `unclaimed_freebies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sales_entry` (`sales_entry_id`),
  ADD KEY `idx_invoice` (`invoice_number`),
  ADD KEY `idx_item_code` (`item_code`),
  ADD KEY `idx_branch` (`branch`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `upgrades`
--
ALTER TABLE `upgrades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `upgrade_no` (`upgrade_no`),
  ADD KEY `original_invoice_no` (`original_invoice_no`),
  ADD KEY `new_invoice_no` (`new_invoice_no`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `upgrade_new_items`
--
ALTER TABLE `upgrade_new_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `upgrade_id` (`upgrade_id`);

--
-- Indexes for table `upgrade_old_items`
--
ALTER TABLE `upgrade_old_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `upgrade_id` (`upgrade_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `active_sessions`
--
ALTER TABLE `active_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `booklet_invoice_usage`
--
ALTER TABLE `booklet_invoice_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booklet_numbers`
--
ALTER TABLE `booklet_numbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `claimed_items`
--
ALTER TABLE `claimed_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dealers`
--
ALTER TABLE `dealers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `family_codes`
--
ALTER TABLE `family_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_bank_branches`
--
ALTER TABLE `item_bank_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_branch_prices`
--
ALTER TABLE `item_branch_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_discount_branches`
--
ALTER TABLE `item_discount_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_freebies`
--
ALTER TABLE `item_freebies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_prices`
--
ALTER TABLE `item_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item_serial_branches`
--
ALTER TABLE `item_serial_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `others_bank`
--
ALTER TABLE `others_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `preorders`
--
ALTER TABLE `preorders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `preorder_items`
--
ALTER TABLE `preorder_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promoters`
--
ALTER TABLE `promoters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promo_items`
--
ALTER TABLE `promo_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_allocations`
--
ALTER TABLE `purchase_order_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `purchase_order_edit_history`
--
ALTER TABLE `purchase_order_edit_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rddeliveries`
--
ALTER TABLE `rddeliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rddelivery_items`
--
ALTER TABLE `rddelivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refund_items`
--
ALTER TABLE `refund_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_entry`
--
ALTER TABLE `sales_entry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_entry_freebies`
--
ALTER TABLE `sales_entry_freebies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_entry_items`
--
ALTER TABLE `sales_entry_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_freebies`
--
ALTER TABLE `sales_freebies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_items`
--
ALTER TABLE `sales_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sidebar_restrictions`
--
ALTER TABLE `sidebar_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skip_receipt_requests`
--
ALTER TABLE `skip_receipt_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_on_hand`
--
ALTER TABLE `stock_on_hand`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=589;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_ids`
--
ALTER TABLE `terminal_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `terminal_issuers`
--
ALTER TABLE `terminal_issuers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `unclaimed_freebies`
--
ALTER TABLE `unclaimed_freebies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upgrades`
--
ALTER TABLE `upgrades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upgrade_new_items`
--
ALTER TABLE `upgrade_new_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `upgrade_old_items`
--
ALTER TABLE `upgrade_old_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booklet_invoice_usage`
--
ALTER TABLE `booklet_invoice_usage`
  ADD CONSTRAINT `booklet_invoice_usage_ibfk_1` FOREIGN KEY (`booklet_id`) REFERENCES `booklet_numbers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family_codes`
--
ALTER TABLE `family_codes`
  ADD CONSTRAINT `family_codes_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_bank_branches`
--
ALTER TABLE `item_bank_branches`
  ADD CONSTRAINT `item_bank_branches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_branch_prices`
--
ALTER TABLE `item_branch_prices`
  ADD CONSTRAINT `item_branch_prices_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_discount_branches`
--
ALTER TABLE `item_discount_branches`
  ADD CONSTRAINT `item_discount_branches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_freebies`
--
ALTER TABLE `item_freebies`
  ADD CONSTRAINT `item_freebies_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_prices`
--
ALTER TABLE `item_prices`
  ADD CONSTRAINT `item_prices_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_serial_branches`
--
ALTER TABLE `item_serial_branches`
  ADD CONSTRAINT `item_serial_branches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `preorder_items`
--
ALTER TABLE `preorder_items`
  ADD CONSTRAINT `preorder_items_ibfk_1` FOREIGN KEY (`preorder_id`) REFERENCES `preorders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promo_items`
--
ALTER TABLE `promo_items`
  ADD CONSTRAINT `promo_items_ibfk_1` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_order_allocations`
--
ALTER TABLE `purchase_order_allocations`
  ADD CONSTRAINT `purchase_order_allocations_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rddelivery_items`
--
ALTER TABLE `rddelivery_items`
  ADD CONSTRAINT `rddelivery_items_ibfk_1` FOREIGN KEY (`rddelivery_id`) REFERENCES `rddeliveries` (`id`);

--
-- Constraints for table `sales_entry_freebies`
--
ALTER TABLE `sales_entry_freebies`
  ADD CONSTRAINT `fk_sales_entry_freebies` FOREIGN KEY (`sales_entry_id`) REFERENCES `sales_entry` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_entry_items`
--
ALTER TABLE `sales_entry_items`
  ADD CONSTRAINT `fk_sales_entry_items` FOREIGN KEY (`sales_entry_id`) REFERENCES `sales_entry` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_freebies`
--
ALTER TABLE `sales_freebies`
  ADD CONSTRAINT `sales_freebies_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_items`
--
ALTER TABLE `sales_items`
  ADD CONSTRAINT `sales_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sidebar_restrictions`
--
ALTER TABLE `sidebar_restrictions`
  ADD CONSTRAINT `sidebar_restrictions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `upgrade_new_items`
--
ALTER TABLE `upgrade_new_items`
  ADD CONSTRAINT `upgrade_new_items_ibfk_1` FOREIGN KEY (`upgrade_id`) REFERENCES `upgrades` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `upgrade_old_items`
--
ALTER TABLE `upgrade_old_items`
  ADD CONSTRAINT `upgrade_old_items_ibfk_1` FOREIGN KEY (`upgrade_id`) REFERENCES `upgrades` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
