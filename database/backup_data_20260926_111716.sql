-- orca-med-partners full backup (schema + data)
-- generated: 2026-09-26T11:17:16+00:00
-- database: orca-med-partners
-- tables: 33

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT=0;
START TRANSACTION;

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `role` varchar(255) NOT NULL DEFAULT 'employee',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_username_unique` (`username`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`value`)),
  `description` text DEFAULT NULL,
  `updated_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_settings_key_unique` (`key`),
  KEY `app_settings_updated_by_admin_id_foreign` (`updated_by_admin_id`),
  CONSTRAINT `app_settings_updated_by_admin_id_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `auditable_type` varchar(255) NOT NULL,
  `auditable_id` bigint(20) unsigned NOT NULL,
  `action` varchar(255) NOT NULL,
  `actor_type` varchar(255) NOT NULL,
  `actor_id` bigint(20) unsigned NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_actor_type_actor_id_index` (`actor_type`,`actor_id`),
  KEY `audit_logs_action_index` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=268 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `capital_snapshots`;
CREATE TABLE `capital_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `total_capital` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'final',
  `snapshot_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot_metadata`)),
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `capital_snapshots_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `capital_snapshots_year_month_index` (`year`,`month`),
  KEY `capital_snapshots_snapshot_date_index` (`snapshot_date`),
  CONSTRAINT `capital_snapshots_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `capital_snapshot_items`;
CREATE TABLE `capital_snapshot_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `capital_snapshot_id` bigint(20) unsigned NOT NULL,
  `participant_id` bigint(20) unsigned NOT NULL,
  `participant_capital_snapshot` decimal(15,2) NOT NULL,
  `participant_ratio_snapshot` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `calculation_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `capital_snapshot_participant_unique` (`capital_snapshot_id`,`participant_id`),
  KEY `capital_snapshot_items_participant_id_capital_snapshot_id_index` (`participant_id`,`capital_snapshot_id`),
  CONSTRAINT `capital_snapshot_items_capital_snapshot_id_foreign` FOREIGN KEY (`capital_snapshot_id`) REFERENCES `capital_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `capital_snapshot_items_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `depreciation_notes`;
CREATE TABLE `depreciation_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint(20) unsigned DEFAULT NULL,
  `fund_id` bigint(20) unsigned DEFAULT NULL,
  `monthly_profit_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(8,4) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `admin_note` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `depreciation_notes_participant_id_foreign` (`participant_id`),
  KEY `depreciation_notes_monthly_profit_id_foreign` (`monthly_profit_id`),
  KEY `depreciation_notes_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `depreciation_notes_year_month_index` (`year`,`month`),
  KEY `depreciation_notes_fund_id_transaction_date_index` (`fund_id`,`transaction_date`),
  CONSTRAINT `depreciation_notes_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depreciation_notes_fund_id_foreign` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depreciation_notes_monthly_profit_id_foreign` FOREIGN KEY (`monthly_profit_id`) REFERENCES `monthly_profits` (`id`) ON DELETE SET NULL,
  CONSTRAINT `depreciation_notes_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `distribution_rules`;
CREATE TABLE `distribution_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `management_fee_rate` decimal(8,4) NOT NULL,
  `depreciation_fund_rate` decimal(8,4) NOT NULL,
  `growth_fund_rate` decimal(8,4) NOT NULL,
  `incentive_fund_rate` decimal(8,4) NOT NULL,
  `distributed_share_rate` decimal(8,4) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `distribution_rules_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `distribution_rules_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `distribution_rules_effective_from_effective_to_index` (`effective_from`,`effective_to`),
  KEY `distribution_rules_status_index` (`status`),
  CONSTRAINT `distribution_rules_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `distribution_rules_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `failed_jobs`;
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

DROP TABLE IF EXISTS `funds`;
CREATE TABLE `funds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `funds_code_unique` (`code`),
  KEY `funds_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `funds_code_status_index` (`code`,`status`),
  CONSTRAINT `funds_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fund_transactions`;
CREATE TABLE `fund_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fund_id` bigint(20) unsigned NOT NULL,
  `monthly_profit_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_type` varchar(255) NOT NULL DEFAULT 'adjustment',
  `transaction_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `resulting_balance` decimal(15,2) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fund_transactions_monthly_profit_id_foreign` (`monthly_profit_id`),
  KEY `fund_transactions_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `fund_transactions_fund_id_created_at_index` (`fund_id`,`created_at`),
  KEY `fund_transactions_transaction_type_index` (`transaction_type`),
  CONSTRAINT `fund_transactions_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fund_transactions_fund_id_foreign` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fund_transactions_monthly_profit_id_foreign` FOREIGN KEY (`monthly_profit_id`) REFERENCES `monthly_profits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `investments`;
CREATE TABLE `investments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `invested_at` date NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `approved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investments_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `investments_participant_id_status_index` (`participant_id`,`status`),
  KEY `investments_invested_at_index` (`invested_at`),
  KEY `investments_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  CONSTRAINT `investments_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investments_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investments_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `jobs`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_batches`;
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

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `monthly_profits`;
CREATE TABLE `monthly_profits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `capital_snapshot_id` bigint(20) unsigned NOT NULL,
  `distribution_rule_id` bigint(20) unsigned DEFAULT NULL,
  `distribution_rule_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distribution_rule_snapshot`)),
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `gross_profit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `management_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `depreciation_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `growth_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `incentive_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `distributed_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rounding_delta_adjustment` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monthly_profit_version_unique` (`year`,`month`,`version`),
  KEY `monthly_profits_distribution_rule_id_foreign` (`distribution_rule_id`),
  KEY `monthly_profits_parent_id_foreign` (`parent_id`),
  KEY `monthly_profits_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `monthly_profits_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `monthly_profits_year_month_status_index` (`year`,`month`,`status`),
  KEY `monthly_profits_capital_snapshot_id_status_index` (`capital_snapshot_id`,`status`),
  CONSTRAINT `monthly_profits_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monthly_profits_capital_snapshot_id_foreign` FOREIGN KEY (`capital_snapshot_id`) REFERENCES `capital_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `monthly_profits_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `monthly_profits_distribution_rule_id_foreign` FOREIGN KEY (`distribution_rule_id`) REFERENCES `distribution_rules` (`id`),
  CONSTRAINT `monthly_profits_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `monthly_profits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `notifications_participant_id_is_read_index` (`participant_id`,`is_read`),
  KEY `notifications_type_index` (`type`),
  CONSTRAINT `notifications_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notifications_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_enabled_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'participant',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participants_username_unique` (`username`),
  UNIQUE KEY `participants_email_unique` (`email`),
  UNIQUE KEY `participants_code_unique` (`code`),
  KEY `participants_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `participants_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participant_fund_allocations`;
CREATE TABLE `participant_fund_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fund_id` bigint(20) unsigned NOT NULL,
  `monthly_profit_id` bigint(20) unsigned NOT NULL,
  `participant_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allocation_type` varchar(255) NOT NULL DEFAULT 'growth',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `participant_fund_allocation_unique` (`fund_id`,`monthly_profit_id`,`participant_id`,`allocation_type`),
  KEY `participant_fund_allocations_monthly_profit_id_foreign` (`monthly_profit_id`),
  KEY `participant_fund_allocations_participant_id_fund_id_index` (`participant_id`,`fund_id`),
  CONSTRAINT `participant_fund_allocations_fund_id_foreign` FOREIGN KEY (`fund_id`) REFERENCES `funds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_fund_allocations_monthly_profit_id_foreign` FOREIGN KEY (`monthly_profit_id`) REFERENCES `monthly_profits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_fund_allocations_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=551 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `participant_profit_allocations`;
CREATE TABLE `participant_profit_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `monthly_profit_id` bigint(20) unsigned NOT NULL,
  `participant_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `share_ratio` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `status` varchar(255) NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monthly_profit_participant_allocation_unique` (`monthly_profit_id`,`participant_id`),
  KEY `participant_profit_allocations_participant_id_status_index` (`participant_id`,`status`),
  CONSTRAINT `participant_profit_allocations_monthly_profit_id_foreign` FOREIGN KEY (`monthly_profit_id`) REFERENCES `monthly_profits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_profit_allocations_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=298 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `profit_projection_logs`;
CREATE TABLE `profit_projection_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `period_type` varchar(255) NOT NULL,
  `period_value` int(10) unsigned NOT NULL,
  `is_compounded` tinyint(1) NOT NULL DEFAULT 0,
  `expected_net_profit` decimal(15,2) NOT NULL,
  `expected_total_balance` decimal(15,2) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `profit_projection_logs_participant_id_foreign` (`participant_id`),
  KEY `profit_projection_logs_created_at_index` (`created_at`),
  KEY `profit_projection_logs_period_type_index` (`period_type`),
  CONSTRAINT `profit_projection_logs_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `refresh_tokens`;
CREATE TABLE `refresh_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `family` char(36) DEFAULT NULL,
  `token_hash` varchar(128) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `replaced_by_token_id` bigint(20) unsigned DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refresh_tokens_token_hash_unique` (`token_hash`),
  KEY `refresh_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `refresh_tokens_replaced_by_token_id_foreign` (`replaced_by_token_id`),
  KEY `refresh_tokens_family_index` (`family`),
  CONSTRAINT `refresh_tokens_replaced_by_token_id_foreign` FOREIGN KEY (`replaced_by_token_id`) REFERENCES `refresh_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settlements`;
CREATE TABLE `settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `total_distributed_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `participant_profit_share` decimal(15,2) NOT NULL DEFAULT 0.00,
  `participant_fund_share` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `amount_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `paid_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `payout_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_version_unique` (`year`,`version`),
  KEY `settlements_parent_id_foreign` (`parent_id`),
  KEY `settlements_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `settlements_approved_by_admin_id_foreign` (`approved_by_admin_id`),
  KEY `settlements_paid_by_admin_id_foreign` (`paid_by_admin_id`),
  KEY `settlements_year_status_index` (`year`,`status`),
  CONSTRAINT `settlements_approved_by_admin_id_foreign` FOREIGN KEY (`approved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlements_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlements_paid_by_admin_id_foreign` FOREIGN KEY (`paid_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlements_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `settlements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settlement_adjustments`;
CREATE TABLE `settlement_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint(20) unsigned NOT NULL,
  `settlement_payment_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `direction` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` text NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settlement_adjustments_settlement_payment_id_foreign` (`settlement_payment_id`),
  KEY `settlement_adjustments_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `settlement_adjustments_settlement_id_created_at_index` (`settlement_id`,`created_at`),
  CONSTRAINT `settlement_adjustments_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_adjustments_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`),
  CONSTRAINT `settlement_adjustments_settlement_payment_id_foreign` FOREIGN KEY (`settlement_payment_id`) REFERENCES `settlement_payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settlement_items`;
CREATE TABLE `settlement_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint(20) unsigned NOT NULL,
  `participant_id` bigint(20) unsigned NOT NULL,
  `profit_share` decimal(15,2) NOT NULL DEFAULT 0.00,
  `fund_share` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_participant_unique` (`settlement_id`,`participant_id`),
  KEY `settlement_items_participant_id_payment_status_index` (`participant_id`,`payment_status`),
  CONSTRAINT `settlement_items_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `settlement_items_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settlement_payments`;
CREATE TABLE `settlement_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_source` varchar(20) NOT NULL DEFAULT 'other',
  `reference` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settlement_payments_created_by_admin_id_foreign` (`created_by_admin_id`),
  KEY `settlement_payments_settlement_id_paid_at_index` (`settlement_id`,`paid_at`),
  KEY `settlement_payments_reference_index` (`reference`),
  KEY `settlement_payments_payment_source_index` (`payment_source`),
  CONSTRAINT `settlement_payments_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_payments_settlement_id_foreign` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settlement_payment_receipts`;
