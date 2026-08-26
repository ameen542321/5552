-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: carled
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accountants`
--

DROP TABLE IF EXISTS `accountants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accountants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `store_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'accountant',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `suspension_reason` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accountants_email_unique` (`email`),
  KEY `accountants_user_id_foreign` (`user_id`),
  KEY `accountants_store_id_foreign` (`store_id`),
  CONSTRAINT `fk_accountants_store_id_cascade` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_accountants_user_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `archived_items`
--

DROP TABLE IF EXISTS `archived_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archived_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `store_id` bigint(20) unsigned DEFAULT NULL,
  `archivable_type` varchar(255) NOT NULL,
  `archivable_id` bigint(20) unsigned NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `original_slug` varchar(255) DEFAULT NULL,
  `archived_slug` varchar(255) DEFAULT NULL,
  `reference` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'archived',
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `owner_restore_deadline` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` bigint(20) unsigned DEFAULT NULL,
  `admin_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `archived_items_archivable_type_archivable_id_unique` (`archivable_type`,`archivable_id`),
  UNIQUE KEY `archived_items_reference_unique` (`reference`),
  KEY `archived_items_store_id_foreign` (`store_id`),
  KEY `archived_items_archived_by_foreign` (`archived_by`),
  KEY `archived_items_restored_by_foreign` (`restored_by`),
  KEY `archived_items_owner_id_status_index` (`owner_id`,`status`),
  KEY `archived_items_status_owner_restore_deadline_index` (`status`,`owner_restore_deadline`),
  CONSTRAINT `archived_items_archived_by_foreign` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `archived_items_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `archived_items_restored_by_foreign` FOREIGN KEY (`restored_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `archived_items_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_main_category` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_store_slug_unique` (`store_id`,`slug`),
  CONSTRAINT `fk_categories_store_cascade` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `credit_sales`
--

DROP TABLE IF EXISTS `credit_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credit_sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) DEFAULT NULL,
  `store_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `partial_payments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`partial_payments`)),
  `description` varchar(255) DEFAULT NULL,
  `credit_note` text DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('pending','deducted') NOT NULL DEFAULT 'pending',
  `month` varchar(255) NOT NULL,
  `deducted_month` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_credit_sales_employee_id_foreign` (`person_id`),
  KEY `employee_credit_sales_store_id_foreign` (`store_id`),
  KEY `credit_sales_sale_id_index` (`sale_id`),
  CONSTRAINT `employee_credit_sales_employee_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_credit_sales_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_balances`
--

DROP TABLE IF EXISTS `daily_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `accountant_id` bigint(20) unsigned NOT NULL,
  `system_sales_total` decimal(15,2) NOT NULL,
  `system_cash_expected` decimal(15,2) NOT NULL,
  `actual_cash_submitted` decimal(15,2) NOT NULL,
  `difference` decimal(15,2) NOT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `next_shift_business_date` date DEFAULT NULL,
  `next_shift_decision` varchar(40) DEFAULT NULL,
  `next_shift_decided_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_balances_store_id_foreign` (`store_id`),
  KEY `daily_balances_accountant_id_foreign` (`accountant_id`),
  KEY `daily_balances_business_date_index` (`business_date`),
  KEY `daily_balances_closed_at_index` (`closed_at`),
  KEY `daily_balances_next_shift_decided_by_foreign` (`next_shift_decided_by`),
  KEY `daily_balances_next_shift_business_date_index` (`next_shift_business_date`),
  CONSTRAINT `daily_balances_accountant_id_foreign` FOREIGN KEY (`accountant_id`) REFERENCES `accountants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_balances_next_shift_decided_by_foreign` FOREIGN KEY (`next_shift_decided_by`) REFERENCES `accountants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `daily_balances_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=510 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `debts`
--

DROP TABLE IF EXISTS `debts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `debts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `debt_parent_id` bigint(20) unsigned DEFAULT NULL,
  `person_type` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_method_label` varchar(255) DEFAULT NULL,
  `cash_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `card_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date` date DEFAULT NULL,
  `type` enum('normal') NOT NULL DEFAULT 'normal',
  `status` enum('pending','deducted') NOT NULL DEFAULT 'pending',
  `month` varchar(255) NOT NULL,
  `deducted_month` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_debts_employee_id_foreign` (`person_id`),
  KEY `employee_debts_parent_id_index` (`debt_parent_id`),
  KEY `debts_store_id_foreign` (`store_id`),
  CONSTRAINT `debts_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_debts_employee_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `device_tokens`
--

DROP TABLE IF EXISTS `device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `device_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `accountant_id` bigint(20) unsigned DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_absences`
--

DROP TABLE IF EXISTS `employee_absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_absences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','deducted') NOT NULL DEFAULT 'pending',
  `month` varchar(255) NOT NULL,
  `deducted_month` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_absences_employee_id_foreign` (`person_id`),
  KEY `employee_absences_store_id_foreign` (`store_id`),
  CONSTRAINT `employee_absences_employee_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_absences_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_credit_collections`
--

DROP TABLE IF EXISTS `employee_credit_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_credit_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `credit_sale_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `store_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `payment_method_label` varchar(50) DEFAULT NULL,
  `cash_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `card_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `collection_date` date NOT NULL,
  `collected_by` bigint(20) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credit_collections_credit_date_index` (`credit_sale_id`,`collection_date`),
  KEY `credit_collections_store_date_index` (`store_id`,`collection_date`),
  KEY `credit_collections_person_index` (`person_id`,`person_type`),
  KEY `credit_collections_sale_id_index` (`sale_id`),
  CONSTRAINT `employee_credit_collections_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_logs`
--

DROP TABLE IF EXISTS `employee_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) NOT NULL,
  `store_id` bigint(20) unsigned NOT NULL,
  `action_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_logs_store_id_foreign` (`store_id`),
  CONSTRAINT `employee_logs_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_salary_reports`
--

DROP TABLE IF EXISTS `employee_salary_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_salary_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) DEFAULT NULL,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `month` varchar(255) NOT NULL,
  `year` varchar(255) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_withdrawals` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_absences` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_normal_debts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_credit_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
  `previous_debts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bonus` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extra_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_salary_reports_employee_id_foreign` (`person_id`),
  KEY `employee_salary_reports_store_id_foreign` (`store_id`),
  KEY `employee_salary_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `employee_salary_reports_employee_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_salary_reports_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_salary_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_withdrawals`
