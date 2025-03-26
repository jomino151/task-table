<?php
/**
 * Plugin Name:       Task Table
 * Description:       Creates a dynamic table to communicate tasks to multis.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Jonas Morawietz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       task-table
 *
 * @package TaskTable
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('TASK_TABLE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TASK_TABLE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once TASK_TABLE_PLUGIN_DIR . 'includes/database.php';
require_once TASK_TABLE_PLUGIN_DIR . 'includes/api.php';
require_once TASK_TABLE_PLUGIN_DIR . 'includes/admin-pages.php';

// Register activation hook
register_activation_hook(__FILE__, 'task_table_create_signups_table');

/**
 * Register the block using the metadata loaded from the `block.json` file.
 */
function task_table_register_blocks() {
    register_block_type(__DIR__ . '/build/task-table');
}
add_action('init', 'task_table_register_blocks');