CREATE TABLE `settlement_payment_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_payment_id` bigint(20) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `mime` varchar(255) DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settlement_payment_receipts_settlement_payment_id_unique` (`settlement_payment_id`),
  KEY `settlement_payment_receipts_created_by_admin_id_foreign` (`created_by_admin_id`),
  CONSTRAINT `settlement_payment_receipts_created_by_admin_id_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `settlement_payment_receipts_settlement_payment_id_foreign` FOREIGN KEY (`settlement_payment_id`) REFERENCES `settlement_payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint(20) unsigned NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `resolved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_tickets_resolved_by_admin_id_foreign` (`resolved_by_admin_id`),
  KEY `support_tickets_participant_id_status_index` (`participant_id`,`status`),
  CONSTRAINT `support_tickets_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_tickets_resolved_by_admin_id_foreign` FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- table `admins` (3 rows)
-- ----------------------------
INSERT INTO `admins` (`id`,`name`,`username`,`email`,`password`,`status`,`role`,`permissions`,`is_super_admin`,`remember_token`,`deleted_at`,`created_at`,`updated_at`) VALUES
(1,'Super Administrator','superadmin','superadmin@example.com','$2y$12$2kgacbEshYQ2KKMOkdWOgOdZcFicuSUBltn15WQGSJXSr/VtARAsK','active','super-admin','[\"participants.view\",\"participants.create\",\"participants.update\",\"participants.activate\",\"participants.deactivate\",\"participants.reset_password\",\"investments.view\",\"investments.create\",\"investments.update\",\"capital.view\",\"capital.manage\",\"profits.view\",\"profits.create\",\"profits.update\",\"profits.approve\",\"funds.view\",\"funds.manage\",\"depreciation.view\",\"depreciation.create\",\"depreciation.update\",\"settlements.view\",\"settlements.create\",\"settlements.update\",\"settlements.approve\",\"settlements.pay\",\"reports.view\",\"reports.export\",\"notifications.view\",\"distribution_rules.view\",\"distribution_rules.manage\",\"settings.view\",\"settings.manage\",\"audit_logs.view\",\"roles.manage\",\"permissions.manage\"]',1,NULL,NULL,'2026-09-13 12:54:59','2026-09-13 12:54:59'),
(2,'Financial Manager','financial-manager','financial-manager@example.com','$2y$12$YScn18tkWGvlVMplL6JynepPIy9IJJM/muBZ63XW8b9smNWOjK3z.','active','financial-manager','[\"participants.view\",\"participants.create\",\"participants.update\",\"investments.view\",\"investments.create\",\"investments.update\",\"capital.view\",\"capital.manage\",\"profits.view\",\"profits.create\",\"profits.update\",\"profits.approve\",\"funds.view\",\"funds.manage\",\"depreciation.view\",\"depreciation.create\",\"depreciation.update\",\"settlements.view\",\"settlements.create\",\"settlements.update\",\"settlements.approve\",\"settlements.pay\",\"reports.view\",\"reports.export\",\"notifications.view\",\"distribution_rules.view\",\"distribution_rules.manage\",\"settings.view\"]',0,NULL,NULL,'2026-09-13 12:54:59','2026-09-13 12:54:59'),
(3,'Employee','employee','employee@example.com','$2y$12$kscUQDuC3YpuAB7nxzwobO6.36Y5hQ2PXHfr0r/rcffGQEfdGlR7q','active','employee','[\"participants.view\",\"notifications.view\",\"reports.view\",\"settings.view\",\"funds.view\",\"depreciation.view\",\"settlements.view\",\"distribution_rules.view\"]',0,NULL,NULL,'2026-09-13 12:55:00','2026-09-13 12:55:00');

-- ----------------------------
-- table `app_settings` (8 rows)
-- ----------------------------
INSERT INTO `app_settings` (`id`,`key`,`value`,`description`,`updated_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,'company_name','\"ORCA MED Partners\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(2,'currency_code','\"SAR\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(3,'currency_symbol','\"\\u062c.\\u0645\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(4,'date_format','\"Y-m-d\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(5,'roi_base_annual_rate','\"0.216\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(6,'roi_growth_bonuses','[\"0.005\",\"0.01\",\"0.0075\",\"0.005\"]',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(7,'session_lifetime_minutes','\"120\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10'),
(8,'login_throttle_attempts','\"10\"',NULL,1,'2026-09-24 02:07:10','2026-09-24 02:07:10');

