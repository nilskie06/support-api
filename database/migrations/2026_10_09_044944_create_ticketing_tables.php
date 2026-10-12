<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        foreach ($this->createTableStatements() as $statement) {
            $connection->unprepared($statement);
        }

        if (Schema::hasTable('users')) {
            $connection->statement(<<<'SQL'
                ALTER TABLE `tkt_group_user`
                ADD CONSTRAINT `tkt_group_user_user_id_foreign`
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                SQL);
        }
    }

    public function down(): void
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_reverse($this->tableNames()) as $tableName) {
                $connection->statement("DROP TABLE IF EXISTS `{$tableName}`");
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return array<int, string>
     */
    private function createTableStatements(): array
    {
        return [
            <<<'SQL'
                CREATE TABLE `tkt_ai_chat_memories` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `topic` varchar(255) NOT NULL,
                  `content` text NOT NULL,
                  `usage_count` int(11) NOT NULL DEFAULT 1,
                  `confidence_score` int(11) NOT NULL DEFAULT 50,
                  `source_ticket_id` bigint(20) unsigned DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_boards` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `status` tinyint(1) NOT NULL DEFAULT 1,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `created_by` bigint(20) NOT NULL,
                  `updated_by` bigint(20) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_board_status` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `order` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `color` varchar(255) NOT NULL,
                  `type` varchar(255) DEFAULT NULL,
                  `is_required` tinyint(4) NOT NULL DEFAULT 0,
                  `has_next_step` tinyint(1) NOT NULL DEFAULT 1,
                  `notify_creator` tinyint(4) NOT NULL DEFAULT 0,
                  `notify_followers` tinyint(4) NOT NULL DEFAULT 0,
                  `notify_assignees` tinyint(4) NOT NULL DEFAULT 0,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `board_id` bigint(20) NOT NULL,
                  `created_by` bigint(20) NOT NULL,
                  `updated_by` bigint(20) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `id` (`id`,`board_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_canned_responses` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `title` varchar(120) NOT NULL,
                  `body` text NOT NULL,
                  `issue_type_id` bigint(20) unsigned DEFAULT NULL,
                  `created_by` bigint(20) unsigned NOT NULL,
                  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
                  `use_count` int(10) unsigned NOT NULL DEFAULT 0,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_canned_responses_created_by_is_shared_index` (`created_by`,`is_shared`),
                  KEY `tkt_canned_responses_issue_type_id_index` (`issue_type_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_closing_tickets` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `close_reason` text DEFAULT NULL,
                  `cancel_reason` text DEFAULT NULL,
                  `status` tinyint(4) NOT NULL DEFAULT 1,
                  `system_ticket_id` bigint(20) NOT NULL,
                  `job_id` bigint(20) DEFAULT NULL,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `closed_by` bigint(20) NOT NULL,
                  `created_by` bigint(20) NOT NULL,
                  `updated_by` bigint(20) NOT NULL,
                  `closing_at` datetime NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_department_approver` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `department_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `created_by` bigint(20) unsigned DEFAULT NULL,
                  `updated_by` bigint(20) unsigned DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_department_approver_department_id_index` (`department_id`),
                  KEY `tkt_department_approver_user_id_index` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_groups` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `department_id` bigint(20) DEFAULT NULL,
                  `approver_id` bigint(20) unsigned DEFAULT NULL,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `created_by` bigint(20) DEFAULT NULL,
                  `updated_by` bigint(20) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_group_approver` (
                  `group_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`group_id`,`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_group_company` (
                  `group_id` bigint(20) unsigned NOT NULL,
                  `company_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`group_id`,`company_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_group_tagged_department` (
                  `group_id` bigint(20) unsigned NOT NULL,
                  `department_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`group_id`,`department_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_group_tagged_group` (
                  `group_id` bigint(20) unsigned NOT NULL,
                  `tagged_group_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`group_id`,`tagged_group_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_group_user` (
                  `group_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  KEY `tkt_group_user_group_id_foreign` (`group_id`),
                  KEY `tkt_group_user_user_id_foreign` (`user_id`),
                  CONSTRAINT `tkt_group_user_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `tkt_groups` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_issue_severities` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `color` varchar(255) DEFAULT NULL,
                  `enable` tinyint(1) NOT NULL DEFAULT 1,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `issue_severities_name_unique` (`name`) USING HASH
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_issue_statuses` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `order` int(11) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `color` varchar(255) DEFAULT NULL,
                  `require_remarks` tinyint(1) NOT NULL DEFAULT 0,
                  `enable` tinyint(1) NOT NULL DEFAULT 1,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `issue_statuses_name_unique` (`name`),
                  KEY `issue_statuses_order_index` (`order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_issue_types` (
                  `issue_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `issue_desc` varchar(50) DEFAULT NULL,
                  `color` varchar(255) DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`issue_id`),
                  UNIQUE KEY `issue_types_issue_desc_unique` (`issue_desc`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_menus` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `parent_id` bigint(20) unsigned DEFAULT 0,
                  `code` varchar(255) NOT NULL,
                  `name` varchar(255) NOT NULL,
                  `icon` varchar(255) DEFAULT NULL,
                  `url` varchar(255) DEFAULT NULL,
                  `order` int(11) NOT NULL DEFAULT 0,
                  `status` tinyint(1) NOT NULL DEFAULT 1,
                  `is_side_menu` tinyint(1) NOT NULL DEFAULT 1,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `tkt_menus_code_unique` (`code`) USING HASH
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_resolution` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `ticket_id` int(11) NOT NULL,
                  `resolution` text DEFAULT NULL,
                  `root_cause` text DEFAULT NULL,
                  `prevent_recurrence` text DEFAULT NULL,
                  `created_by` int(11) NOT NULL,
                  `updated_by` int(11) NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_tickets` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `ref_no` varchar(255) NOT NULL,
                  `subject` varchar(255) NOT NULL,
                  `summary` longtext NOT NULL,
                  `date_encountered` date NOT NULL,
                  `frequency` varchar(20) NOT NULL,
                  `duration_from` date DEFAULT NULL,
                  `duration_to` date DEFAULT NULL,
                  `url` varchar(255) DEFAULT NULL,
                  `system` varchar(10) DEFAULT NULL,
                  `module` varchar(255) DEFAULT NULL COMMENT 'code from menu',
                  `issue_group` varchar(10) DEFAULT NULL,
                  `order` int(11) DEFAULT NULL,
                  `board_status_id` bigint(20) DEFAULT NULL,
                  `parent_id` bigint(20) unsigned DEFAULT NULL,
                  `issue_type_id` bigint(20) NOT NULL,
                  `issue_severity_id` bigint(20) DEFAULT NULL,
                  `department_id` bigint(20) unsigned DEFAULT NULL,
                  `group_id` int(11) DEFAULT NULL,
                  `company_id` bigint(20) DEFAULT NULL,
                  `is_archived` tinyint(4) NOT NULL DEFAULT 0,
                  `status` tinyint(4) NOT NULL DEFAULT 1,
                  `closed_by` bigint(20) DEFAULT NULL,
                  `closed_at` datetime DEFAULT NULL,
                  `last_followed_up_at` timestamp NULL DEFAULT NULL,
                  `follow_up_count` int(10) unsigned NOT NULL DEFAULT 0,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `created_by` bigint(20) NOT NULL,
                  `updated_by` bigint(20) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `system_tickets_ref_no_unique` (`ref_no`) USING HASH,
                  KEY `system_tickets_module_index` (`module`(250)),
                  KEY `system_tickets_department_id_index` (`department_id`),
                  KEY `board_status_id` (`board_status_id`,`issue_type_id`,`issue_severity_id`,`group_id`,`company_id`),
                  KEY `status` (`status`),
                  KEY `closed_at` (`closed_at`,`created_at`),
                  KEY `date_encountered` (`date_encountered`),
                  KEY `tkt_system_tickets_parent_id_index` (`parent_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_assignee` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_system_ticket_assignee_system_ticket_id_index` (`system_ticket_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_attachments` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `comment_id` bigint(20) unsigned DEFAULT NULL,
                  `resolution_id` bigint(20) unsigned DEFAULT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  `path` varchar(255) NOT NULL,
                  `filename` varchar(255) NOT NULL,
                  `extension` varchar(255) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `system_ticket_attachments_system_ticket_id_index` (`system_ticket_id`),
                  KEY `system_ticket_attachments_user_id_index` (`user_id`),
                  KEY `system_ticket_attachments_comment_id_index` (`comment_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_comments` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  `parent_id` bigint(20) unsigned DEFAULT NULL,
                  `message` text DEFAULT NULL,
                  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
                  `type` enum('comment','follow_up') NOT NULL DEFAULT 'comment',
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `tkt_system_ticket_comments_system_ticket_id_index` (`system_ticket_id`),
                  KEY `tkt_system_ticket_comments_user_id_index` (`user_id`),
                  KEY `tkt_system_ticket_comments_parent_id_foreign` (`parent_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_histories` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `is_status` tinyint(1) NOT NULL DEFAULT 1,
                  `status` varchar(100) DEFAULT NULL,
                  `data` longtext NOT NULL,
                  `remarks` longtext DEFAULT NULL,
                  `updated_by_id` bigint(20) unsigned NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `system_ticket_histories_system_ticket_id_index` (`system_ticket_id`),
                  KEY `system_ticket_histories_status_index` (`status`),
                  KEY `system_ticket_histories_updated_by_id_index` (`updated_by_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_logs` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `description` text NOT NULL,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `board_status_id` bigint(20) unsigned DEFAULT NULL,
                  `group_id` bigint(20) unsigned DEFAULT NULL,
                  `is_status_update` tinyint(4) NOT NULL DEFAULT 0,
                  `processed_by` bigint(20) unsigned NOT NULL,
                  `processed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  `ref_no` varchar(255) NOT NULL,
                  `subject` varchar(255) NOT NULL,
                  `summary` longtext NOT NULL,
                  `date_encountered` date NOT NULL,
                  `frequency` varchar(20) NOT NULL,
                  `duration_from` date DEFAULT NULL,
                  `duration_to` date DEFAULT NULL,
                  `url` varchar(255) DEFAULT NULL,
                  `system` varchar(10) DEFAULT NULL,
                  `module` varchar(255) DEFAULT NULL,
                  `issue_group` varchar(10) DEFAULT NULL,
                  `order` int(11) DEFAULT NULL,
                  `issue_type_id` bigint(20) NOT NULL,
                  `issue_severity_id` bigint(20) DEFAULT NULL,
                  `department_id` bigint(20) unsigned DEFAULT NULL,
                  `company_id` bigint(20) DEFAULT NULL,
                  `is_archived` tinyint(4) NOT NULL DEFAULT 0,
                  `status` tinyint(4) NOT NULL DEFAULT 1,
                  `closed_by` bigint(20) DEFAULT NULL,
                  `closed_at` datetime DEFAULT NULL,
                  `created_by` bigint(20) NOT NULL,
                  `updated_by` bigint(20) NOT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `system_ticket_logs_system_ticket_id_index` (`system_ticket_id`),
                  KEY `system_ticket_logs_board_status_id_index` (`board_status_id`),
                  KEY `system_ticket_logs_department_id_index` (`department_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_ratings` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `ticket_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  `rating` tinyint(3) unsigned NOT NULL,
                  `comment` text DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `tkt_system_ticket_ratings_ticket_id_user_id_unique` (`ticket_id`,`user_id`),
                  KEY `tkt_system_ticket_ratings_ticket_id_index` (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_reference` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `reference_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_system_ticket_reference_system_ticket_id_index` (`system_ticket_id`),
                  KEY `tkt_system_ticket_reference_reference_id_index` (`reference_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_tagged_user` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_system_ticket_tagged_user_system_ticket_id_index` (`system_ticket_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_tests` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `unique_slug` varchar(255) NOT NULL,
                  `instruction` text NOT NULL,
                  `url` varchar(255) DEFAULT NULL,
                  `tester_remarks` text DEFAULT NULL,
                  `status` tinyint(4) NOT NULL DEFAULT 1,
                  `system_ticket_id` bigint(20) NOT NULL,
                  `job_id` bigint(20) DEFAULT NULL,
                  `processed_by` bigint(20) DEFAULT NULL,
                  `processed_at` datetime DEFAULT NULL,
                  `deleted_at` timestamp NULL DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `tkt_system_ticket_tests_unique_slug_unique` (`unique_slug`) USING HASH
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_system_ticket_test_user` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `system_ticket_test_id` bigint(20) unsigned NOT NULL,
                  `user_id` bigint(20) unsigned NOT NULL,
                  PRIMARY KEY (`id`),
                  KEY `tkt_system_ticket_test_user_system_ticket_test_id_index` (`system_ticket_test_id`),
                  KEY `tkt_system_ticket_test_user_user_id_index` (`user_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_ticketing_notifications` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `description` text NOT NULL,
                  `url` text DEFAULT NULL,
                  `notification_type` tinyint(4) NOT NULL DEFAULT 1,
                  `received_by` bigint(20) NOT NULL,
                  `received_at` datetime NOT NULL,
                  `read_at` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            <<<'SQL'
                CREATE TABLE `tkt_user_notification_preferences` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` bigint(20) NOT NULL,
                  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
                  `in_app_enabled` tinyint(1) NOT NULL DEFAULT 1,
                  `web_push_enabled` tinyint(1) NOT NULL DEFAULT 1,
                  `teams_enabled` tinyint(1) NOT NULL DEFAULT 1,
                  `created_at` timestamp NULL DEFAULT NULL,
                  `updated_at` timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `tkt_user_notification_preferences_user_id_unique` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(): array
    {
        return [
            'tkt_ai_chat_memories',
            'tkt_boards',
            'tkt_board_status',
            'tkt_canned_responses',
            'tkt_closing_tickets',
            'tkt_department_approver',
            'tkt_groups',
            'tkt_group_approver',
            'tkt_group_company',
            'tkt_group_tagged_department',
            'tkt_group_tagged_group',
            'tkt_group_user',
            'tkt_issue_severities',
            'tkt_issue_statuses',
            'tkt_issue_types',
            'tkt_menus',
            'tkt_system_resolution',
            'tkt_system_tickets',
            'tkt_system_ticket_assignee',
            'tkt_system_ticket_attachments',
            'tkt_system_ticket_comments',
            'tkt_system_ticket_histories',
            'tkt_system_ticket_logs',
            'tkt_system_ticket_ratings',
            'tkt_system_ticket_reference',
            'tkt_system_ticket_tagged_user',
            'tkt_system_ticket_tests',
            'tkt_system_ticket_test_user',
            'tkt_ticketing_notifications',
            'tkt_user_notification_preferences',
        ];
    }
};