--

DROP TABLE IF EXISTS `employee_withdrawals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_withdrawals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `person_type` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('pending','deducted') NOT NULL DEFAULT 'pending',
  `month` varchar(255) NOT NULL,
  `deducted_month` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `daily_balance_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_withdrawals_employee_id_foreign` (`person_id`),
  KEY `employee_withdrawals_daily_balance_id_foreign` (`daily_balance_id`),
  KEY `employee_withdrawals_business_date_index` (`business_date`),
  KEY `employee_withdrawals_store_id_foreign` (`store_id`),
  CONSTRAINT `employee_withdrawals_daily_balance_id_foreign` FOREIGN KEY (`daily_balance_id`) REFERENCES `daily_balances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_withdrawals_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `store_id` bigint(20) unsigned NOT NULL,
  `role` enum('employee','accountant') NOT NULL DEFAULT 'employee',
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `added_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employees_store_id_foreign` (`store_id`),
  KEY `employees_added_by_foreign` (`added_by`),
  CONSTRAINT `employees_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employees_store_id_cascade` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `daily_balance_id` bigint(20) unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_employee_id_foreign` (`employee_id`),
  KEY `expenses_daily_balance_id_foreign` (`daily_balance_id`),
  KEY `expenses_business_date_index` (`business_date`),
  CONSTRAINT `expenses_daily_balance_id_foreign` FOREIGN KEY (`daily_balance_id`) REFERENCES `daily_balances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `quantity_change` int(11) NOT NULL,
  `quantity_snapshot` decimal(15,4) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `business_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_logs_business_date_index` (`business_date`),
  KEY `inventory_logs_store_id_foreign` (`store_id`),
  CONSTRAINT `inventory_logs_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(255) DEFAULT NULL,
  `vehicle_type` varchar(255) DEFAULT NULL,
  `plate_number` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'printed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_sale_id_foreign` (`sale_id`),
  CONSTRAINT `invoices_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=1273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(255) DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `model_type` varchar(255) DEFAULT NULL,
  `model_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `logs_user_id_foreign` (`user_id`),
  KEY `logs_store_id_foreign` (`store_id`),
  CONSTRAINT `fk_logs_user_id_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `logs_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1340 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `sender_type` enum('admin','user','accountant','system','CARLED') NOT NULL,
  `target_type` enum('all','users','accountants','stores','store','user','mixed') NOT NULL,
  `target_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_ids`)),
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `template_key` varchar(255) DEFAULT NULL,
  `channel` enum('site','push','both','CARLED') NOT NULL,
  `read_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`read_by`)),
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=395 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `onesignal_settings`
--

DROP TABLE IF EXISTS `onesignal_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `onesignal_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `allowed_stores` int(11) NOT NULL,
  `allowed_accountants` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `product_fractions`
--

DROP TABLE IF EXISTS `product_fractions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_fractions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `option_label` varchar(255) NOT NULL,
  `deduction_value` decimal(10,2) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_fractions_product_id_foreign` (`product_id`),
  CONSTRAINT `product_fractions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `product_type` enum('standard','fractional') NOT NULL DEFAULT 'standard',
  `usage_type` varchar(30) NOT NULL DEFAULT 'sale',
  `roll_length` decimal(8,2) NOT NULL DEFAULT 30.00 COMMENT 'طول الرول الكامل بالأمتار (للمنتجات من نوع fractional)',
  `waste_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `piece_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `quantity` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `is_splittable` tinyint(1) NOT NULL DEFAULT 0,
  `items_per_unit` int(11) NOT NULL DEFAULT 1,
  `carton_qty` int(10) unsigned DEFAULT NULL,
  `quick_sale_default_unit` varchar(10) NOT NULL DEFAULT 'unit',
  `min_stock` decimal(18,8) NOT NULL DEFAULT 0.00000000,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `fk_products_store_id_cascade` (`store_id`),
  KEY `products_description_index` (`description`(768)),
  CONSTRAINT `fk_products_store_id_cascade` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `purchase_name` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `cost` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchases_business_date_index` (`business_date`),
  KEY `purchases_store_id_foreign` (`store_id`),
  CONSTRAINT `purchases_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fraction_id` bigint(20) unsigned DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `custom_name` varchar(255) DEFAULT NULL,
  `custom_consumption` decimal(8,2) DEFAULT NULL,
  `custom_meters` decimal(8,2) DEFAULT NULL,
  `roll_length_at_sale` decimal(8,2) DEFAULT NULL,
  `unit_type` varchar(255) DEFAULT NULL,
  `unit_label_snapshot` varchar(50) DEFAULT NULL,
  `product_type_snapshot` varchar(50) DEFAULT NULL,
  `product_usage_snapshot` varchar(50) DEFAULT NULL,
  `is_splittable_snapshot` tinyint(1) DEFAULT NULL,
  `items_per_unit_snapshot` decimal(12,4) DEFAULT NULL,
  `roll_length_snapshot` decimal(12,4) DEFAULT NULL,
  `quantity_snapshot` decimal(12,4) DEFAULT NULL,
  `sale_price_snapshot` decimal(12,2) DEFAULT NULL,
  `cost_price_snapshot` decimal(12,2) DEFAULT NULL,
  `snapshot_source` varchar(30) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_id_foreign` (`sale_id`),
  KEY `sale_items_fraction_id_foreign` (`fraction_id`),
  CONSTRAINT `sale_items_fraction_id_foreign` FOREIGN KEY (`fraction_id`) REFERENCES `product_fractions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3471 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `accountant_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) NOT NULL,
  `cash_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغ المدفوع نقداً',
  `card_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'المبلغ المدفوع بالشبكة',
  `remaining_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_type` enum('cash','card','credit','internal_use','mixed') DEFAULT 'cash',
  `has_partial_credit` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'هل تحتوي الفاتورة على آجل جزئي مع كاش أو شبكة',
  `has_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `daily_balance_id` bigint(20) unsigned DEFAULT NULL,
  `client_operation_id` char(36) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `products_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` int(11) NOT NULL DEFAULT 0,
  `labor_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_client_operation_id_unique` (`client_operation_id`),
  KEY `sales_store_id_foreign` (`store_id`),
  KEY `sales_employee_id_foreign` (`employee_id`),
  KEY `sales_daily_balance_id_foreign` (`daily_balance_id`),
  KEY `sales_business_date_index` (`business_date`),
  CONSTRAINT `sales_daily_balance_id_foreign` FOREIGN KEY (`daily_balance_id`) REFERENCES `daily_balances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5677 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `sale_price_snapshot` decimal(12,2) DEFAULT NULL,
  `cost_price_snapshot` decimal(12,2) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('increase','decrease') NOT NULL,
  `quantity` decimal(18,6) NOT NULL,
  `requested_quantity` decimal(15,4) DEFAULT NULL,
  `unit_type_at_movement` varchar(30) DEFAULT NULL,
  `product_type_at_movement` varchar(30) DEFAULT NULL,
  `is_splittable_at_movement` tinyint(1) DEFAULT NULL,
  `items_per_unit_at_movement` decimal(15,4) DEFAULT NULL,
  `roll_length_value_at_movement` decimal(15,4) DEFAULT NULL,
  `display_unit_label` varchar(30) DEFAULT NULL,
  `balance_before` decimal(18,6) DEFAULT NULL,
  `balance_after` decimal(18,6) DEFAULT NULL,
  `meters` decimal(18,6) DEFAULT NULL,
  `roll_length_at_movement` decimal(18,6) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `business_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_store_id_foreign` (`store_id`),
  KEY `stock_movements_product_id_foreign` (`product_id`),
  KEY `stock_movements_user_id_foreign` (`user_id`),
  KEY `stock_movements_business_date_index` (`business_date`),
  CONSTRAINT `fk_stock_movements_store_id_cascade` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4964 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_purchase_order_count_attempts`
--

DROP TABLE IF EXISTS `store_purchase_order_count_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_purchase_order_count_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_purchase_order_id` bigint(20) unsigned NOT NULL,
  `store_purchase_order_item_id` bigint(20) unsigned NOT NULL,
  `attempt` tinyint(3) unsigned NOT NULL,
  `counted_quantity` decimal(15,2) NOT NULL,
  `system_quantity_image` decimal(15,2) NOT NULL,
  `unit_type` varchar(30) NOT NULL,
  `accountant_id` bigint(20) unsigned DEFAULT NULL,
  `note` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_count_attempt_item_attempt_unique` (`store_purchase_order_item_id`,`attempt`),
  KEY `po_counts_order_fk` (`store_purchase_order_id`),
  KEY `po_counts_accountant_fk` (`accountant_id`),
  CONSTRAINT `po_counts_accountant_fk` FOREIGN KEY (`accountant_id`) REFERENCES `accountants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `po_counts_item_fk` FOREIGN KEY (`store_purchase_order_item_id`) REFERENCES `store_purchase_order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `po_counts_order_fk` FOREIGN KEY (`store_purchase_order_id`) REFERENCES `store_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_purchase_order_events`