-- ----------------------------
-- table `audit_logs` (267 rows)
-- ----------------------------
INSERT INTO `audit_logs` (`id`,`auditable_type`,`auditable_id`,`action`,`actor_type`,`actor_id`,`old_values`,`new_values`,`metadata`,`ip_address`,`user_agent`,`created_at`) VALUES
(1,'App\\Models\\Participant',2,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-15 14:02:01'),
(2,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"superadmin\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-16 04:55:45'),
(3,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"superadmin\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-16 04:55:57'),
(4,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"superadmin\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-16 04:56:02'),
(5,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-16 04:57:59'),
(6,'participant',2,'participant_login_success','App\\Models\\Participant',2,NULL,NULL,'{\"context\":\"participant_login\"}','127.0.0.1','PostmanRuntime/2.5.0','2026-09-16 04:58:31'),
(7,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-16 15:08:45'),
(8,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.59.27.162','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-17 02:14:52'),
(9,'App\\Models\\Participant',3,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"shallal\",\"email\":\"mohamedhattia0@gmail.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"shallal\",\"email\":\"mohamedhattia0@gmail.com\",\"status\":\"active\"}}','197.59.27.162','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-17 02:15:41'),
(10,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','45.102.131.34','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.1','2026-09-17 12:09:09'),
(11,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 14:46:10'),
(12,'App\\Models\\Participant',4,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"dr.mahmoud\",\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"dr.mahmoud\",\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 14:49:24'),
(13,'App\\Models\\Participant',5,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"en.ahmed\",\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"en.ahmed\",\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 14:51:37'),
(14,'App\\Models\\Participant',6,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"dr.abderahman\",\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"dr.abderahman\",\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 14:54:23'),
(15,'App\\Models\\Participant',7,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"mr.mohamed\",\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"mr.mohamed\",\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 14:56:51'),
(16,'App\\Models\\Participant',8,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"mr.mostafa\",\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"mr.mostafa\",\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:01:13'),
(17,'App\\Models\\Participant',9,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"mr.ibrahim\",\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"mr.ibrahim\",\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:02:52'),
(18,'App\\Models\\Participant',10,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"mr.Karim\",\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"mr.Karim\",\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:04:45'),
(19,'investment',1,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:08:23'),
(20,'investment',2,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:09:09'),
(21,'App\\Models\\Participant',11,'participant_created','App\\Models\\Admin',1,NULL,'{\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}','{\"new\":{\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:13:42'),
(22,'investment',3,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:14:40'),
(23,'investment',4,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:15:02'),
(24,'investment',5,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:15:23'),
(25,'investment',6,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:15:47'),
(26,'investment',7,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:16:11'),
(27,'investment',8,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:17:02'),
(28,'investment',9,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:17:39'),
(29,'investment',9,'investment_updated','App\\Models\\Admin',1,'{\"id\":9,\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:17:39.000000Z\",\"updated_at\":\"2026-09-17T12:17:39.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:17:59.000000Z\"}','{\"old\":{\"id\":9,\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:17:39.000000Z\",\"updated_at\":\"2026-09-17T12:17:39.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":4,\"amount\":\"43000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:17:59.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:17:59'),
(30,'investment',9,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":4,\"amount\":\"43000000.00\",\"approved_at\":\"2026-09-17 12:17:59\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:17:59'),
(31,'investment',8,'investment_updated','App\\Models\\Admin',1,'{\"id\":8,\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:17:02.000000Z\",\"updated_at\":\"2026-09-17T12:17:02.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:02.000000Z\"}','{\"old\":{\"id\":8,\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:17:02.000000Z\",\"updated_at\":\"2026-09-17T12:17:02.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":5,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:02.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:02'),
(32,'investment',8,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":5,\"amount\":\"2000000.00\",\"approved_at\":\"2026-09-17 12:18:02\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:02'),
(33,'investment',7,'investment_updated','App\\Models\\Admin',1,'{\"id\":7,\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:16:11.000000Z\",\"updated_at\":\"2026-09-17T12:16:11.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:07.000000Z\"}','{\"old\":{\"id\":7,\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:16:11.000000Z\",\"updated_at\":\"2026-09-17T12:16:11.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":8,\"amount\":\"7000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:07.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:07'),
(34,'investment',7,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":8,\"amount\":\"7000000.00\",\"approved_at\":\"2026-09-17 12:18:07\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:07'),
(35,'investment',6,'investment_updated','App\\Models\\Admin',1,'{\"id\":6,\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:47.000000Z\",\"updated_at\":\"2026-09-17T12:15:47.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:12.000000Z\"}','{\"old\":{\"id\":6,\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:47.000000Z\",\"updated_at\":\"2026-09-17T12:15:47.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":7,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:12.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:12'),
(36,'investment',6,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":7,\"amount\":\"1000000.00\",\"approved_at\":\"2026-09-17 12:18:12\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:12'),
(37,'investment',5,'investment_updated','App\\Models\\Admin',1,'{\"id\":5,\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:23.000000Z\",\"updated_at\":\"2026-09-17T12:15:23.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:16.000000Z\"}','{\"old\":{\"id\":5,\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:23.000000Z\",\"updated_at\":\"2026-09-17T12:15:23.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":10,\"amount\":\"2000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:16.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:16'),
(38,'investment',5,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":10,\"amount\":\"2000000.00\",\"approved_at\":\"2026-09-17 12:18:16\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:16'),
(39,'investment',4,'investment_updated','App\\Models\\Admin',1,'{\"id\":4,\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:02.000000Z\",\"updated_at\":\"2026-09-17T12:15:02.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:19.000000Z\"}','{\"old\":{\"id\":4,\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:15:02.000000Z\",\"updated_at\":\"2026-09-17T12:15:02.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":9,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:19.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:19'),
(40,'investment',4,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":9,\"amount\":\"1000000.00\",\"approved_at\":\"2026-09-17 12:18:19\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:19'),
(41,'investment',3,'investment_updated','App\\Models\\Admin',1,'{\"id\":3,\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:14:40.000000Z\",\"updated_at\":\"2026-09-17T12:14:40.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:22.000000Z\"}','{\"old\":{\"id\":3,\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:14:40.000000Z\",\"updated_at\":\"2026-09-17T12:14:40.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":11,\"amount\":\"1000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:22.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:22'),
(42,'investment',3,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":11,\"amount\":\"1000000.00\",\"approved_at\":\"2026-09-17 12:18:22\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:22'),
(43,'investment',2,'investment_updated','App\\Models\\Admin',1,'{\"id\":2,\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:09:09.000000Z\",\"updated_at\":\"2026-09-17T12:09:09.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:25.000000Z\"}','{\"old\":{\"id\":2,\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:09:09.000000Z\",\"updated_at\":\"2026-09-17T12:09:09.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":6,\"amount\":\"6000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:25.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:25'),
(44,'investment',2,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":6,\"amount\":\"6000000.00\",\"approved_at\":\"2026-09-17 12:18:25\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:25'),
(45,'investment',1,'investment_updated','App\\Models\\Admin',1,'{\"id\":1,\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:08:23.000000Z\",\"updated_at\":\"2026-09-17T12:08:23.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:29.000000Z\"}','{\"old\":{\"id\":1,\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:08:23.000000Z\",\"updated_at\":\"2026-09-17T12:08:23.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":4,\"amount\":\"4000000.00\",\"invested_at\":\"2026-10-01T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-17T12:18:29.000000Z\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:29'),
(46,'investment',1,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":4,\"amount\":\"4000000.00\",\"approved_at\":\"2026-09-17 12:18:29\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:18:29'),
(47,'capital_snapshot',1,'capital_snapshot_created','App\\Models\\Admin',1,NULL,'{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000003.00\",\"status\":\"final\"}','{\"new\":{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000003.00\",\"status\":\"final\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 15:24:18'),
(48,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.128.10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-17 15:42:27'),
(49,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"mr.mostafa\"}','2c0f:fc89:192:5ab3:840e:26a:b817:9276','PostmanRuntime/2.6.0','2026-09-17 17:28:05'),
(50,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','2c0f:fc89:192:5ab3:840e:26a:b817:9276','PostmanRuntime/2.6.0','2026-09-17 17:36:58'),
(51,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"mr.mohamed\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 19:22:13'),
(52,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"mr.mohamed\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 19:22:28'),
(53,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"mr.mohamed\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 19:22:31'),
(54,'admin',0,'admin_login_failure','system',0,NULL,NULL,'{\"context\":\"web_login\",\"username\":\"mr.mohamed\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-17 19:22:43'),
(55,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.128.10','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-17 19:57:33'),
(56,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','197.59.184.140','PostmanRuntime/2.7.0','2026-09-19 11:05:22'),
(57,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','197.59.184.140','Dart/3.12 (dart:io)','2026-09-19 11:33:12'),
(58,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','197.59.184.140','Dart/3.12 (dart:io)','2026-09-19 12:47:39'),
(59,'investment',1,'authorization_denied','App\\Models\\Participant',3,NULL,NULL,'{\"action\":\"view\",\"endpoint\":\"api\\/participant\\/investments\\/1\",\"reason\":\"ownership_mismatch\"}','197.59.184.140','PostmanRuntime/2.7.0','2026-09-19 12:49:14'),
(60,'investment',1,'authorization_denied','App\\Models\\Participant',3,NULL,NULL,'{\"action\":\"view\",\"endpoint\":\"api\\/participant\\/investments\\/1\",\"reason\":\"ownership_mismatch\"}','197.59.184.140','PostmanRuntime/2.7.0','2026-09-19 12:52:06'),
(61,'participant',3,'participant_password_change','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"[redacted]\"}','105.39.247.28','Dart/3.12 (dart:io)','2026-09-19 14:27:20'),
(62,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','105.39.247.28','Dart/3.12 (dart:io)','2026-09-19 14:27:35'),
(63,'participant',2,'participant_login_success','App\\Models\\Participant',2,NULL,NULL,'{\"context\":\"participant_login\"}','197.43.21.126','PostmanRuntime/2.6.0','2026-09-19 15:19:25'),
(64,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','197.43.21.126','PostmanRuntime/2.6.0','2026-09-19 15:30:41'),
(65,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"mahmoud_elmalawani\"}','2c0f:fc89:191:c8e0:4038:bad7:ea66:85b0','PostmanRuntime/2.7.0','2026-09-19 15:30:45'),
(66,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','2c0f:fc89:191:c8e0:4038:bad7:ea66:85b0','PostmanRuntime/2.7.0','2026-09-19 15:31:00'),
(67,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.59.109.23','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-19 15:51:34'),
(68,'investment',10,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":3,\"amount\":\"1111.00\",\"invested_at\":\"2026-09-19T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":3,\"amount\":\"1111.00\",\"invested_at\":\"2026-09-19T00:00:00.000000Z\",\"status\":\"pending\"}}','197.59.109.23','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-19 15:54:50'),
(69,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"test\"}','105.39.247.28','Python-urllib/3.14','2026-09-19 23:30:12'),
(70,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"test\"}','105.39.247.28','Python-urllib/3.14','2026-09-19 23:32:19'),
(71,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','105.39.247.28','Dart/3.12 (dart:io)','2026-09-20 02:54:40'),
(72,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.59.109.23','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 11:25:34'),
(73,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.59.109.23','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 11:25:37'),
(74,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.152.84','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 12:04:38'),
(75,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.152.84','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 14:11:04'),
(76,'App\\Models\\Participant',2,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"test4 \\u0644\\u0627\\u0644\\u0627\\u0644\\u0627\\u0627\\u0627\\u0627\",\"last_name\":\"\\u0644\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0644\",\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"}','{\"first_name\":\"test4\",\"last_name\":\"\\u0644\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0644\",\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"test4 \\u0644\\u0627\\u0644\\u0627\\u0644\\u0627\\u0627\\u0627\\u0627\",\"last_name\":\"\\u0644\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0644\",\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"test4\",\"last_name\":\"\\u0644\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0627\\u0644\",\"username\":\"superadmin\",\"email\":\"radyibrahim777@gmail.com\",\"status\":\"active\"}}','197.43.152.84','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 14:17:04'),
(77,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.59.109.23','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-20 15:36:28'),
(78,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:39:25'),
(79,'App\\Models\\Participant',11,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:46:01'),
(80,'App\\Models\\Participant',11,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:46:01'),
(81,'distribution_rule',1,'distribution_rule_created','App\\Models\\Admin',1,NULL,'{\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\"}','{\"new\":{\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:55:00'),
(82,'monthly_profit',1,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":9,\"version\":1}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:55:42'),
(83,'monthly_profit',1,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:56:01'),
(84,'settlement',1,'settlement_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"version\":1,\"participant_profit_share\":\"1950195.00\"}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:56:16'),
(85,'fund',1,'fund_created','App\\Models\\Admin',1,NULL,'{\"code\":\"1000\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u0627\\u0644\\u0627\\u0647\\u0644\\u0627\\u0643\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}','{\"new\":{\"code\":\"1000\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u0627\\u0644\\u0627\\u0647\\u0644\\u0627\\u0643\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:57:39'),
(86,'fund',2,'fund_created','App\\Models\\Admin',1,NULL,'{\"code\":\"1001\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u0645\\u0639\\u062f\\u0644 \\u0627\\u0644\\u0646\\u0645\\u0648\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}','{\"new\":{\"code\":\"1001\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u0645\\u0639\\u062f\\u0644 \\u0627\\u0644\\u0646\\u0645\\u0648\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:58:02'),
(87,'fund',3,'fund_created','App\\Models\\Admin',1,NULL,'{\"code\":\"1002\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u062d\\u0627\\u0641\\u0632 \\u0645\\u0634\\u0627\\u0631\\u0643\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}','{\"new\":{\"code\":\"1002\",\"name\":\"\\u0635\\u0646\\u062f\\u0648\\u0642 \\u062d\\u0627\\u0641\\u0632 \\u0645\\u0634\\u0627\\u0631\\u0643\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 15:58:24'),
(88,'fund',4,'fund_created','App\\Models\\Admin',1,NULL,'{\"code\":\"1003\",\"name\":\"\\u0646\\u0633\\u0628\\u0647 \\u0627\\u062f\\u0627\\u0631\\u0647 \\u0631\\u0627\\u0633 \\u0627\\u0644\\u0645\\u0627\\u0644\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}','{\"new\":{\"code\":\"1003\",\"name\":\"\\u0646\\u0633\\u0628\\u0647 \\u0627\\u062f\\u0627\\u0631\\u0647 \\u0631\\u0627\\u0633 \\u0627\\u0644\\u0645\\u0627\\u0644\",\"status\":\"active\",\"description\":null,\"current_balance\":\"0.00\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:07:05'),
(89,'distribution_rule',2,'distribution_rule_created','App\\Models\\Admin',1,NULL,'{\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\"}','{\"new\":{\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:22:45'),
(90,'distribution_rule',2,'distribution_rule_updated','App\\Models\\Admin',1,'{\"id\":2,\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\",\"is_default\":false,\"notes\":null,\"created_by_admin_id\":1,\"approved_by_admin_id\":null,\"approved_at\":null,\"created_at\":\"2026-09-20T13:22:45.000000Z\",\"updated_at\":\"2026-09-20T13:22:45.000000Z\"}','{\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\"}','{\"old\":{\"id\":2,\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\",\"is_default\":false,\"notes\":null,\"created_by_admin_id\":1,\"approved_by_admin_id\":null,\"approved_at\":null,\"created_at\":\"2026-09-20T13:22:45.000000Z\",\"updated_at\":\"2026-09-20T13:22:45.000000Z\"},\"new\":{\"effective_from\":\"2026-09-20T00:00:00.000000Z\",\"effective_to\":null,\"management_fee_rate\":\"0.4000\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.5000\",\"status\":\"draft\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:23:24'),
(91,'distribution_rule',1,'distribution_rule_updated','App\\Models\\Admin',1,'{\"id\":1,\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\",\"is_default\":true,\"notes\":null,\"created_by_admin_id\":1,\"approved_by_admin_id\":null,\"approved_at\":null,\"created_at\":\"2026-09-20T12:55:00.000000Z\",\"updated_at\":\"2026-09-20T12:55:00.000000Z\"}','{\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\"}','{\"old\":{\"id\":1,\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\",\"is_default\":true,\"notes\":null,\"created_by_admin_id\":1,\"approved_by_admin_id\":null,\"approved_at\":null,\"created_at\":\"2026-09-20T12:55:00.000000Z\",\"updated_at\":\"2026-09-20T12:55:00.000000Z\"},\"new\":{\"effective_from\":\"2026-01-01T00:00:00.000000Z\",\"effective_to\":\"2027-01-01T00:00:00.000000Z\",\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\",\"status\":\"draft\"}}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:23:39'),
(92,'monthly_profit',2,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":10,\"version\":1}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:26:42'),
(93,'monthly_profit',2,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','196.202.22.233','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-20 16:26:51'),
(94,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.152.84','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 13:52:04'),
(95,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.128.116','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 18:42:49'),
(96,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','105.37.224.65','Dart/3.12 (dart:io)','2026-09-22 09:52:51'),
(97,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','105.42.250.115','Dart/3.12 (dart:io)','2026-09-22 21:18:46'),
(98,'participant',3,'two_factor_toggled','App\\Models\\Participant',3,NULL,NULL,'{\"endpoint\":\"api\\/v1\\/me\\/settings\\/2fa\\/toggle\",\"enabled\":true}','105.42.250.115','Dart/3.12 (dart:io)','2026-09-22 21:56:16'),
(99,'participant',3,'two_factor_toggled','App\\Models\\Participant',3,NULL,NULL,'{\"endpoint\":\"api\\/v1\\/me\\/settings\\/2fa\\/toggle\",\"enabled\":false}','105.42.250.115','Dart/3.12 (dart:io)','2026-09-22 21:56:40'),
(100,'participant',3,'two_factor_toggled','App\\Models\\Participant',3,NULL,NULL,'{\"endpoint\":\"api\\/v1\\/me\\/settings\\/2fa\\/toggle\",\"enabled\":true}','105.42.250.115','Dart/3.12 (dart:io)','2026-09-22 22:31:21'),
(101,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','197.59.0.32','Dart/3.12 (dart:io)','2026-09-23 19:31:17'),
(102,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','17.185.64.126','Dart/3.12 (dart:io)','2026-09-23 20:28:21'),
(103,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:26:10'),
(104,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:26:44'),
(105,'participant',3,'participant_login_success','App\\Models\\Participant',3,NULL,NULL,'{\"context\":\"participant_login\"}','139.178.129.65','Dart/3.12 (dart:io)','2026-09-23 21:26:54'),
(106,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:27:11'),
(107,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:27:31'),
(108,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:28:03'),
(109,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:28:53'),
(110,'participant',0,'participant_login_failure','system',0,NULL,NULL,'{\"username\":\"shallal\"}','139.178.129.67','Dart/3.12 (dart:io)','2026-09-23 21:29:37'),
(111,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:02:14'),
(112,'depreciation_note',1,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"125000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"125000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:08:49'),
(113,'monthly_profit',3,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":10,\"version\":2}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:08:49'),
(114,'monthly_profit',3,'monthly_profit_revision_created','App\\Models\\Admin',1,NULL,NULL,'{\"parent_id\":2,\"version\":2}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:08:49'),
(115,'monthly_profit',3,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":2}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:09:05'),
(116,'monthly_profit',2,'monthly_profit_deleted','App\\Models\\Admin',1,NULL,NULL,'{\"period\":\"2026 \\/ 10\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:09:11'),
(117,'depreciation_note',2,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":11,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 11\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":11,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 11\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:06'),
(118,'monthly_profit',4,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":11,\"version\":1}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:06'),
(119,'depreciation_note',3,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"125000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"125000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:21'),
(120,'monthly_profit',5,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":10,\"version\":3}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:21'),
(121,'monthly_profit',5,'monthly_profit_revision_created','App\\Models\\Admin',1,NULL,NULL,'{\"parent_id\":3,\"version\":3}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:21'),
(122,'monthly_profit',5,'monthly_profit_deleted','App\\Models\\Admin',1,NULL,NULL,'{\"period\":\"2026 \\/ 10\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:34'),
(123,'fund_transaction',1,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":4,\"transaction_type\":\"deposit\",\"amount\":\"750000.00\",\"old_balance\":\"0.00\",\"new_balance\":\"750000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(124,'fund',4,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"750000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(125,'fund',4,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"750000.00\",\"transaction_id\":1}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(126,'fund_transaction',2,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":2,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(127,'fund',2,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(128,'fund',2,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\",\"transaction_id\":2}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(129,'fund_transaction',3,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":3,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(130,'fund',3,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(131,'fund',3,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"75000.00\",\"transaction_id\":3}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(132,'fund_transaction',4,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":1,\"transaction_type\":\"deposit\",\"amount\":\"150000.00\",\"old_balance\":\"0.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(133,'fund',1,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(134,'fund',1,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"150000.00\",\"transaction_id\":4}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(135,'monthly_profit',4,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:47'),
(136,'monthly_profit',3,'monthly_profit_deleted','App\\Models\\Admin',1,NULL,NULL,'{\"period\":\"2026 \\/ 10\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:19:56'),
(137,'depreciation_note',4,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":10,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 10\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:16'),
(138,'monthly_profit',6,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":10,\"version\":1}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:16'),
(139,'fund_transaction',5,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":4,\"transaction_type\":\"deposit\",\"amount\":\"750000.00\",\"old_balance\":\"750000.00\",\"new_balance\":\"1500000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(140,'fund',4,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"750000.00\",\"new_balance\":\"1500000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(141,'fund',4,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"750000.00\",\"new_balance\":\"1500000.00\",\"transaction_id\":5}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(142,'fund_transaction',6,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":2,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(143,'fund',2,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(144,'fund',2,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\",\"transaction_id\":6}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(145,'fund_transaction',7,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":3,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(146,'fund',3,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(147,'fund',3,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"75000.00\",\"new_balance\":\"150000.00\",\"transaction_id\":7}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(148,'fund_transaction',8,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":1,\"transaction_type\":\"deposit\",\"amount\":\"150000.00\",\"old_balance\":\"150000.00\",\"new_balance\":\"300000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(149,'fund',1,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"300000.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(150,'fund',1,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"300000.00\",\"transaction_id\":8}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(151,'monthly_profit',6,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 02:20:24'),
(152,'capital_snapshot_item',34,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":6,\"old_capital\":\"6000000.00\",\"new_capital\":\"6000001.00\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:06:49'),
(153,'capital_snapshot',1,'capital_snapshot_updated','App\\Models\\Admin',1,'{\"id\":1,\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000003.00\",\"status\":\"final\",\"snapshot_metadata\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:24:18.000000Z\",\"updated_at\":\"2026-09-17T12:24:18.000000Z\"}','{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000004.00\",\"status\":\"final\"}','{\"old\":{\"id\":1,\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000003.00\",\"status\":\"final\",\"snapshot_metadata\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T12:24:18.000000Z\",\"updated_at\":\"2026-09-17T12:24:18.000000Z\"},\"new\":{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000004.00\",\"status\":\"final\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:06:49'),
(154,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:09:11'),
(155,'investment',11,'investment_created','App\\Models\\Admin',1,NULL,'{\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"pending\"}','{\"new\":{\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"pending\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:11:57'),
(156,'investment',11,'investment_updated','App\\Models\\Admin',1,'{\"id\":11,\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-24T00:11:57.000000Z\",\"updated_at\":\"2026-09-24T00:11:57.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null}','{\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-24T00:12:07.000000Z\"}','{\"old\":{\"id\":11,\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"pending\",\"notes\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-24T00:11:57.000000Z\",\"updated_at\":\"2026-09-24T00:11:57.000000Z\",\"approved_by_admin_id\":null,\"approved_at\":null},\"new\":{\"participant_id\":4,\"amount\":\"1000000.00\",\"invested_at\":\"2026-09-24T00:00:00.000000Z\",\"status\":\"approved\",\"approved_at\":\"2026-09-24T00:12:07.000000Z\"}}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:12:07'),
(157,'investment',11,'investment_approved','App\\Models\\Admin',1,NULL,NULL,'{\"investor\":4,\"amount\":\"1000000.00\",\"approved_at\":\"2026-09-24 00:12:07\"}','196.150.54.116','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 03:12:07'),
(158,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.38.144','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 11:25:14'),
(159,'fund_transaction',9,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":4,\"transaction_type\":\"deposit\",\"amount\":\"750000.00\",\"old_balance\":\"1500000.00\",\"new_balance\":\"2250000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(160,'fund',4,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"1500000.00\",\"new_balance\":\"2250000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(161,'fund',4,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"1500000.00\",\"new_balance\":\"2250000.00\",\"transaction_id\":9}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(162,'fund_transaction',10,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":2,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(163,'fund',2,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(164,'fund',2,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\",\"transaction_id\":10}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(165,'fund_transaction',11,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":3,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(166,'fund',3,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(167,'fund',3,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"150000.00\",\"new_balance\":\"225000.00\",\"transaction_id\":11}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(168,'fund_transaction',12,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":1,\"transaction_type\":\"deposit\",\"amount\":\"150000.00\",\"old_balance\":\"300000.00\",\"new_balance\":\"450000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(169,'fund',1,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"300000.00\",\"new_balance\":\"450000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(170,'fund',1,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"300000.00\",\"new_balance\":\"450000.00\",\"transaction_id\":12}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(171,'depreciation_note',5,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-20T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 9\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0627\\u0644\\u0645\\u062e\\u0635\\u0635 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0623\\u062b\\u0646\\u0627\\u0621 \\u0625\\u0639\\u0627\\u062f\\u0629 \\u0627\\u0644\\u062a\\u0633\\u0648\\u064a\\u0629 \\u0627\\u0644\\u0645\\u0627\\u0644\\u064a\\u0629.\"}','{\"new\":{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-20T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 9\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0627\\u0644\\u0645\\u062e\\u0635\\u0635 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0623\\u062b\\u0646\\u0627\\u0621 \\u0625\\u0639\\u0627\\u062f\\u0629 \\u0627\\u0644\\u062a\\u0633\\u0648\\u064a\\u0629 \\u0627\\u0644\\u0645\\u0627\\u0644\\u064a\\u0629.\"}}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(172,'fund',1,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"450000.00\",\"new_balance\":\"450000.00\",\"fund_id\":1}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(173,'fund',2,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"225000.00\",\"fund_id\":2}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(174,'fund',3,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"225000.00\",\"fund_id\":3}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(175,'fund',4,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"2250000.00\",\"new_balance\":\"2250000.00\",\"fund_id\":4}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(176,'fund',5,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"0.00\",\"fund_id\":5}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(177,'settlement',1,'finance_reconcile_settlement_regenerated','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"participant_profit_share\":\"5850000.00\"}','127.0.0.1','Symfony','2026-09-24 10:26:09'),
(178,'fund',1,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"450000.00\",\"new_balance\":\"450000.00\",\"fund_id\":1}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(179,'fund',2,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"225000.00\",\"fund_id\":2}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(180,'fund',3,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"225000.00\",\"fund_id\":3}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(181,'fund',4,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"2250000.00\",\"new_balance\":\"2250000.00\",\"fund_id\":4}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(182,'fund',5,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"0.00\",\"fund_id\":5}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(183,'settlement',1,'finance_reconcile_settlement_regenerated','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"participant_profit_share\":\"5850000.00\"}','127.0.0.1','Symfony','2026-09-24 10:28:19'),
(184,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-24 11:25:45'),
(185,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.166.87','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 17:09:30'),
(186,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 22:59:28'),
(187,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 22:59:31'),
(188,'App\\Models\\Participant',7,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','196.150.55.52','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 23:14:02'),
(189,'App\\Models\\Participant',7,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"mohamed\",\"last_name\":\"elmalwany\",\"username\":\"mr.mohamed\",\"code\":null,\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"mohamed\",\"last_name\":\"elmalwany\",\"username\":\"mr.mohamed\",\"code\":\"100\",\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"mohamed\",\"last_name\":\"elmalwany\",\"username\":\"mr.mohamed\",\"code\":null,\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"mohamed\",\"last_name\":\"elmalwany\",\"username\":\"mr.mohamed\",\"code\":\"100\",\"email\":\"mr.mohamed@orcamed.com\",\"status\":\"active\"}}','196.150.55.52','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.2','2026-09-24 23:14:02'),
(190,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:26:47'),
(191,'depreciation_note',6,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"200000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":12,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 12\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"200000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":12,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 12\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:28'),
(192,'monthly_profit',7,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":12,\"version\":1}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:28'),
(193,'fund_transaction',13,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":4,\"transaction_type\":\"deposit\",\"amount\":\"1000000.00\",\"old_balance\":\"2250000.00\",\"new_balance\":\"3250000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(194,'fund',4,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"2250000.00\",\"new_balance\":\"3250000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(195,'fund',4,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"2250000.00\",\"new_balance\":\"3250000.00\",\"transaction_id\":13}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(196,'fund_transaction',14,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":2,\"transaction_type\":\"deposit\",\"amount\":\"100000.00\",\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(197,'fund',2,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(198,'fund',2,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\",\"transaction_id\":14}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(199,'fund_transaction',15,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":3,\"transaction_type\":\"deposit\",\"amount\":\"100000.00\",\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(200,'fund',3,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38');
INSERT INTO `audit_logs` (`id`,`auditable_type`,`auditable_id`,`action`,`actor_type`,`actor_id`,`old_values`,`new_values`,`metadata`,`ip_address`,`user_agent`,`created_at`) VALUES
(201,'fund',3,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"225000.00\",\"new_balance\":\"325000.00\",\"transaction_id\":15}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(202,'fund_transaction',16,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":1,\"transaction_type\":\"deposit\",\"amount\":\"200000.00\",\"old_balance\":\"450000.00\",\"new_balance\":\"650000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(203,'fund',1,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"450000.00\",\"new_balance\":\"650000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(204,'fund',1,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"450000.00\",\"new_balance\":\"650000.00\",\"transaction_id\":16}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(205,'monthly_profit',7,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:38:38'),
(206,'App\\Models\\Participant',11,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:43:12'),
(207,'App\\Models\\Participant',11,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"code\":null,\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"code\":\"101\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"code\":null,\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"ahmed\",\"last_name\":\"tayel\",\"username\":\"mr.ahmed\",\"code\":\"101\",\"email\":\"mr.ahmed@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:43:12'),
(208,'App\\Models\\Participant',10,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:43:44'),
(209,'App\\Models\\Participant',10,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"karim\",\"last_name\":\"tayel\",\"username\":\"mr.Karim\",\"code\":null,\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"karim\",\"last_name\":\"tayel\",\"username\":\"mr.Karim\",\"code\":\"102\",\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"karim\",\"last_name\":\"tayel\",\"username\":\"mr.Karim\",\"code\":null,\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"karim\",\"last_name\":\"tayel\",\"username\":\"mr.Karim\",\"code\":\"102\",\"email\":\"mr.Karim@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:43:44'),
(210,'App\\Models\\Participant',9,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:44:15'),
(211,'App\\Models\\Participant',9,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"Ibrahim\",\"last_name\":\"tayel\",\"username\":\"mr.ibrahim\",\"code\":null,\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"Ibrahim\",\"last_name\":\"tayel\",\"username\":\"mr.ibrahim\",\"code\":\"103\",\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"Ibrahim\",\"last_name\":\"tayel\",\"username\":\"mr.ibrahim\",\"code\":null,\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"Ibrahim\",\"last_name\":\"tayel\",\"username\":\"mr.ibrahim\",\"code\":\"103\",\"email\":\"mr.ibrahim@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:44:15'),
(212,'App\\Models\\Participant',8,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:44:44'),
(213,'App\\Models\\Participant',8,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"mostafa\",\"last_name\":\"elzatat\",\"username\":\"mr.mostafa\",\"code\":null,\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"mostafa\",\"last_name\":\"elzatat\",\"username\":\"mr.mostafa\",\"code\":\"104\",\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"mostafa\",\"last_name\":\"elzatat\",\"username\":\"mr.mostafa\",\"code\":null,\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"mostafa\",\"last_name\":\"elzatat\",\"username\":\"mr.mostafa\",\"code\":\"104\",\"email\":\"mr.mostafa@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:44:44'),
(214,'App\\Models\\Participant',6,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:45:07'),
(215,'App\\Models\\Participant',6,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"abderahman\",\"last_name\":\"elmalwany\",\"username\":\"dr.abderahman\",\"code\":null,\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"abderahman\",\"last_name\":\"elmalwany\",\"username\":\"dr.abderahman\",\"code\":\"105\",\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"abderahman\",\"last_name\":\"elmalwany\",\"username\":\"dr.abderahman\",\"code\":null,\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"abderahman\",\"last_name\":\"elmalwany\",\"username\":\"dr.abderahman\",\"code\":\"105\",\"email\":\"dr.abderahman@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:45:07'),
(216,'App\\Models\\Participant',5,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:45:34'),
(217,'App\\Models\\Participant',5,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"ahmed\",\"last_name\":\"elmalwany\",\"username\":\"en.ahmed\",\"code\":null,\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"ahmed\",\"last_name\":\"elmalwany\",\"username\":\"en.ahmed\",\"code\":\"106\",\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"ahmed\",\"last_name\":\"elmalwany\",\"username\":\"en.ahmed\",\"code\":null,\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"ahmed\",\"last_name\":\"elmalwany\",\"username\":\"en.ahmed\",\"code\":\"106\",\"email\":\"en.ahmed@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:45:34'),
(218,'App\\Models\\Participant',4,'participant_password_changed','App\\Models\\Admin',1,NULL,'{\"password\":\"[redacted]\"}','{\"new\":{\"password\":\"[redacted]\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:46:18'),
(219,'App\\Models\\Participant',4,'participant_updated','App\\Models\\Admin',1,'{\"first_name\":\"mahmoud\",\"last_name\":\"elmalwany\",\"username\":\"dr.mahmoud\",\"code\":null,\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"}','{\"first_name\":\"mahmoud\",\"last_name\":\"elmalwany\",\"username\":\"dr.mahmoud\",\"code\":\"107\",\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"}','{\"old\":{\"first_name\":\"mahmoud\",\"last_name\":\"elmalwany\",\"username\":\"dr.mahmoud\",\"code\":null,\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"},\"new\":{\"first_name\":\"mahmoud\",\"last_name\":\"elmalwany\",\"username\":\"dr.mahmoud\",\"code\":\"107\",\"email\":\"mahmoud84@orcamed.com\",\"status\":\"active\"}}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:46:18'),
(220,'capital_snapshot_item',50,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":4,\"old_capital\":\"51000000.00\",\"new_capital\":\"50000000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:47:35'),
(221,'capital_snapshot_item',51,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":7,\"old_capital\":\"0.00\",\"new_capital\":\"1000000.00\"}','45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','2026-09-26 01:47:35'),
(222,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','197.43.147.89','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-26 11:20:00'),
(223,'capital_snapshot_item',45,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":6,\"old_capital\":\"6000001.00\",\"new_capital\":\"6000000.00\",\"old_ratio\":\"0.0857\",\"new_ratio\":\"0.0857\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(224,'capital_snapshot_item',46,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":5,\"old_capital\":\"2000000.00\",\"new_capital\":\"2000000.00\",\"old_ratio\":\"0.0285\",\"new_ratio\":\"0.0286\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(225,'capital_snapshot_item',47,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":11,\"old_capital\":\"1000000.00\",\"new_capital\":\"1000000.00\",\"old_ratio\":\"0.0142\",\"new_ratio\":\"0.0143\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(226,'capital_snapshot_item',48,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":9,\"old_capital\":\"1000000.00\",\"new_capital\":\"1000000.00\",\"old_ratio\":\"0.0142\",\"new_ratio\":\"0.0143\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(227,'capital_snapshot_item',49,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":10,\"old_capital\":\"2000000.00\",\"new_capital\":\"2000000.00\",\"old_ratio\":\"0.0285\",\"new_ratio\":\"0.0286\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(228,'capital_snapshot_item',51,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":7,\"old_capital\":\"1000000.00\",\"new_capital\":\"1000000.00\",\"old_ratio\":\"0.0142\",\"new_ratio\":\"0.0143\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(229,'capital_snapshot_item',52,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":8,\"old_capital\":\"7000000.00\",\"new_capital\":\"7000000.00\",\"old_ratio\":\"0.0999\",\"new_ratio\":\"0.1000\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(230,'capital_snapshot_item',53,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":1,\"old_capital\":\"1.00\",\"new_capital\":\"0.00\",\"old_ratio\":\"0.0000\",\"new_ratio\":\"0.0000\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(231,'capital_snapshot_item',54,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":2,\"old_capital\":\"1.00\",\"new_capital\":\"0.00\",\"old_ratio\":\"0.0000\",\"new_ratio\":\"0.0000\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(232,'capital_snapshot_item',55,'capital_snapshot_item_updated','App\\Models\\Admin',1,NULL,NULL,'{\"snapshot_id\":1,\"participant_id\":3,\"old_capital\":\"1.00\",\"new_capital\":\"0.00\",\"old_ratio\":\"0.0000\",\"new_ratio\":\"0.0000\"}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(233,'capital_snapshot',1,'capital_snapshot_updated','App\\Models\\Admin',1,'{\"id\":1,\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000004.00\",\"status\":\"final\",\"snapshot_metadata\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T15:24:18.000000Z\",\"updated_at\":\"2026-09-24T03:06:49.000000Z\"}','{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000000.00\",\"status\":\"final\"}','{\"old\":{\"id\":1,\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000004.00\",\"status\":\"final\",\"snapshot_metadata\":null,\"created_by_admin_id\":1,\"created_at\":\"2026-09-17T15:24:18.000000Z\",\"updated_at\":\"2026-09-24T03:06:49.000000Z\"},\"new\":{\"snapshot_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":9,\"total_capital\":\"70000000.00\",\"status\":\"final\"}}','127.0.0.1','Symfony','2026-09-26 08:34:21'),
(234,'fund',1,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"650000.00\",\"new_balance\":\"650000.00\",\"fund_id\":1}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(235,'fund',2,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"325000.00\",\"fund_id\":2}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(236,'fund',3,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"325000.00\",\"fund_id\":3}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(237,'fund',4,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"3250000.00\",\"new_balance\":\"3250000.00\",\"fund_id\":4}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(238,'fund',5,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"0.00\",\"fund_id\":5}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(239,'settlement',1,'finance_reconcile_settlement_regenerated','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"participant_profit_share\":\"8450000.00\"}','127.0.0.1','Symfony','2026-09-26 08:35:25'),
(240,'fund',1,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"650000.00\",\"new_balance\":\"650000.00\",\"fund_id\":1}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(241,'fund',2,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"325000.00\",\"fund_id\":2}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(242,'fund',3,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"325000.00\",\"fund_id\":3}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(243,'fund',4,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"3250000.00\",\"new_balance\":\"3250000.00\",\"fund_id\":4}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(244,'fund',5,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"0.00\",\"fund_id\":5}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(245,'settlement',1,'finance_reconcile_settlement_regenerated','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"participant_profit_share\":\"8450000.00\"}','127.0.0.1','Symfony','2026-09-26 08:48:46'),
(246,'admin',1,'admin_login_success','App\\Models\\Admin',1,NULL,NULL,'{\"context\":\"web_login\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','2026-09-26 10:52:37'),
(247,'depreciation_note',7,'depreciation_created','App\\Models\\Admin',1,NULL,'{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":8,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 8\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}','{\"new\":{\"amount\":\"150000.00\",\"rate\":\"0.0500\",\"transaction_date\":\"2026-09-17T00:00:00.000000Z\",\"year\":2026,\"month\":8,\"description\":\"\\u0645\\u062e\\u0635\\u0635 \\u0625\\u0647\\u0644\\u0627\\u0643 \\u0634\\u0647\\u0631 8\\/2026\",\"admin_note\":\"\\u062a\\u0645 \\u0625\\u0646\\u0634\\u0627\\u0621 \\u0645\\u062e\\u0635\\u0635 \\u0627\\u0644\\u0625\\u0647\\u0644\\u0627\\u0643 \\u062a\\u0644\\u0642\\u0627\\u0626\\u064a\\u0627\\u064b \\u0645\\u0646 \\u0641\\u062a\\u0631\\u0629 \\u0627\\u0644\\u0623\\u0631\\u0628\\u0627\\u062d.\"}}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(248,'monthly_profit',8,'monthly_profit_created','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"month\":8,\"version\":1}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(249,'fund_transaction',17,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":4,\"transaction_type\":\"deposit\",\"amount\":\"750000.00\",\"old_balance\":\"3250000.00\",\"new_balance\":\"4000000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(250,'fund',4,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"3250000.00\",\"new_balance\":\"4000000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(251,'fund',4,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"3250000.00\",\"new_balance\":\"4000000.00\",\"transaction_id\":17}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(252,'fund_transaction',18,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":2,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(253,'fund',2,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(254,'fund',2,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\",\"transaction_id\":18}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(255,'fund_transaction',19,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":3,\"transaction_type\":\"deposit\",\"amount\":\"75000.00\",\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(256,'fund',3,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(257,'fund',3,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"325000.00\",\"new_balance\":\"400000.00\",\"transaction_id\":19}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(258,'fund_transaction',20,'fund_transaction_created','App\\Models\\Admin',1,NULL,NULL,'{\"fund_id\":1,\"transaction_type\":\"deposit\",\"amount\":\"150000.00\",\"old_balance\":\"650000.00\",\"new_balance\":\"800000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(259,'fund',1,'fund_deposit','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"650000.00\",\"new_balance\":\"800000.00\"}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(260,'fund',1,'fund_balance_changed','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"650000.00\",\"new_balance\":\"800000.00\",\"transaction_id\":20}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(261,'monthly_profit',8,'monthly_profit_approved','App\\Models\\Admin',1,NULL,NULL,'{\"version\":1}','127.0.0.1','Symfony','2026-09-26 11:02:59'),
(262,'fund',1,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"800000.00\",\"new_balance\":\"800000.00\",\"fund_id\":1}','127.0.0.1','Symfony','2026-09-26 11:03:00'),
(263,'fund',2,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"400000.00\",\"new_balance\":\"400000.00\",\"fund_id\":2}','127.0.0.1','Symfony','2026-09-26 11:03:00'),
(264,'fund',3,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"400000.00\",\"new_balance\":\"400000.00\",\"fund_id\":3}','127.0.0.1','Symfony','2026-09-26 11:03:00'),
(265,'fund',4,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"4000000.00\",\"new_balance\":\"4000000.00\",\"fund_id\":4}','127.0.0.1','Symfony','2026-09-26 11:03:00'),
(266,'fund',5,'fund_balance_recalculated','App\\Models\\Admin',1,NULL,NULL,'{\"old_balance\":\"0.00\",\"new_balance\":\"0.00\",\"fund_id\":5}','127.0.0.1','Symfony','2026-09-26 11:03:00'),
(267,'settlement',1,'finance_reconcile_settlement_regenerated','App\\Models\\Admin',1,NULL,NULL,'{\"year\":2026,\"participant_profit_share\":\"10400000.00\"}','127.0.0.1','Symfony','2026-09-26 11:03:00');

-- ----------------------------
-- table `cache` (24 rows)
-- ----------------------------
INSERT INTO `cache` (`key`,`value`,`expiration`) VALUES
('laravel-cache-0824634afcc7e8b9e64e6ecf5e91e5186bd0d560','i:1;',1790184561),
('laravel-cache-0824634afcc7e8b9e64e6ecf5e91e5186bd0d560:timer','i:1790184561;',1790184561),
('laravel-cache-092cc5c4317c9a652d6ce24f8865e8a2e2992f96','i:1;',1790103498),
('laravel-cache-092cc5c4317c9a652d6ce24f8865e8a2e2992f96:timer','i:1790103498;',1790103498),
('laravel-cache-149e7fd20095d3df4b3c927ffc640d704c25afd3','i:1;',1790060031),
('laravel-cache-149e7fd20095d3df4b3c927ffc640d704c25afd3:timer','i:1790060031;',1790060031),
('laravel-cache-19e9ba51a846eab90e0b43a243c6ccf71f12606a','i:1;',1790259029),
('laravel-cache-19e9ba51a846eab90e0b43a243c6ccf71f12606a:timer','i:1790259029;',1790259029),
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1790420017),
('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790420017;',1790420017),
('laravel-cache-6b6b360c5a3b58236ab747be8a23b6bfb62a460e','i:1;',1790375267),
('laravel-cache-6b6b360c5a3b58236ab747be8a23b6bfb62a460e:timer','i:1790375267;',1790375267),
('laravel-cache-875cb3be0ab42ee292140d6f1746a657d0d44f4a','i:1;',1790181137),
('laravel-cache-875cb3be0ab42ee292140d6f1746a657d0d44f4a:timer','i:1790181137;',1790181137),
('laravel-cache-969cecabfb83f71d5c3599ea13ceb92bad090893','i:2;',1790188193),
('laravel-cache-969cecabfb83f71d5c3599ea13ceb92bad090893:timer','i:1790188193;',1790188193),
('laravel-cache-b7b99f13887cfc8bc345a77ff59e2d738998eb42','i:1;',1790410860),
('laravel-cache-b7b99f13887cfc8bc345a77ff59e2d738998eb42:timer','i:1790410860;',1790410860),
('laravel-cache-be1251296e9be6bc55d7f1764dfab4b8654eb875','i:1;',1790208611),
('laravel-cache-be1251296e9be6bc55d7f1764dfab4b8654eb875:timer','i:1790208611;',1790208611),
('laravel-cache-cab823503f14f44d69fd368cf178635a3aa651f5','i:1;',1790238374),
('laravel-cache-cab823503f14f44d69fd368cf178635a3aa651f5:timer','i:1790238374;',1790238374),
('laravel-cache-e9788f57689f0af9fa1c25e9219d09527b13bda2','i:1;',1790188074),
('laravel-cache-e9788f57689f0af9fa1c25e9219d09527b13bda2:timer','i:1790188074;',1790188074);

-- table `cache_locks` is empty

-- ----------------------------
-- table `capital_snapshots` (1 rows)
-- ----------------------------
INSERT INTO `capital_snapshots` (`id`,`snapshot_date`,`year`,`month`,`total_capital`,`status`,`snapshot_metadata`,`created_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,'2026-09-17',2026,9,'70000000.00','final',NULL,1,'2026-09-17 15:24:18','2026-09-26 08:34:21');

-- ----------------------------
-- table `capital_snapshot_items` (11 rows)
-- ----------------------------
INSERT INTO `capital_snapshot_items` (`id`,`capital_snapshot_id`,`participant_id`,`participant_capital_snapshot`,`participant_ratio_snapshot`,`calculation_metadata`,`created_at`,`updated_at`) VALUES
(45,1,6,'6000000.00','0.0857','{\"index\":0}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(46,1,5,'2000000.00','0.0286','{\"index\":1}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(47,1,11,'1000000.00','0.0143','{\"index\":2}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(48,1,9,'1000000.00','0.0143','{\"index\":3}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(49,1,10,'2000000.00','0.0286','{\"index\":4}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(50,1,4,'50000000.00','0.7142','{\"index\":5}','2026-09-26 01:47:35','2026-09-26 01:47:35'),
(51,1,7,'1000000.00','0.0143','{\"index\":6}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(52,1,8,'7000000.00','0.1000','{\"index\":7}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(53,1,1,'0.00','0.0000','{\"index\":8}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(54,1,2,'0.00','0.0000','{\"index\":9}','2026-09-26 01:47:35','2026-09-26 08:34:21'),
(55,1,3,'0.00','0.0000','{\"index\":10}','2026-09-26 01:47:35','2026-09-26 08:34:21');

-- ----------------------------
-- table `depreciation_notes` (7 rows)
-- ----------------------------
INSERT INTO `depreciation_notes` (`id`,`participant_id`,`fund_id`,`monthly_profit_id`,`amount`,`rate`,`transaction_date`,`year`,`month`,`description`,`admin_note`,`created_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,NULL,1,NULL,'125000.00','0.0500','2026-09-17',2026,10,'مخصص إهلاك شهر 10/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-24 02:08:49','2026-09-24 02:08:49'),
(2,NULL,1,4,'150000.00','0.0500','2026-09-17',2026,11,'مخصص إهلاك شهر 11/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-24 02:19:06','2026-09-24 02:19:06'),
(3,NULL,1,NULL,'125000.00','0.0500','2026-09-17',2026,10,'مخصص إهلاك شهر 10/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-24 02:19:21','2026-09-24 02:19:21'),
(4,NULL,1,6,'150000.00','0.0500','2026-09-17',2026,10,'مخصص إهلاك شهر 10/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-24 02:20:16','2026-09-24 02:20:16'),
(5,NULL,1,1,'150000.00','0.0500','2026-09-20',2026,9,'مخصص إهلاك شهر 9/2026','تم إنشاء المخصص تلقائياً أثناء إعادة التسوية المالية.',1,'2026-09-24 10:26:09','2026-09-24 10:26:09'),
(6,NULL,1,7,'200000.00','0.0500','2026-09-17',2026,12,'مخصص إهلاك شهر 12/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-26 01:38:28','2026-09-26 01:38:28'),
(7,NULL,1,8,'150000.00','0.0500','2026-09-17',2026,8,'مخصص إهلاك شهر 8/2026','تم إنشاء مخصص الإهلاك تلقائياً من فترة الأرباح.',1,'2026-09-26 11:02:59','2026-09-26 11:02:59');

-- ----------------------------
-- table `distribution_rules` (2 rows)
-- ----------------------------
INSERT INTO `distribution_rules` (`id`,`effective_from`,`effective_to`,`management_fee_rate`,`depreciation_fund_rate`,`growth_fund_rate`,`incentive_fund_rate`,`distributed_share_rate`,`status`,`is_default`,`notes`,`created_by_admin_id`,`approved_by_admin_id`,`approved_at`,`created_at`,`updated_at`) VALUES
(1,'2026-01-01','2027-01-01','0.2500','0.0500','0.0250','0.0250','0.6500','draft',1,'مشارك',1,NULL,NULL,'2026-09-20 15:55:00','2026-09-20 16:23:39'),
(2,'2026-09-20',NULL,'0.4000','0.0500','0.0250','0.0250','0.5000','draft',0,'مثتثمر',1,NULL,NULL,'2026-09-20 16:22:45','2026-09-20 16:23:24');

-- table `failed_jobs` is empty

-- ----------------------------
-- table `funds` (5 rows)
-- ----------------------------
INSERT INTO `funds` (`id`,`code`,`name`,`current_balance`,`status`,`description`,`created_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,'1000','صندوق الاهلاك','800000.00','active',NULL,1,'2026-09-20 15:57:39','2026-09-26 11:02:59'),
(2,'1001','صندوق معدل النمو','400000.00','active',NULL,1,'2026-09-20 15:58:02','2026-09-26 11:02:59'),
(3,'1002','صندوق حافز مشارك','400000.00','active',NULL,1,'2026-09-20 15:58:24','2026-09-26 11:02:59'),
(4,'1003','نسبه اداره راس المال','4000000.00','active',NULL,1,'2026-09-20 16:07:05','2026-09-26 11:02:59'),
(5,'management_fund','حساب الإدارة','0.00','active','حصة الإدارة الشهرية (25%) تُغذى تلقائياً عند اعتماد الأرباح',NULL,'2026-09-21 21:49:44','2026-09-21 21:49:44');

-- ----------------------------
-- table `fund_transactions` (20 rows)
-- ----------------------------
INSERT INTO `fund_transactions` (`id`,`fund_id`,`monthly_profit_id`,`transaction_type`,`transaction_date`,`amount`,`resulting_balance`,`reference`,`description`,`notes`,`created_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,4,4,'deposit','2026-09-23','750000.00','750000.00','PROFIT-4','تحويل من أرباح شهر 11/2026',NULL,1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(2,2,4,'deposit','2026-09-23','75000.00','75000.00','PROFIT-4','تحويل من أرباح شهر 11/2026',NULL,1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(3,3,4,'deposit','2026-09-23','75000.00','75000.00','PROFIT-4','تحويل من أرباح شهر 11/2026',NULL,1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(4,1,4,'deposit','2026-09-23','150000.00','150000.00','PROFIT-4','تحويل من أرباح شهر 11/2026',NULL,1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(5,4,6,'deposit','2026-09-23','750000.00','1500000.00','PROFIT-6','تحويل من أرباح شهر 10/2026',NULL,1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(6,2,6,'deposit','2026-09-23','75000.00','150000.00','PROFIT-6','تحويل من أرباح شهر 10/2026',NULL,1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(7,3,6,'deposit','2026-09-23','75000.00','150000.00','PROFIT-6','تحويل من أرباح شهر 10/2026',NULL,1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(8,1,6,'deposit','2026-09-23','150000.00','300000.00','PROFIT-6','تحويل من أرباح شهر 10/2026',NULL,1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(9,4,1,'deposit','2026-09-20','750000.00','2250000.00','PROFIT-1','تحويل من أرباح شهر 9/2026 (إعادة تسوية)',NULL,1,'2026-09-24 10:26:09','2026-09-24 10:26:09'),
(10,2,1,'deposit','2026-09-20','75000.00','225000.00','PROFIT-1','تحويل من أرباح شهر 9/2026 (إعادة تسوية)',NULL,1,'2026-09-24 10:26:09','2026-09-24 10:26:09'),
(11,3,1,'deposit','2026-09-20','75000.00','225000.00','PROFIT-1','تحويل من أرباح شهر 9/2026 (إعادة تسوية)',NULL,1,'2026-09-24 10:26:09','2026-09-24 10:26:09'),
(12,1,1,'deposit','2026-09-20','150000.00','450000.00','PROFIT-1','تحويل من أرباح شهر 9/2026 (إعادة تسوية)',NULL,1,'2026-09-24 10:26:09','2026-09-24 10:26:09'),
(13,4,7,'deposit','2026-09-25','1000000.00','3250000.00','PROFIT-7','تحويل من أرباح شهر 12/2026',NULL,1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(14,2,7,'deposit','2026-09-25','100000.00','325000.00','PROFIT-7','تحويل من أرباح شهر 12/2026',NULL,1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(15,3,7,'deposit','2026-09-25','100000.00','325000.00','PROFIT-7','تحويل من أرباح شهر 12/2026',NULL,1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(16,1,7,'deposit','2026-09-25','200000.00','650000.00','PROFIT-7','تحويل من أرباح شهر 12/2026',NULL,1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(17,4,8,'deposit','2026-09-26','750000.00','4000000.00','PROFIT-8','تحويل من أرباح شهر 8/2026',NULL,1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(18,2,8,'deposit','2026-09-26','75000.00','400000.00','PROFIT-8','تحويل من أرباح شهر 8/2026',NULL,1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(19,3,8,'deposit','2026-09-26','75000.00','400000.00','PROFIT-8','تحويل من أرباح شهر 8/2026',NULL,1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(20,1,8,'deposit','2026-09-26','150000.00','800000.00','PROFIT-8','تحويل من أرباح شهر 8/2026',NULL,1,'2026-09-26 11:02:59','2026-09-26 11:02:59');

-- ----------------------------
-- table `investments` (1 rows)
-- ----------------------------
INSERT INTO `investments` (`id`,`participant_id`,`amount`,`invested_at`,`status`,`notes`,`created_by_admin_id`,`created_at`,`updated_at`,`approved_by_admin_id`,`approved_at`) VALUES
(11,4,'1000000.00','2026-09-24','approved',NULL,1,'2026-09-24 03:11:57','2026-09-24 03:12:07',1,'2026-09-24 03:12:07');

-- table `jobs` is empty

-- table `job_batches` is empty

-- ----------------------------
-- table `migrations` (22 rows)
-- ----------------------------
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_09_06_000001_create_admins_and_participants_tables',1),
(5,'2026_09_06_000002_create_investment_and_financial_core_tables',1),
(6,'2026_09_06_000003_create_funds_and_settlement_tables',1),
(7,'2026_09_06_000004_create_notifications_and_audit_tables',1),
(8,'2026_09_06_000005_create_personal_access_tokens_table',1),
(9,'2026_09_06_000006_add_admin_and_participant_role_columns',1),
(10,'2026_09_06_000007_add_approval_columns_to_investments_table',1),
(11,'2026_09_06_000008_add_distribution_rule_snapshot_to_monthly_profits',1),
(12,'2026_09_06_000009_add_operational_fields_to_fund_transactions',1),
(13,'2026_09_09_000001_create_settlement_payments_table',1),
(14,'2026_09_09_000002_add_payment_source_to_settlement_payments',1),
(15,'2026_09_09_000003_create_settlement_adjustments_table',1),
(16,'2026_09_13_000001_add_two_factor_columns_to_participants_table',1),
(17,'2026_09_13_000002_create_settlement_payment_receipts_table',1),
(18,'2026_09_13_000003_create_support_tickets_table',1),
(19,'2026_09_21_000001_add_code_to_participants_table',2),
(20,'2026_09_21_000002_make_monthly_profits_distribution_rule_id_nullable',2),
(21,'2026_09_21_000003_add_management_fund',2),
(22,'2026_09_21_000004_create_profit_projection_logs_table',2);

-- ----------------------------
-- table `monthly_profits` (5 rows)
-- ----------------------------
INSERT INTO `monthly_profits` (`id`,`capital_snapshot_id`,`distribution_rule_id`,`distribution_rule_snapshot`,`parent_id`,`year`,`month`,`version`,`status`,`gross_profit`,`management_amount`,`depreciation_amount`,`growth_amount`,`incentive_amount`,`distributed_amount`,`rounding_delta_adjustment`,`created_by_admin_id`,`approved_by_admin_id`,`approved_at`,`notes`,`created_at`,`updated_at`) VALUES
(1,1,1,'{\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\"}',NULL,2026,9,1,'approved','3000000.00','750000.00','150000.00','75000.00','75000.00','1950000.00','0.00',1,1,'2026-09-20 15:56:01',NULL,'2026-09-20 15:55:42','2026-09-24 10:26:09'),
(4,1,1,'{\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\"}',NULL,2026,11,1,'approved','3000000.00','750000.00','150000.00','75000.00','75000.00','1950000.00','0.00',1,1,'2026-09-24 02:19:47',NULL,'2026-09-24 02:19:06','2026-09-24 10:26:09'),
(6,1,1,'{\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\"}',NULL,2026,10,1,'approved','3000000.00','750000.00','150000.00','75000.00','75000.00','1950000.00','0.00',1,1,'2026-09-24 02:20:24',NULL,'2026-09-24 02:20:16','2026-09-24 10:26:09'),
(7,1,1,'{\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\"}',NULL,2026,12,1,'approved','4000000.00','1000000.00','200000.00','100000.00','100000.00','2600000.00','0.00',1,1,'2026-09-26 01:38:38',NULL,'2026-09-26 01:38:28','2026-09-26 01:38:38'),
(8,1,1,'{\"management_fee_rate\":\"0.2500\",\"depreciation_fund_rate\":\"0.0500\",\"growth_fund_rate\":\"0.0250\",\"incentive_fund_rate\":\"0.0250\",\"distributed_share_rate\":\"0.6500\"}',NULL,2026,8,1,'approved','3000000.00','750000.00','150000.00','75000.00','75000.00','1950000.00','0.00',1,1,'2026-09-26 11:02:59',NULL,'2026-09-26 11:02:59','2026-09-26 11:02:59');

-- ----------------------------
-- table `notifications` (87 rows)
-- ----------------------------
INSERT INTO `notifications` (`id`,`participant_id`,`type`,`title`,`body`,`is_read`,`metadata`,`created_by_admin_id`,`created_at`,`updated_at`) VALUES
(1,4,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":9}',NULL,'2026-09-17 15:17:59','2026-09-17 15:17:59'),
(2,5,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":8}',NULL,'2026-09-17 15:18:02','2026-09-17 15:18:02'),
(3,8,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":7}',NULL,'2026-09-17 15:18:07','2026-09-17 15:18:07'),
(4,7,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":6}',NULL,'2026-09-17 15:18:12','2026-09-17 15:18:12'),
(5,10,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":5}',NULL,'2026-09-17 15:18:16','2026-09-17 15:18:16'),
(6,9,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":4}',NULL,'2026-09-17 15:18:19','2026-09-17 15:18:19'),
(7,11,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":3}',NULL,'2026-09-17 15:18:22','2026-09-17 15:18:22'),
(8,6,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":2}',NULL,'2026-09-17 15:18:25','2026-09-17 15:18:25'),
(9,4,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":1}',NULL,'2026-09-17 15:18:29','2026-09-17 15:18:29'),
(10,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(11,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(12,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(13,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(14,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(15,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(16,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(17,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(18,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(19,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-20 15:56:01'),
(20,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',1,'{\"monthly_profit_id\":1}',1,'2026-09-20 15:56:01','2026-09-22 21:53:29'),
(21,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(22,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(23,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',1,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-22 21:53:29'),
(24,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(25,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(26,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(27,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(28,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(29,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(30,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(31,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":2}',1,'2026-09-20 16:26:51','2026-09-20 16:26:51'),
(32,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(33,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(34,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(35,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(36,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(37,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(38,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(39,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(40,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(41,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(42,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":3}',1,'2026-09-24 02:09:05','2026-09-24 02:09:05'),
(43,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(44,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(45,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(46,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(47,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(48,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(49,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(50,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(51,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(52,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(53,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":4}',1,'2026-09-24 02:19:47','2026-09-24 02:19:47'),
(54,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(55,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(56,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(57,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(58,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(59,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(60,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(61,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(62,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(63,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(64,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":6}',1,'2026-09-24 02:20:24','2026-09-24 02:20:24'),
(65,4,'investment_update','تحديث الاستثمار','تم تحديث بيانات استثمارك.',0,'{\"investment_id\":11}',NULL,'2026-09-24 03:12:07','2026-09-24 03:12:07'),
(66,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(67,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(68,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(69,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(70,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(71,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(72,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(73,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(74,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(75,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(76,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":7}',1,'2026-09-26 01:38:38','2026-09-26 01:38:38'),
(77,1,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(78,2,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(79,3,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(80,4,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(81,5,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(82,6,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(83,7,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(84,8,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(85,9,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(86,10,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59'),
(87,11,'profit_update','اعتماد أرباح شهرية','تم اعتماد أرباح شهرية جديدة لحسابك.',0,'{\"monthly_profit_id\":8}',1,'2026-09-26 11:02:59','2026-09-26 11:02:59');

-- ----------------------------
-- table `participants` (11 rows)
-- ----------------------------
INSERT INTO `participants` (`id`,`first_name`,`last_name`,`username`,`code`,`email`,`password`,`status`,`two_factor_enabled`,`two_factor_enabled_at`,`role`,`permissions`,`created_by_admin_id`,`remember_token`,`deleted_at`,`created_at`,`updated_at`) VALUES
(1,'T','T','t_f913aa',NULL,'c2ffec@t.tt','$2y$12$xteptnDtSwEqKkYe98u2euPulUYI7miE./L/h7pihXZCtEYnQM05G','active',0,NULL,'participant',NULL,NULL,NULL,NULL,'2026-09-13 13:00:11','2026-09-13 13:00:11'),
(2,'test4','لاااااااااااال','superadmin',NULL,'radyibrahim777@gmail.com','$2y$12$2kgacbEshYQ2KKMOkdWOgOdZcFicuSUBltn15WQGSJXSr/VtARAsK','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-15 14:02:01','2026-09-20 14:17:04'),
(3,'تجريبي','يا دكتور','shallal',NULL,'mohamedhattia0@gmail.com','$2y$12$JSemqu9f7MsYja4p7IkhBecLNFQTX4dwcjogErfjikFPe5EGchCEm','active',1,'2026-09-22 22:31:21','participant',NULL,1,NULL,NULL,'2026-09-17 02:15:41','2026-09-22 22:31:21'),
(4,'mahmoud','elmalwany','dr.mahmoud','107','mahmoud84@orcamed.com','$2y$12$q2nqIukTN8LE9BxfRIQ7Uu59x6ylZpZlIFJcjtS5hEWSD7TAxab/C','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 14:49:24','2026-09-26 01:46:18'),
(5,'ahmed','elmalwany','en.ahmed','106','en.ahmed@orcamed.com','$2y$12$n1y6leadUB3UBxadBe9LAuY3MbvDLuSDNkx55zUI6bt3fza3WfJnm','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 14:51:37','2026-09-26 01:45:34'),
(6,'abderahman','elmalwany','dr.abderahman','105','dr.abderahman@orcamed.com','$2y$12$ODpgeTibeNcdpKA1yTUeKuh3FUFPfCNdmrmgzZhv.upgO/33CntpG','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 14:54:23','2026-09-26 01:45:07'),
(7,'mohamed','elmalwany','mr.mohamed','100','mr.mohamed@orcamed.com','$2y$12$wrg/r31CXpkhVCE79etjfuspikBn7TwTVgOmLXnXKxOfIpzUPf57C','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 14:56:51','2026-09-24 23:14:02'),
(8,'mostafa','elzatat','mr.mostafa','104','mr.mostafa@orcamed.com','$2y$12$Dv7mSIj9rbDY8Jwz7sg/rulCsgtl2X3WpTUuOSr4WyC9mHtAltHZ.','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 15:01:13','2026-09-26 01:44:44'),
(9,'Ibrahim','tayel','mr.ibrahim','103','mr.ibrahim@orcamed.com','$2y$12$jEsawZsyKmVdLSLNAjMcH.aqbG4dmgHrLgxd69DYKdvZLF1FbiZy.','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 15:02:52','2026-09-26 01:44:15'),
(10,'karim','tayel','mr.Karim','102','mr.Karim@orcamed.com','$2y$12$zBUySW8LGXXmxDmdNnOTk.FiaQHdXrsI0lpHTjdf0PvlLmUMLA136','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 15:04:45','2026-09-26 01:43:44'),
(11,'ahmed','tayel','mr.ahmed','101','mr.ahmed@orcamed.com','$2y$12$EtXU8br0X1uhyW5E30DtB.GseN/hMgw1AXIMoyauf5f8GaNfyt59G','active',0,NULL,'participant',NULL,1,NULL,NULL,'2026-09-17 15:13:42','2026-09-26 01:43:12');

-- ----------------------------
-- table `participant_fund_allocations` (110 rows)
-- ----------------------------
INSERT INTO `participant_fund_allocations` (`id`,`fund_id`,`monthly_profit_id`,`participant_id`,`amount`,`allocation_type`,`created_at`,`updated_at`) VALUES
(441,2,8,1,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(442,2,8,2,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(443,2,8,3,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(444,2,8,4,'53565.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(445,2,8,5,'2145.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(446,2,8,6,'6427.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(447,2,8,7,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(448,2,8,8,'7500.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(449,2,8,9,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(450,2,8,10,'2145.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(451,2,8,11,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(452,3,8,1,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(453,3,8,2,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(454,3,8,3,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(455,3,8,4,'53565.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(456,3,8,5,'2145.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(457,3,8,6,'6427.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(458,3,8,7,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(459,3,8,8,'7500.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(460,3,8,9,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(461,3,8,10,'2145.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(462,3,8,11,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(463,2,1,1,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(464,2,1,2,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(465,2,1,3,'0.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(466,2,1,4,'53565.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(467,2,1,5,'2145.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(468,2,1,6,'6427.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(469,2,1,7,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(470,2,1,8,'7500.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(471,2,1,9,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(472,2,1,10,'2145.00','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(473,2,1,11,'1072.50','growth','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(474,3,1,1,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(475,3,1,2,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(476,3,1,3,'0.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(477,3,1,4,'53565.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(478,3,1,5,'2145.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(479,3,1,6,'6427.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(480,3,1,7,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(481,3,1,8,'7500.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(482,3,1,9,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(483,3,1,10,'2145.00','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(484,3,1,11,'1072.50','incentive','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(485,2,6,1,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(486,2,6,2,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(487,2,6,3,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(488,2,6,4,'53565.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(489,2,6,5,'2145.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(490,2,6,6,'6427.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(491,2,6,7,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(492,2,6,8,'7500.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(493,2,6,9,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(494,2,6,10,'2145.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(495,2,6,11,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(496,3,6,1,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(497,3,6,2,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(498,3,6,3,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(499,3,6,4,'53565.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(500,3,6,5,'2145.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(501,3,6,6,'6427.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(502,3,6,7,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(503,3,6,8,'7500.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(504,3,6,9,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(505,3,6,10,'2145.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(506,3,6,11,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(507,2,4,1,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(508,2,4,2,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(509,2,4,3,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(510,2,4,4,'53565.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(511,2,4,5,'2145.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(512,2,4,6,'6427.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(513,2,4,7,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(514,2,4,8,'7500.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(515,2,4,9,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(516,2,4,10,'2145.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(517,2,4,11,'1072.50','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(518,3,4,1,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(519,3,4,2,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(520,3,4,3,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(521,3,4,4,'53565.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(522,3,4,5,'2145.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(523,3,4,6,'6427.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(524,3,4,7,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(525,3,4,8,'7500.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(526,3,4,9,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(527,3,4,10,'2145.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(528,3,4,11,'1072.50','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(529,2,7,1,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(530,2,7,2,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(531,2,7,3,'0.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(532,2,7,4,'71420.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(533,2,7,5,'2860.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(534,2,7,6,'8570.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(535,2,7,7,'1430.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(536,2,7,8,'10000.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(537,2,7,9,'1430.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(538,2,7,10,'2860.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(539,2,7,11,'1430.00','growth','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(540,3,7,1,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(541,3,7,2,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(542,3,7,3,'0.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(543,3,7,4,'71420.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(544,3,7,5,'2860.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(545,3,7,6,'8570.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(546,3,7,7,'1430.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(547,3,7,8,'10000.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(548,3,7,9,'1430.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(549,3,7,10,'2860.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(550,3,7,11,'1430.00','incentive','2026-09-26 11:03:00','2026-09-26 11:03:00');

-- ----------------------------
-- table `participant_profit_allocations` (55 rows)
-- ----------------------------
INSERT INTO `participant_profit_allocations` (`id`,`monthly_profit_id`,`participant_id`,`amount`,`share_ratio`,`status`,`created_at`,`updated_at`) VALUES
(243,8,6,'167115.00','0.0857','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(244,8,5,'55770.00','0.0286','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(245,8,11,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(246,8,9,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(247,8,10,'55770.00','0.0286','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(248,8,4,'1392690.00','0.7142','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(249,8,7,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(250,8,8,'195000.00','0.1000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(251,8,1,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(252,8,2,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(253,8,3,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(254,1,6,'167115.00','0.0857','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(255,1,5,'55770.00','0.0286','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(256,1,11,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(257,1,9,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(258,1,10,'55770.00','0.0286','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(259,1,4,'1392690.00','0.7142','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(260,1,7,'27885.00','0.0143','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(261,1,8,'195000.00','0.1000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(262,1,1,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(263,1,2,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(264,1,3,'0.00','0.0000','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(265,6,6,'167115.00','0.0857','approved','2026-09-26 11:02:59','2026-09-26 11:02:59'),
(266,6,5,'55770.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(267,6,11,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(268,6,9,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(269,6,10,'55770.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(270,6,4,'1392690.00','0.7142','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(271,6,7,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(272,6,8,'195000.00','0.1000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(273,6,1,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(274,6,2,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(275,6,3,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(276,4,6,'167115.00','0.0857','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(277,4,5,'55770.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(278,4,11,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(279,4,9,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(280,4,10,'55770.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(281,4,4,'1392690.00','0.7142','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(282,4,7,'27885.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(283,4,8,'195000.00','0.1000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(284,4,1,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(285,4,2,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(286,4,3,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(287,7,6,'222820.00','0.0857','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(288,7,5,'74360.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(289,7,11,'37180.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(290,7,9,'37180.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(291,7,10,'74360.00','0.0286','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(292,7,4,'1856920.00','0.7142','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(293,7,7,'37180.00','0.0143','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(294,7,8,'260000.00','0.1000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(295,7,1,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(296,7,2,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00'),
(297,7,3,'0.00','0.0000','approved','2026-09-26 11:03:00','2026-09-26 11:03:00');

-- table `password_reset_tokens` is empty

-- ----------------------------
-- table `personal_access_tokens` (11 rows)
-- ----------------------------
INSERT INTO `personal_access_tokens` (`id`,`tokenable_type`,`tokenable_id`,`name`,`token`,`abilities`,`last_used_at`,`expires_at`,`created_at`,`updated_at`) VALUES
(2,'App\\Models\\Participant',2,'participant-api','03979353c7a00d6977061ca0fe774ee8c9379f8af3211d56fdd1ec5fe5efc0ec','[\"*\"]',NULL,NULL,'2026-09-16 04:58:31','2026-09-16 04:58:31'),
(7,'App\\Models\\Participant',3,'participant-api','37c5ec7492fd9df3400e02e734070ae1b2a603cc5c64eba9fefefcaa6e38a85c','[\"*\"]',NULL,NULL,'2026-09-19 14:27:35','2026-09-19 14:27:35'),
(8,'App\\Models\\Participant',2,'participant-api','fccfa561b339f40cccfdad1cde26788eeca5ebf54df2de8a7b00d00f2fa71efc','[\"*\"]',NULL,NULL,'2026-09-19 15:19:25','2026-09-19 15:19:25'),
(9,'App\\Models\\Participant',3,'participant-api','76eb64f5b99be295dc46cf2d5d12705b814a9b3c4d1fa0eff48b5ee5c38c5040','[\"*\"]',NULL,NULL,'2026-09-19 15:30:41','2026-09-19 15:30:41'),
(10,'App\\Models\\Participant',3,'participant-api','70b5effc760a6ec926d3ba38e52040fd96689ab5ff0ff608a29baa599401c67f','[\"*\"]',NULL,NULL,'2026-09-19 15:31:00','2026-09-19 15:31:00'),
(11,'App\\Models\\Participant',3,'participant-api','2863b6c4428eb9628093ee129c93c1173b3c0c771c90fa29deb2d2b1a41658ea','[\"*\"]',NULL,NULL,'2026-09-20 02:54:40','2026-09-20 02:54:40'),
(12,'App\\Models\\Participant',3,'participant-api','0b645c7019426e7229cee821b8da606f3a77c2fbbbe78d00474f712aa5ae63ee','[\"*\"]',NULL,NULL,'2026-09-22 09:52:51','2026-09-22 09:52:51'),
(13,'App\\Models\\Participant',3,'participant-api','d54b5f215d9651ec48b3ec930cf2029495d4fd44cdb82440d6729e8770bb778c','[\"*\"]',NULL,NULL,'2026-09-22 21:18:46','2026-09-22 21:18:46'),
(14,'App\\Models\\Participant',3,'participant-api','f0ee54db8e866319e86b63cae2e14fd97b5e7c8709fb8e36728b78cbc2a0849b','[\"*\"]',NULL,NULL,'2026-09-23 19:31:17','2026-09-23 19:31:17'),
(15,'App\\Models\\Participant',3,'participant-api','b7af26dff0267bf7d30391ea0f25771f013a3310117f5de84fc13d4a14db2724','[\"*\"]',NULL,NULL,'2026-09-23 20:28:21','2026-09-23 20:28:21'),
(16,'App\\Models\\Participant',3,'participant-api','fab1f093aed46c887184c3b6290aef96193c65ecd0d01d0c697b22696f099738','[\"*\"]',NULL,NULL,'2026-09-23 21:26:54','2026-09-23 21:26:54');

-- table `profit_projection_logs` is empty

-- ----------------------------
-- table `refresh_tokens` (15 rows)
-- ----------------------------
INSERT INTO `refresh_tokens` (`id`,`tokenable_type`,`tokenable_id`,`family`,`token_hash`,`expires_at`,`revoked_at`,`replaced_by_token_id`,`last_used_at`,`created_at`,`updated_at`) VALUES
(1,'App\\Models\\Participant',2,'111dd3d7-ba72-4fed-a01b-b8a4b4f39b6e','80f7c43b2070669c1d4ed31e95c8f05b9292796192415f966e71c12ed1cc6c51','2026-09-23 04:58:31',NULL,NULL,'2026-09-16 04:58:31','2026-09-16 04:58:31','2026-09-16 04:58:31'),
(2,'App\\Models\\Participant',3,'9f081be6-3ca2-4442-8084-781ffbbe4a81','481d1290bb439991e15ae1acfaac8cc47c595a965c5f68cdea5728ab50df8791','2026-09-24 17:36:58','2026-09-19 14:27:20',NULL,'2026-09-17 17:36:58','2026-09-17 17:36:58','2026-09-19 14:27:20'),
(3,'App\\Models\\Participant',3,'2e733b31-e491-4c6c-8852-da2822f54e3d','d3874daad742f81edff5855273740ab3bb6f34201e731adcb3b9ea74692eac05','2026-09-26 11:05:22','2026-09-19 14:27:20',NULL,'2026-09-19 11:05:22','2026-09-19 11:05:22','2026-09-19 14:27:20'),
(4,'App\\Models\\Participant',3,'ada92d23-e727-47e2-9a13-4d42d7bc0bed','9b03f73adee35b5c7b917c352312c7ead4a96ff54bb6f60bc1dcd970344688ca','2026-09-26 11:33:12','2026-09-19 14:27:20',NULL,'2026-09-19 11:33:12','2026-09-19 11:33:12','2026-09-19 14:27:20'),
(5,'App\\Models\\Participant',3,'0008d65f-0ebd-489a-a0a7-2ade2e8f4837','7e39a3e14bf2aa47333ac743d7ab0e1a42a60d7f02d93e7240ba566953eccb30','2026-09-26 12:47:39','2026-09-19 14:27:20',NULL,'2026-09-19 12:47:39','2026-09-19 12:47:39','2026-09-19 14:27:20'),
(6,'App\\Models\\Participant',3,'e7c971f3-329d-44ba-b1a9-85d382d55872','efdc0c364db348c27cc51c8ccce2001ad1f4d7cfe337806541b24f689014885d','2026-09-26 14:27:35',NULL,NULL,'2026-09-19 14:27:35','2026-09-19 14:27:35','2026-09-19 14:27:35'),
(7,'App\\Models\\Participant',2,'139d868b-681a-4983-871a-957a6fe528c4','7e67da76cd189119a88554939693a0fe43cfda822270eb5be471021fc2088fe8','2026-09-26 15:19:25',NULL,NULL,'2026-09-19 15:19:25','2026-09-19 15:19:25','2026-09-19 15:19:25'),
(8,'App\\Models\\Participant',3,'ae533919-9ca6-421f-ae6c-3804f0a13bfb','15ce57c827767d6225af789dc5365e7c17184ce952d10a3919b18064de1c0681','2026-09-26 15:30:41',NULL,NULL,'2026-09-19 15:30:41','2026-09-19 15:30:41','2026-09-19 15:30:41'),
(9,'App\\Models\\Participant',3,'51412846-8a83-4b1a-8906-e803f6ca71c4','9529f99cd1c009c538ae861ba8d11c0e134e6e29919913cda7166ac66ee96fd9','2026-09-26 15:31:00',NULL,NULL,'2026-09-19 15:31:00','2026-09-19 15:31:00','2026-09-19 15:31:00'),
(10,'App\\Models\\Participant',3,'81577ea2-ead6-4492-ba41-285083eec726','d97bf3ed04d131baa052305a4f7c4070ac027866831d27553e042a6b391e2f55','2026-09-27 02:54:40',NULL,NULL,'2026-09-20 02:54:40','2026-09-20 02:54:40','2026-09-20 02:54:40'),
(11,'App\\Models\\Participant',3,'c4a6b3a0-1fa1-4bf6-b067-747b25bfd2f8','53251f4c58a9ddbba91465be19776b015f57a3448daff0871eded3d5bcf56fb4','2026-09-29 09:52:51',NULL,NULL,'2026-09-22 09:52:51','2026-09-22 09:52:51','2026-09-22 09:52:51'),
(12,'App\\Models\\Participant',3,'735016ef-c990-4399-b78a-42683dd04c22','bf1891c24c7958d8f66e7600d1d44a168542d45b67fc8ec60b9a639b2d1fc66c','2026-09-29 21:18:46',NULL,NULL,'2026-09-22 21:18:46','2026-09-22 21:18:46','2026-09-22 21:18:46'),
(13,'App\\Models\\Participant',3,'509253aa-c53a-42e7-b421-b1c5998522f8','427039ae41f0d7295c81e0302ee700e4b21c84feecefddb890d555bce064af7c','2026-09-30 19:31:17',NULL,NULL,'2026-09-23 19:31:17','2026-09-23 19:31:17','2026-09-23 19:31:17'),
(14,'App\\Models\\Participant',3,'e1fe6454-f5ce-4b2f-889b-3456c50e88e8','bd5d59578d03bc8b2de8c6cc821bc59d0f474e05a2f169b38e76bc0cf7c7175a','2026-09-30 20:28:21',NULL,NULL,'2026-09-23 20:28:21','2026-09-23 20:28:21','2026-09-23 20:28:21'),
(15,'App\\Models\\Participant',3,'ae6cddc6-ae97-44a2-8d58-69ec0e2ab9fb','5256190bb50de344d0774074c8b5773cac8457d65c7980d3de04b2ed5f7089b8','2026-09-30 21:26:54',NULL,NULL,'2026-09-23 21:26:54','2026-09-23 21:26:54','2026-09-23 21:26:54');

-- ----------------------------
-- table `sessions` (11 rows)
-- ----------------------------
INSERT INTO `sessions` (`id`,`user_id`,`ip_address`,`user_agent`,`payload`,`last_activity`) VALUES
('6P4sRuyDWFZnYz0oNXlo8aseKzOHcndsuSWar8GP',NULL,'178.212.56.70','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiYmlSZURSUXhDSmxFZlY0NG1DRnpaUlRVVnRHTno3NU1iRFIzbzZ3RiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MTE6ImFkbWluLmxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790376421),
('fELU0p5wDLdOdgK2sshsCpVZ1oQuvuo4MqFvIv0u',NULL,'197.43.147.89','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiY1pwOTBuUVlmQlFONVR0eHRqZTRjOVkzMm1oQXN3QkVLeXhPRzI0NCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MTE6ImFkbWluLmxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790410792),
('IbCG5Yfbc1nBSP382pjQcJrwtAxe1B9UYfjmb5oa',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiOWtSdEhHN1llbHlaSGVRZjY2QkM5bDVRRjMxcEdBZ3NoVnA5Y0VQWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9sb2dpbiI7czo1OiJyb3V0ZSI7czoxMToiYWRtaW4ubG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1790419956),
('NiOPRF0JMvI2YqI2lm5aqWD8xBAmOdfJoJ2MiUfT',NULL,'54.159.10.96','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoibUdNd016ZjVZOVZhVjQ4Vk01a0hRaDZEeGNEcFd1Sm5YbUtmM29pTSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjU6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790408652),
('QOgMjG5CnroeJGbDvs5DWqS2oPvFLdthyy71R7tq',NULL,'66.29.156.92','Mozilla/5.0 (compatible; ResearchScanBot/0.2; non-commercial web research)','YTozOntzOjY6Il90b2tlbiI7czo0MDoiOFB1YThjOWF5Y2hjZnVkaDVaV2R2Q1lUdlBQbUlBZnZMMklyblR3SCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MTE6ImFkbWluLmxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790389702),
('r7eKGMxRCg5D6S80cEx67701unaMP7Bw8EmeiNO7',NULL,'45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','YTozOntzOjY6Il90b2tlbiI7czo0MDoiNXFYSFJhNzBzOHRhdGpPR3F4ZXhXdzk3M2RUcUtzYWQ5SmY4VnJOaSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MTE6ImFkbWluLmxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790375199),
('rDpWfLwgC3QrCGcXU5VCORmVn0I5ZNqExCY7fybg',NULL,'45.96.243.196','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.6 Mobile/15E148 Safari/604.1 OPT/6.7.3','YTo0OntzOjY6Il90b2tlbiI7czo0MDoicWVpZ0tPMjhYaUpEc0JNV0ppbHlxMm5KNEFvNFFmTjBoVTRGR2l5NCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbW9udGhseS1wcm9maXRzIjtzOjU6InJvdXRlIjtzOjIxOiJhZG1pbi5tb250aGx5LXByb2ZpdHMiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjEyOiJ3ZWJfYWRtaW5faWQiO2k6MTt9',1790376875),
('u4JHan1FfVqkf8AnEbJ8B4jIYsC9nv0GIk4ZUc6H',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoibkNka0RreHpraWpTeDZhWFhHQTVpVEhsQ2F6amJ5dkp6Q1V3SmNNSyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzk6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9pbnZlc3RtZW50cyI7czo1OiJyb3V0ZSI7czoxNzoiYWRtaW4uaW52ZXN0bWVudHMiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjEyOiJ3ZWJfYWRtaW5faWQiO2k6MTt9',1790421240),
('uwvcyUKhun3OzUnh3HlwLUjw7ZoTWjoJGElNMzhw',NULL,'197.43.147.89','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiY2lHT09Gbm9GYjNQclY3ZGNqZFR5REU1U1B1UVpKajdyMGs4VUI2YyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDM6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vaW52ZXN0bWVudHMiO3M6NToicm91dGUiO3M6MTc6ImFkbWluLmludmVzdG1lbnRzIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czoxMjoid2ViX2FkbWluX2lkIjtpOjE7fQ==',1790410943),
('WJO75kckAWueGsIHSFqn49lIqEeUPw7BjmmGwG1y',NULL,'66.29.156.92','Mozilla/5.0 (compatible; ResearchScanBot/0.2; non-commercial web research)','YTozOntzOjY6Il90b2tlbiI7czo0MDoieXFNcVJMUWw2YlhlOHlhSTRyRzRrbFhVazNtVEV6bmdHQ2VHejk0UCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjU6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790389701),
('YOHbHxgLvGNZMSgw5Q5zp49SKhG8Cfpnym2lxwPX',NULL,'54.159.10.96','Mozilla/5.0 (iPhone; CPU iPhone OS 18_7_8 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVFFuNEhzUXhpNTRQUVB4RWpNd0tqdU4zQWJpREVlQXFUTjVtWG1XciI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzc6Imh0dHBzOi8vb3JjYW1lZGxsYy5vbmxpbmUvYWRtaW4vbG9naW4iO3M6NToicm91dGUiO3M6MTE6ImFkbWluLmxvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1790408652);

-- ----------------------------
-- table `settlements` (1 rows)
-- ----------------------------
INSERT INTO `settlements` (`id`,`parent_id`,`year`,`version`,`status`,`total_distributed_amount`,`participant_profit_share`,`participant_fund_share`,`net_payable`,`amount_due`,`paid_amount`,`created_by_admin_id`,`approved_by_admin_id`,`paid_by_admin_id`,`approved_at`,`payout_at`,`notes`,`created_at`,`updated_at`) VALUES
(1,NULL,2026,1,'draft','10400000.00','10400000.00','0.00','10400000.00','10400000.00','0.00',1,NULL,NULL,NULL,NULL,'Amount due equals approved annual participant profit plus approved participant fund allocations. Principal remains excluded. Previous payments are recorded separately in settlement_payments.','2026-09-20 15:56:16','2026-09-26 11:03:00');

-- table `settlement_adjustments` is empty

-- ----------------------------
-- table `settlement_items` (11 rows)
-- ----------------------------
INSERT INTO `settlement_items` (`id`,`settlement_id`,`participant_id`,`profit_share`,`fund_share`,`net_payable`,`payment_status`,`paid_amount`,`paid_at`,`created_at`,`updated_at`) VALUES
(56,1,1,'0.00','0.00','0.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(57,1,2,'0.00','0.00','0.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(58,1,3,'0.00','0.00','0.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(59,1,4,'7427680.00','0.00','7427680.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(60,1,5,'297440.00','0.00','297440.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(61,1,6,'891280.00','0.00','891280.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(62,1,7,'148720.00','0.00','148720.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(63,1,8,'1040000.00','0.00','1040000.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(64,1,9,'148720.00','0.00','148720.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(65,1,10,'297440.00','0.00','297440.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00'),
(66,1,11,'148720.00','0.00','148720.00','pending','0.00',NULL,'2026-09-26 11:03:00','2026-09-26 11:03:00');

-- table `settlement_payments` is empty

-- table `settlement_payment_receipts` is empty

-- ----------------------------
-- table `support_tickets` (1 rows)
-- ----------------------------
INSERT INTO `support_tickets` (`id`,`participant_id`,`subject`,`message`,`category`,`status`,`resolved_by_admin_id`,`resolved_at`,`created_at`,`updated_at`) VALUES
(1,3,'تتتتتتوتو','تستظمظزززيزبزرزر',NULL,'open',NULL,NULL,'2026-09-22 21:57:41','2026-09-22 21:57:41');

-- table `users` is empty

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
