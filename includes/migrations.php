<?php
/**
 * BC Assistant - Database Migrations
 * 
 * This file handles database schema migrations when the plugin is updated.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BC_Assistant_Migrations {
    /**
     * Run migrations if needed
     */
    public static function run_migrations() {
        $db_version = get_option('bc_assistant_db_version', '0.0.0');
        $current_version = BC_ASSISTANT_VERSION;
        
        // If database version is current, no migrations needed
        if (version_compare($db_version, $current_version, '>=')) {
            return;
        }
        
        // Log migration start
        BC_Assistant_Helper::log('Starting database migration from ' . $db_version . ' to ' . $current_version);
        
        // Run migrations based on version
        if (version_compare($db_version, '1.0.0', '<')) {
            self::migrate_to_1_0_0();
        }
        
        if (version_compare($db_version, '1.0.5', '<')) {
            self::migrate_to_1_0_5();
        }
        
        if (version_compare($db_version, '1.0.7', '<')) {
            self::migrate_to_1_0_7();
        }
        
        // Update database version
        update_option('bc_assistant_db_version', $current_version);
        
        // Log migration complete
        BC_Assistant_Helper::log('Database migration completed to version ' . $current_version);
    }
    
    /**
     * Migrate to version 1.0.0
     * Initial database setup
     */
    private static function migrate_to_1_0_0() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create initial tables
        $conversations_table = "CREATE TABLE {$wpdb->prefix}bc_assistant_conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            thread_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $messages_table = "CREATE TABLE {$wpdb->prefix}bc_assistant_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role varchar(50) NOT NULL,
            content text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        
        // Load dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Create tables
        dbDelta($conversations_table);
        dbDelta($messages_table);
        
        BC_Assistant_Helper::log('Migration to 1.0.0 completed');
    }
    
    /**
     * Migrate to version 1.0.5
     * Add thread_id index to conversations table
     */
    private static function migrate_to_1_0_5() {
        global $wpdb;
        
        // Check if index already exists
        $index_exists = $wpdb->get_results(
            "SHOW INDEX FROM {$wpdb->prefix}bc_assistant_conversations WHERE Key_name = 'thread_id'"
        );
        
        if (empty($index_exists)) {
            // Add index
            $wpdb->query("ALTER TABLE {$wpdb->prefix}bc_assistant_conversations ADD INDEX thread_id (thread_id)");
        }
        
        BC_Assistant_Helper::log('Migration to 1.0.5 completed');
    }
    
    /**
     * Migrate to version 1.0.7
     * Add context and metadata fields to conversations table
     */
    private static function migrate_to_1_0_7() {
        global $wpdb;
        
        // Check if columns already exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}bc_assistant_conversations LIKE 'context'");
        
        if (empty($columns)) {
            // Add columns
            $wpdb->query("ALTER TABLE {$wpdb->prefix}bc_assistant_conversations ADD COLUMN context varchar(50) DEFAULT 'default' AFTER thread_id");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}bc_assistant_conversations ADD COLUMN metadata text DEFAULT NULL AFTER context");
        }
        
        BC_Assistant_Helper::log('Migration to 1.0.7 completed');
    }
}

// Add hook to run migrations
add_action('admin_init', array('BC_Assistant_Migrations', 'run_migrations'));