--

DROP TABLE IF EXISTS `store_purchase_order_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_purchase_order_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_purchase_order_id` bigint(20) unsigned NOT NULL,
  `store_purchase_order_item_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(60) NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) DEFAULT NULL,
  `actor_type` varchar(20) NOT NULL,
  `actor_id` bigint(20) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_purchase_order_events_store_purchase_order_item_id_foreign` (`store_purchase_order_item_id`),
  KEY `po_events_order_created_index` (`store_purchase_order_id`,`created_at`),
  CONSTRAINT `store_purchase_order_events_store_purchase_order_id_foreign` FOREIGN KEY (`store_purchase_order_id`) REFERENCES `store_purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_purchase_order_events_store_purchase_order_item_id_foreign` FOREIGN KEY (`store_purchase_order_item_id`) REFERENCES `store_purchase_order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_purchase_order_items`
--

DROP TABLE IF EXISTS `store_purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_purchase_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `matched_product_id` bigint(20) unsigned DEFAULT NULL,
  `custom_product_name` varchar(255) DEFAULT NULL,
  `quantity_requested` decimal(15,2) NOT NULL,
  `quantity_received` decimal(15,2) DEFAULT NULL,
  `unit_type` varchar(30) NOT NULL DEFAULT 'unit',
  `items_per_unit` int(10) unsigned DEFAULT NULL,
  `roll_length` decimal(12,2) DEFAULT NULL,
  `cost_price_at_order` decimal(10,2) DEFAULT NULL,
  `cost_price_at_receipt` decimal(10,2) DEFAULT NULL,
  `price_variance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_variance_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `update_product_cost` tinyint(1) NOT NULL DEFAULT 0,
  `stock_quantity_before` decimal(15,3) DEFAULT NULL,
  `stock_quantity_after` decimal(15,3) DEFAULT NULL,
  `cost_price_before` decimal(10,2) DEFAULT NULL,
  `cost_price_after` decimal(10,2) DEFAULT NULL,
  `owner_purchase_id` bigint(20) unsigned DEFAULT NULL,
  `add_to_owner_purchases` tinyint(1) NOT NULL DEFAULT 0,
  `receipt_notes` text DEFAULT NULL,
  `inventory_count_required` tinyint(1) NOT NULL DEFAULT 0,
  `inventory_count_quantity` decimal(15,2) DEFAULT NULL,
  `inventory_count_unit` varchar(20) DEFAULT NULL,
  `inventory_count_note` text DEFAULT NULL,
  `system_quantity_snapshot` decimal(15,2) DEFAULT NULL,
  `inventory_counted_quantity` decimal(15,3) DEFAULT NULL,
  `inventory_snapshot_quantity` decimal(15,3) DEFAULT NULL,
  `inventory_counted_at` timestamp NULL DEFAULT NULL,
  `inventory_snapshot_at` timestamp NULL DEFAULT NULL,
  `inventory_count_submitted_at` timestamp NULL DEFAULT NULL,
  `inventory_count_submitted_by` bigint(20) unsigned DEFAULT NULL,
  `inventory_count_notes` text DEFAULT NULL,
  `inventory_changed_after_count` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `inventory_count_attempt` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `excluded_after_count` tinyint(1) NOT NULL DEFAULT 0,
  `excluded_at` timestamp NULL DEFAULT NULL,
  `exclusion_reason` text DEFAULT NULL,
  `changed_by_type` varchar(20) DEFAULT NULL,
  `changed_by_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_purchase_order_items_store_purchase_order_id_foreign` (`store_purchase_order_id`),
  KEY `store_purchase_order_items_product_id_index` (`product_id`),
  KEY `store_purchase_order_items_matched_product_id_index` (`matched_product_id`),
  KEY `store_purchase_order_items_owner_purchase_id_foreign` (`owner_purchase_id`),
  KEY `store_purchase_order_items_excluded_after_count_index` (`excluded_after_count`),
  CONSTRAINT `store_purchase_order_items_matched_product_id_foreign` FOREIGN KEY (`matched_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_purchase_order_items_owner_purchase_id_foreign` FOREIGN KEY (`owner_purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_purchase_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_purchase_order_items_store_purchase_order_id_foreign` FOREIGN KEY (`store_purchase_order_id`) REFERENCES `store_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3556 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_purchase_orders`
--

DROP TABLE IF EXISTS `store_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `accountant_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending_owner_review','inventory_returned','inventory_submitted','sent','received','approved','rejected','cancelled') NOT NULL DEFAULT 'draft',
  `workflow_status` varchar(50) NOT NULL DEFAULT 'draft_accountant',
  `inventory_review_status` varchar(40) DEFAULT NULL,
  `edit_return_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `inventory_review_note` text DEFAULT NULL,
  `owner_notes` text DEFAULT NULL,
  `accountant_notes` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `inventory_returned_at` timestamp NULL DEFAULT NULL,
  `inventory_draft_saved_at` timestamp NULL DEFAULT NULL,
  `owner_reviewed_at` timestamp NULL DEFAULT NULL,
  `returned_for_inventory_at` timestamp NULL DEFAULT NULL,
  `inventory_submitted_at` timestamp NULL DEFAULT NULL,
  `inventory_submitted_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_business_date` date DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `receipt_actor_type` varchar(20) DEFAULT NULL,
  `receipt_actor_id` bigint(20) unsigned DEFAULT NULL,
  `approval_operation_id` char(36) DEFAULT NULL,
  `final_notice_until` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_purchase_orders_approval_operation_id_unique` (`approval_operation_id`),
  KEY `store_purchase_orders_store_id_status_index` (`store_id`,`status`),
  KEY `store_purchase_orders_user_id_status_index` (`user_id`,`status`),
  KEY `store_purchase_orders_approved_business_date_index` (`approved_business_date`),
  KEY `store_purchase_orders_accountant_id_status_index` (`accountant_id`,`status`),
  KEY `store_purchase_orders_workflow_status_index` (`workflow_status`),
  CONSTRAINT `store_purchase_orders_accountant_id_foreign` FOREIGN KEY (`accountant_id`) REFERENCES `accountants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_purchase_orders_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_purchase_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_settings`
--

DROP TABLE IF EXISTS `store_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_settings_store_id_foreign` (`store_id`),
  CONSTRAINT `store_settings_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_transfer_items`
--

DROP TABLE IF EXISTS `store_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `store_transfer_id` bigint(20) unsigned NOT NULL,
  `sender_product_id` bigint(20) unsigned DEFAULT NULL,
  `receiver_product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `requested_quantity` decimal(15,3) NOT NULL,
  `normalized_quantity` decimal(15,3) NOT NULL,
  `unit_type` varchar(30) NOT NULL DEFAULT 'unit',
  `unit_label_snapshot` varchar(50) DEFAULT NULL,
  `product_type_snapshot` varchar(50) DEFAULT NULL,
  `is_splittable_snapshot` tinyint(1) DEFAULT NULL,
  `items_per_unit_snapshot` decimal(12,4) DEFAULT NULL,
  `roll_length_snapshot` decimal(12,4) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sender_stock_before` decimal(15,3) DEFAULT NULL,
  `sender_stock_after` decimal(15,3) DEFAULT NULL,
  `receiver_stock_before` decimal(15,3) DEFAULT NULL,
  `receiver_stock_after` decimal(15,3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_transfer_items_store_transfer_id_foreign` (`store_transfer_id`),
  KEY `store_transfer_items_sender_product_id_index` (`sender_product_id`),
  KEY `store_transfer_items_receiver_product_id_index` (`receiver_product_id`),
  CONSTRAINT `store_transfer_items_receiver_product_id_foreign` FOREIGN KEY (`receiver_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_transfer_items_sender_product_id_foreign` FOREIGN KEY (`sender_product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_transfer_items_store_transfer_id_foreign` FOREIGN KEY (`store_transfer_id`) REFERENCES `store_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_transfers`
--

DROP TABLE IF EXISTS `store_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `store_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_store_id` bigint(20) unsigned NOT NULL,
  `receiver_store_id` bigint(20) unsigned NOT NULL,
  `status` enum('pending','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `request_business_date` date DEFAULT NULL,
  `action_business_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `action_by_type` varchar(255) DEFAULT NULL,
  `action_by_id` bigint(20) unsigned DEFAULT NULL,
  `acted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `receiver_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `store_transfers_sender_store_id_status_index` (`sender_store_id`,`status`),
  KEY `store_transfers_receiver_store_id_status_index` (`receiver_store_id`,`status`),
  KEY `store_transfers_created_by_type_created_by_id_index` (`created_by_type`,`created_by_id`),
  KEY `store_transfers_action_by_type_action_by_id_index` (`action_by_type`,`action_by_id`),
  KEY `store_transfers_request_business_date_index` (`request_business_date`),
  KEY `store_transfers_action_business_date_index` (`action_business_date`),
  CONSTRAINT `store_transfers_receiver_store_id_foreign` FOREIGN KEY (`receiver_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_transfers_sender_store_id_foreign` FOREIGN KEY (`sender_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stores`
--

DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `commercial_registration` varchar(255) DEFAULT NULL,
  `bank_accounts` text DEFAULT NULL,
  `labor_description_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`labor_description_options`)),
  `logo` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `suspension_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `number_of_shifts` int(11) NOT NULL DEFAULT 1,
  `shift_1_start` time DEFAULT NULL,
  `shift_2_start` time DEFAULT NULL,
  `shift_3_start` time DEFAULT NULL,
  `force_shift_closure` tinyint(1) NOT NULL DEFAULT 0,
  `inventory_audit_cycle_months` tinyint(3) unsigned NOT NULL DEFAULT 6,
  `inventory_audit_start_mode` varchar(20) NOT NULL DEFAULT 'store_created_at',
  `inventory_audit_start_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stores_slug_unique` (`slug`),
  KEY `stores_user_id_foreign` (`user_id`),
  CONSTRAINT `fk_stores_user_id_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('basic','silver','gold') NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_at` date NOT NULL,
  `end_at` date NOT NULL,
  `status` enum('active','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `fk_subscriptions_user_id_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_sessions`
--

DROP TABLE IF EXISTS `support_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned DEFAULT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `admin_name` varchar(255) DEFAULT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `target_type` varchar(255) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `target_name` varchar(255) DEFAULT NULL,
  `target_role` varchar(255) DEFAULT NULL,
  `reason` text NOT NULL,
  `ticket_reference` varchar(255) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `started_ip` varchar(45) DEFAULT NULL,
  `ended_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_sessions_target_type_target_id_index` (`target_type`,`target_id`),
  KEY `support_sessions_admin_id_ended_at_index` (`admin_id`,`ended_at`),
  KEY `support_sessions_ticket_reference_index` (`ticket_reference`),
  KEY `support_sessions_support_ticket_id_foreign` (`support_ticket_id`),
  KEY `support_sessions_expires_at_index` (`expires_at`),
  CONSTRAINT `support_sessions_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_sessions_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_ticket_events`
--

DROP TABLE IF EXISTS `support_ticket_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_ticket_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `actor_role` varchar(20) NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_events_support_ticket_id_created_at_index` (`support_ticket_id`,`created_at`),
  CONSTRAINT `support_ticket_events_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_ticket_messages`
--

DROP TABLE IF EXISTS `support_ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_ticket_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `sender_role` varchar(20) NOT NULL,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_messages_support_ticket_id_created_at_index` (`support_ticket_id`,`created_at`),
  CONSTRAINT `support_ticket_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(255) NOT NULL,
  `owner_id` bigint(20) unsigned NOT NULL,
  `accountant_id` bigint(20) unsigned DEFAULT NULL,
  `requested_role` varchar(30) NOT NULL DEFAULT 'owner',
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `priority` varchar(20) NOT NULL DEFAULT 'normal',
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `support_response` text DEFAULT NULL,
  `responded_by` bigint(20) unsigned DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `owner_unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `support_unread_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by_support` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_reference_unique` (`reference`),
  KEY `support_tickets_accountant_id_foreign` (`accountant_id`),
  KEY `support_tickets_responded_by_foreign` (`responded_by`),
  KEY `support_tickets_owner_id_status_index` (`owner_id`,`status`),
  KEY `support_tickets_status_created_at_index` (`status`,`created_at`),
  KEY `support_tickets_last_activity_at_index` (`last_activity_at`),
  KEY `support_tickets_expires_at_index` (`expires_at`),
  CONSTRAINT `support_tickets_accountant_id_foreign` FOREIGN KEY (`accountant_id`) REFERENCES `accountants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `support_tickets_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_tickets_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `notifications_expiry` int(11) NOT NULL DEFAULT 15,
  `invoices_expiry` int(11) NOT NULL DEFAULT 30,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_settings_user_id_foreign` (`user_id`),
  CONSTRAINT `fk_user_settings_user_id_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `current_store_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','accountant','user') NOT NULL DEFAULT 'user',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `suspension_reason` varchar(255) DEFAULT NULL,
  `subscription_end_at` date DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `plan_id` bigint(20) unsigned DEFAULT NULL,
  `allowed_stores` int(11) NOT NULL DEFAULT 1,
  `allowed_accountants` int(11) NOT NULL DEFAULT 1,
  `welcome_shown` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_slug_unique` (`slug`),
  KEY `users_plan_id_foreign` (`plan_id`),
  KEY `users_current_store_id_foreign` (`current_store_id`),
  CONSTRAINT `users_current_store_id_foreign` FOREIGN KEY (`current_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-09  3:21:16
