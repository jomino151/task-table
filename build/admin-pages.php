<?php
/**
 * Admin pages for Task Table plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register admin pages
 */
function task_table_register_admin_pages() {
    add_menu_page(
        __('Task Table', 'task-table'),
        __('Task Table', 'task-table'),
        'manage_task_table',
        'task-table',
        'task_table_admin_dashboard_page',
        'dashicons-list-view',
        30
    );
    
    add_submenu_page(
        'task-table',
        __('Events', 'task-table'),
        __('Events', 'task-table'),
        'manage_task_table',
        'task-table',
        'task_table_admin_dashboard_page'
    );
    
    add_submenu_page(
        'task-table',
        __('Signups', 'task-table'),
        __('Signups', 'task-table'),
        'manage_task_table',
        'task-table-signups',
        'task_table_admin_signups_page'
    );
    
    add_submenu_page(
        'task-table',
        __('Subscribers', 'task-table'),
        __('Subscribers', 'task-table'),
        'manage_task_table',
        'task-table-subscribers',
        'task_table_admin_subscribers_page'
    );
    
    add_submenu_page(
        'task-table',
        __('Email Settings', 'task-table'),
        __('Email Settings', 'task-table'),
        'manage_task_table',
        'task-table-email-settings',
        'task_table_admin_email_settings_page'
    );
}
add_action('admin_menu', 'task_table_register_admin_pages');

/**
 * Admin dashboard page
 */
function task_table_admin_dashboard_page() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'task_table_events';
    $signups_table = $wpdb->prefix . 'task_table_signups';
    // Handle archive/unarchive (nonce protected via URL)
    if (isset($_GET['action'], $_GET['event_id'], $_GET['_wpnonce'])) {
        $allowed = ['archive','unarchive'];
        $action = sanitize_text_field($_GET['action']);
        $event_id = (int)$_GET['event_id'];
        if (in_array($action, $allowed, true) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'task_table_event_'.$action.'_'.$event_id)) {
            $archived_val = $action === 'archive' ? 1 : 0;
            $wpdb->update($events_table, ['archived'=>$archived_val, 'updated_at'=>current_time('mysql')], ['id'=>$event_id]);
            echo '<div class="notice notice-success is-dismissible"><p>'.($archived_val?__('Event archived.','task-table'):__('Event restored.','task-table')).'</p></div>';
        }
    }

    $show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';
    $where = $show_archived ? 'WHERE archived = 1' : 'WHERE archived = 0';
    $events = $wpdb->get_results("SELECT * FROM $events_table $where ORDER BY created_at DESC", ARRAY_A);
    
    // Count signups
    $pending_signups = $wpdb->get_var("SELECT COUNT(*) FROM $signups_table WHERE status = 'pending'");
    
    ?>
    <div class="wrap">
        <h1><?php _e('Task Table Dashboard', 'task-table'); ?></h1>
        <p class="view-toggle-links">
            <?php if(!$show_archived): ?>
                <a href="<?php echo esc_url(add_query_arg(['archived'=>'1'])); ?>"><?php _e('View Archived Events','task-table'); ?></a>
            <?php else: ?>
                <a href="<?php echo esc_url(remove_query_arg('archived')); ?>"><?php _e('View Active Events','task-table'); ?></a>
            <?php endif; ?>
            | <?php 
                $export_args = [ 'action' => 'task_table_export_events' ];
                if ($show_archived) { $export_args['archived'] = '1'; }
                $export_url = add_query_arg($export_args, admin_url('admin-post.php'));
                $export_url = wp_nonce_url($export_url, 'task_table_export_events');
            ?>
            <a href="<?php echo esc_url($export_url); ?>" target="_blank"><?php _e('Download Excel','task-table'); ?></a>
        </p>
        
        <div class="task-table-dashboard">
            <div class="task-table-stats">
                <div class="stat-box">
                    <h3><?php _e('Events', 'task-table'); ?></h3>
                    <p class="stat-count"><?php echo count($events); ?></p>
                </div>
                
                <div class="stat-box">
                    <h3><?php _e('Pending Signups', 'task-table'); ?></h3>
                    <p class="stat-count"><?php echo intval($pending_signups); ?></p>
                    <?php if ($pending_signups > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=task-table-signups&status=pending'); ?>" class="button button-small">
                            <?php _e('Review', 'task-table'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <h2><?php echo $show_archived ? esc_html__('Archived Events','task-table') : esc_html__('Events','task-table'); ?></h2>
            
            <?php if (!empty($events)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'task-table'); ?></th>
                            <th><?php _e('Request', 'task-table'); ?></th>
                            <th><?php _e('University', 'task-table'); ?></th>
                            <th><?php _e('Time', 'task-table'); ?></th>
                            <th><?php _e('Assignee', 'task-table'); ?></th>
                            <th><?php _e('Created', 'task-table'); ?></th>
                            <th><?php _e('Actions', 'task-table'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo esc_html($event['id']); ?></td>
                                <td><?php echo esc_html($event['was_angefragt']); ?></td>
                                <td><?php echo esc_html($event['name_hochschule']); ?></td>
                                <td><?php echo esc_html($event['zeitpunkt']); ?></td>
                                <td><?php echo esc_html($event['wer_macht']); ?></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($event['created_at'])); ?></td>
                                <td>
                                    <?php if(!$show_archived): ?>
                                        <a class="button button-small" href="<?php echo wp_nonce_url(add_query_arg(['action'=>'archive','event_id'=>$event['id']]), 'task_table_event_archive_'.$event['id']); ?>" onclick="return confirm('<?php _e('Archive this event?','task-table'); ?>');"><?php _e('Archive','task-table'); ?></a>
                                    <?php else: ?>
                                        <a class="button button-small" href="<?php echo wp_nonce_url(add_query_arg(['action'=>'unarchive','event_id'=>$event['id']]), 'task_table_event_unarchive_'.$event['id']); ?>" onclick="return confirm('<?php _e('Restore this event?','task-table'); ?>');"><?php _e('Restore','task-table'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No events found.', 'task-table'); ?></p>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=task-table-signups'); ?>" class="button">
                    <?php _e('Manage Signups', 'task-table'); ?>
                </a>
            </p>
        </div>
    </div>
    <style>
        .task-table-stats {
            display: flex;
            margin-bottom: 20px;
        }
        .stat-box {
            background: white;
            border: 1px solid #ccd0d4;
            padding: 15px;
            margin-right: 15px;
            border-radius: 3px;
            min-width: 150px;
        }
        .stat-box {
            margin-top: 0;
        }
        .stat-count {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .view-toggle-links { margin:10px 0 20px; }
    </style>
    <?php
}

/**
 * Admin signups page
 */
function task_table_admin_signups_page() {
    global $wpdb;
    $signups_table = $wpdb->prefix . 'task_table_signups';
    $events_table = $wpdb->prefix . 'task_table_events';
    
    // Handle signup status changes
    if (isset($_GET['action']) && isset($_GET['signup_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_text_field($_GET['action']);
        $signup_id = intval($_GET['signup_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        if (wp_verify_nonce($nonce, 'task_table_signup_' . $action . '_' . $signup_id)) {
            $signup = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $signups_table WHERE id = %d",
                $signup_id
            ));
            
            if ($signup) {
                if ($action === 'approve') {
                    $wpdb->update(
                        $signups_table,
                        array('status' => 'approved', 'updated_at' => current_time('mysql')),
                        array('id' => $signup_id)
                    );
                    
                    task_table_send_approval_notification($signup);
                    
                    // Update the event's wer_macht field
                    $wpdb->update(
                        $events_table,
                        array('wer_macht' => $signup->user_name),
                        array('id' => $signup->event_id)
                    );
                    
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Signup approved.', 'task-table') . '</p></div>';
                } elseif ($action === 'deny') {
                    $wpdb->update(
                        $signups_table,
                        array('status' => 'denied', 'updated_at' => current_time('mysql')),
                        array('id' => $signup_id)
                    );
                    
                    task_table_send_denial_notification($signup);
                    
                    echo '<div class="notice notice-info is-dismissible"><p>' . __('Signup denied.', 'task-table') . '</p></div>';
                } elseif ($action === 'delete') {
                    $wpdb->delete($signups_table, array('id' => $signup_id));
                    echo '<div class="notice notice-info is-dismissible"><p>' . __('Signup deleted.', 'task-table') . '</p></div>';
                }
            }
        }
    }
    
    // Filter by status
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $where_clause = '';
    
    if ($status_filter) {
        $where_clause = $wpdb->prepare("WHERE s.status = %s", $status_filter);
    }
    
    // Get signups with event details
    $signups = $wpdb->get_results(
        "SELECT s.*, e.was_angefragt, e.name_hochschule, e.zeitpunkt 
         FROM $signups_table s
         LEFT JOIN $events_table e ON s.event_id = e.id
         $where_clause
         ORDER BY s.created_at DESC"
    );
    
    ?>
    <div class="wrap">
        <h1><?php _e('Manage Event Signups', 'task-table'); ?></h1>
        
        <ul class="subsubsub">
            <li>
                <a href="<?php echo admin_url('admin.php?page=task-table-signups'); ?>" <?php echo empty($status_filter) ? 'class="current"' : ''; ?>>
                    <?php _e('All', 'task-table'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=task-table-signups&status=pending'); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>>
                    <?php _e('Pending', 'task-table'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=task-table-signups&status=approved'); ?>" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>>
                    <?php _e('Approved', 'task-table'); ?>
                </a> |
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=task-table-signups&status=denied'); ?>" <?php echo $status_filter === 'denied' ? 'class="current"' : ''; ?>>
                    <?php _e('Denied', 'task-table'); ?>
                </a>
            </li>
        </ul>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'task-table'); ?></th>
                    <th><?php _e('Name', 'task-table'); ?></th>
                    <th><?php _e('Email', 'task-table'); ?></th>
                    <th><?php _e('Event', 'task-table'); ?></th>
                    <th><?php _e('Info', 'task-table'); ?></th>
                    <th><?php _e('Time', 'task-table'); ?></th>
                    <th><?php _e('Status', 'task-table'); ?></th>
                    <th><?php _e('Date', 'task-table'); ?></th>
                    <th><?php _e('Actions', 'task-table'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($signups)): ?>
                    <tr>
                        <td colspan="9"><?php _e('No signups found.', 'task-table'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($signups as $signup): ?>
                        <tr>
                            <td><?php echo esc_html($signup->id); ?></td>
                            <td><?php echo esc_html($signup->user_name); ?></td>
                            <td><?php echo esc_html($signup->user_email); ?></td>
                            <td><?php echo esc_html($signup->was_angefragt); ?></td>
                            <td class="signup-free-text">
                                <?php if (!empty($signup->free_text)) {
                                    $raw = (string) $signup->free_text;
                                    // Normalize newlines
                                    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
                                    // Trim overall and remove leading/trailing blank lines
                                    $raw = trim($raw, " \t\n");
                                    // Split to process each line
                                    $lines = explode("\n", $raw);
                                    $processed = [];
                                    foreach ($lines as $line) {
                                        // Trim left side only to remove large indent, keep intentional right spaces trimmed
                                        $line = ltrim($line, " \t");
                                        // Collapse internal excessive whitespace (but keep single spaces)
                                        $line = preg_replace('/[ \t]{2,}/', ' ', $line);
                                        $processed[] = rtrim($line); // remove trailing spaces
                                    }
                                    $raw = implode("\n", $processed);
                                    // Collapse more than one blank line
                                    $raw = preg_replace("/\n{3,}/", "\n\n", $raw);
                                    $clean_text = trim($raw);
                                    echo nl2br(esc_html($clean_text));
                                } else {
                                    echo '&mdash;';
                                } ?>
                            </td>
                            <td><?php echo esc_html($signup->zeitpunkt); ?></td>
                            <td>
                                <?php
                                $status_label = '';
                                $status_class = '';
                                
                                switch ($signup->status) {
                                    case 'pending':
                                        $status_label = __('Pending', 'task-table');
                                        $status_class = 'status-pending';
                                        break;
                                    case 'approved':
                                        $status_label = __('Approved', 'task-table');
                                        $status_class = 'status-approved';
                                        break;
                                    case 'denied':
                                        $status_label = __('Denied', 'task-table');
                                        $status_class = 'status-denied';
                                        break;
                                }
                                ?>
                                <span class="<?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($signup->created_at)); ?></td>
                            <td>
                                <?php if ($signup->status === 'pending'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=task-table-signups&action=approve&signup_id=' . $signup->id), 'task_table_signup_approve_' . $signup->id); ?>" class="button button-small action-approve">
                                        <?php _e('Approve', 'task-table'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=task-table-signups&action=deny&signup_id=' . $signup->id), 'task_table_signup_deny_' . $signup->id); ?>" class="button button-small action-deny">
                                        <?php _e('Deny', 'task-table'); ?>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=task-table-signups&action=delete&signup_id=' . $signup->id), 'task_table_signup_delete_' . $signup->id); ?>" class="button button-small action-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this signup?', 'task-table'); ?>');">
                                    <?php _e('Delete', 'task-table'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
        .status-pending {
            color: #ff9800;
            font-weight: bold;
        }
        .status-approved {
            color: #4caf50;
            font-weight: bold;
        }
        .status-denied {
            color: #f44336;
            font-weight: bold;
        }
        .action-approve {
            background-color: #4caf50 !important;
            color: white !important;
            border-color: #388e3c !important;
        }
        .action-deny {
            background-color: #f44336 !important;
            color: white !important;
            border-color: #d32f2f !important;
        }
        .action-delete {
            background-color: #9e9e9e !important;
            color: white !important;
            border-color: #757575 !important;
        }
        .signup-free-text {
            max-width: 260px;
            white-space: normal; /* we convert newlines manually with nl2br */
            word-break: break-word;
            line-height: 1.35;
            font-size: 12px;
            padding: 0; /* align visually with other cells */
            background: transparent;
            border: none;
            border-radius: 0;
            vertical-align: top;
        }
        .signup-free-text:empty::after { content: '\2014'; }
        .wp-list-table .signup-free-text br { line-height: 1.2; }
    </style>
    <?php
}

/**
 * Admin subscribers page
 */
function task_table_admin_subscribers_page() {
    global $wpdb;
    $subscribers_table = $wpdb->prefix . 'task_table_subscribers';
    
    // Handle subscriber deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['subscriber_id']) && isset($_GET['_wpnonce'])) {
        $subscriber_id = intval($_GET['subscriber_id']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);
        
        if (wp_verify_nonce($nonce, 'task_table_delete_subscriber_' . $subscriber_id)) {
            $wpdb->delete($subscribers_table, array('id' => $subscriber_id));
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Subscriber deleted.', 'task-table') . '</p></div>';
        }
    }
    
    // Get subscribers
    $subscribers = $wpdb->get_results("SELECT * FROM $subscribers_table ORDER BY created_at DESC");

    // Handle resend confirmation
    if (isset($_GET['action']) && $_GET['action'] === 'resend' && isset($_GET['subscriber_id']) && isset($_GET['_wpnonce'])) {
        $subscriber_id = intval($_GET['subscriber_id']);
        if (wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'task_table_resend_subscriber_' . $subscriber_id)) {
            $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscribers_table WHERE id = %d", $subscriber_id));
            if ($subscriber && (int)$subscriber->confirmed === 0) {
                // Generate new token
                $token = wp_generate_password(32, false, false);
                $wpdb->update($subscribers_table, [
                    'confirmation_token' => $token,
                    'updated_at' => current_time('mysql')
                ], ['id' => $subscriber_id]);
                task_table_send_subscription_confirmation_email($subscriber->email, $token);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Confirmation email resent.', 'task-table') . '</p></div>';
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Manage Subscribers', 'task-table'); ?></h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'task-table'); ?></th>
                    <th><?php _e('Email', 'task-table'); ?></th>
                    <th><?php _e('Confirmed', 'task-table'); ?></th>
                    <th><?php _e('Date', 'task-table'); ?></th>
                    <th><?php _e('Actions', 'task-table'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                    <tr>
                        <td colspan="5"><?php _e('No subscribers found.', 'task-table'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td><?php echo esc_html($subscriber->id); ?></td>
                            <td><?php echo esc_html($subscriber->email); ?></td>
                            <td><?php echo ((int)$subscriber->confirmed === 1) ? __('Yes', 'task-table') : __('No', 'task-table'); ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($subscriber->created_at)); ?></td>
                            <td>
                                <?php if (!(int)$subscriber->confirmed): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=task-table-subscribers&action=resend&subscriber_id=' . $subscriber->id), 'task_table_resend_subscriber_' . $subscriber->id); ?>" class="button button-small">
                                        <?php _e('Resend Confirmation', 'task-table'); ?>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=task-table-subscribers&action=delete&subscriber_id=' . $subscriber->id), 'task_table_delete_subscriber_' . $subscriber->id); ?>" class="button button-small action-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this subscriber?', 'task-table'); ?>');">
                                    <?php _e('Delete', 'task-table'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2><?php _e('Add New Subscriber', 'task-table'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('task_table_add_subscriber', 'task_table_add_subscriber_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="subscriber_email"><?php _e('Email', 'task-table'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="subscriber_email" id="subscriber_email" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="subscriber_confirmed"><?php _e('Confirmed', 'task-table'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="subscriber_confirmed" id="subscriber_confirmed" value="1">
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Add Subscriber', 'task-table'); ?>">
            </p>
        </form>
    </div>
    <?php
    
    // Handle form submission
    if (isset($_POST['submit']) && isset($_POST['task_table_add_subscriber_nonce'])) {
        if (wp_verify_nonce($_POST['task_table_add_subscriber_nonce'], 'task_table_add_subscriber')) {
            $email = sanitize_email($_POST['subscriber_email']);
            $confirmed = isset($_POST['subscriber_confirmed']) ? 1 : 0;
            
            if (is_email($email)) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $subscribers_table WHERE email = %s",
                    $email
                ));
                
                if ($exists) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('This email is already subscribed.', 'task-table') . '</p></div>';
                } else {
                    $wpdb->insert($subscribers_table, array(
                        'email' => $email,
                        'confirmed' => $confirmed,
                        'created_at' => current_time('mysql')
                    ));
                    
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Subscriber added successfully.', 'task-table') . '</p></div>';
                    echo '<script>window.location.href = window.location.href;</script>';
                }
            } else {
                // Fix: Corrected the PHP syntax in the error message
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Please enter a valid email address.', 'task-table') . '</p></div>';
            }
        }
    }
}

/**
 * Admin email settings page
 */
function task_table_admin_email_settings_page() {
    // Handle form submission
    if (isset($_POST['task_table_email_settings_nonce']) && wp_verify_nonce($_POST['task_table_email_settings_nonce'], 'task_table_email_settings')) {
        $templates = task_table_get_email_templates();
        
        foreach (array_keys($templates) as $template_key) {
            if (isset($_POST["task_table_email_{$template_key}_subject"])) {
                update_option("task_table_email_{$template_key}_subject", wp_kses_post($_POST["task_table_email_{$template_key}_subject"]));
            }
            
            if (isset($_POST["task_table_email_{$template_key}_message"])) {
                update_option("task_table_email_{$template_key}_message", wp_kses_post($_POST["task_table_email_{$template_key}_message"]));
            }
        }
        
        if (isset($_POST['moderator_emails'])) {
            // Process and save multiple email addresses from the hidden input that contains all emails
            $moderator_emails = sanitize_text_field($_POST['moderator_emails']);
            $emails_array = array_filter(explode(',', $moderator_emails));
            
            // Validate each email and remove duplicates
            $valid_emails = array();
            foreach ($emails_array as $email) {
                $email = trim($email);
                if (!empty($email) && is_email($email) && !in_array($email, $valid_emails)) {
                    $valid_emails[] = sanitize_email($email);
                }
            }
            
            // Store as a comma-separated string
            update_option('moderator_email', implode(',', $valid_emails));
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Email settings saved.', 'task-table') . '</p></div>';
    }
    
    $templates = task_table_get_email_templates();
    $moderator_emails = get_option('moderator_email', get_option('admin_email'));
    $email_list = task_table_parse_email_list($moderator_emails);
    
    ?>
    <div class="wrap">
        <h1><?php _e('Email Settings', 'task-table'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('task_table_email_settings', 'task_table_email_settings_nonce'); ?>
            
            <h2 class="nav-tab-wrapper email-templates-tabs">
                <a href="#general-settings" class="nav-tab nav-tab-active"><?php _e('General Settings', 'task-table'); ?></a>
                <?php foreach (array_keys($templates) as $tab_key): ?>
                    <a href="#template-<?php echo $tab_key; ?>" class="nav-tab"><?php echo ucfirst(str_replace('_', ' ', $tab_key)); ?></a>
                <?php endforeach; ?>
            </h2>
            
            <div id="general-settings" class="email-template-tab" style="display: block;">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="new_email"><?php _e('Notification Recipients', 'task-table'); ?></label>
                        </th>
                        <td>
                            <div class="email-recipients-container">
                                <div id="email-tags-container" class="email-tags-container">
                                    <?php foreach($email_list as $email): ?>
                                        <div class="email-tag">
                                            <span><?php echo esc_html($email); ?></span>
                                            <button type="button" class="remove-email" data-email="<?php echo esc_attr($email); ?>">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="email-input-container">
                                    <input type="email" id="new_email" placeholder="<?php _e('Add new email address', 'task-table'); ?>" class="regular-text">
                                    <button type="button" id="add_email" class="button button-secondary"><?php _e('Add', 'task-table'); ?></button>
                                </div>
                                
                                <!-- Hidden input to store all emails as comma-separated string -->
                                <input type="hidden" name="moderator_emails" id="moderator_emails" value="<?php echo esc_attr($moderator_emails); ?>">
                                
                                <p class="description">
                                    <?php _e('Enter email addresses that should receive notifications about new signups.', 'task-table'); ?><br>
                                    <?php _e('Each recipient will receive an individual email (addresses won\'t be visible to other recipients).', 'task-table'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php foreach ($templates as $template_key => $template): ?>
                <div id="template-<?php echo $template_key; ?>" class="email-template-tab" style="display: none;">
                    <h3><?php echo ucfirst(str_replace('_', ' ', $template_key)); ?> <?php _e('Template', 'task-table'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="task_table_email_<?php echo $template_key; ?>_subject"><?php _e('Subject', 'task-table'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="task_table_email_<?php echo $template_key; ?>_subject" id="task_table_email_<?php echo $template_key; ?>_subject" class="large-text" value="<?php echo esc_attr(get_option("task_table_email_{$template_key}_subject", $template['subject'])); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="task_table_email_<?php echo $template_key; ?>_message"><?php _e('Message', 'task-table'); ?></label>
                            </th>
                            <td>
                                <textarea name="task_table_email_<?php echo $template_key; ?>_message" id="task_table_email_<?php echo $template_key; ?>_message" class="large-text" rows="10"><?php echo esc_textarea(get_option("task_table_email_{$template_key}_message", $template['message'])); ?></textarea>
                                
                                <h4><?php _e('Available Placeholders', 'task-table'); ?>:</h4>
                                <ul class="task-table-placeholders">
                                    <?php if ($template_key === 'subscription_confirmation'): ?>
                                        <li><code>{confirmation_link}</code> - <?php _e('Bestätigungslink für das Abo', 'task-table'); ?></li>
                                    <?php elseif (in_array($template_key, array('signup_approved', 'signup_denied'))): ?>
                                        <li><code>{user_name}</code> - <?php _e('User name', 'task-table'); ?></li>
                                        <li><code>{event_title}</code> - <?php _e('Event title', 'task-table'); ?></li>
                                        <li><code>{event_details}</code> - <?php _e('Event details', 'task-table'); ?></li>
                                    <?php elseif ($template_key === 'new_signup'): ?>
                                        <li><code>{user_name}</code> - <?php _e('User name', 'task-table'); ?></li>
                                        <li><code>{user_email}</code> - <?php _e('User email', 'task-table'); ?></li>
                                        <li><code>{event_id}</code> - <?php _e('Event ID', 'task-table'); ?></li>
                                    <?php elseif ($template_key === 'new_event'): ?>
                                        <li><code>{event_title}</code> - <?php _e('Event title', 'task-table'); ?></li>
                                        <li><code>{event_details}</code> - <?php _e('Event details', 'task-table'); ?></li>
                                    <?php endif; ?>
                                    <li><code>{site_name}</code> - <?php _e('Site name', 'task-table'); ?></li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'task-table'); ?>">
            </p>
        </form>
    </div>
    
    <style>
        .task-table-placeholders {
            background: #f9f9f9;
            padding: 10px 15px;
            border-left: 3px solid #2271b1;
        }
        .task-table-placeholders code {
            background: #e6e6e6;
            padding: 2px 5px;
        }
        
        /* Email tags styling */
        .email-tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            min-height: 30px;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        .email-tag {
            display: inline-flex;
            align-items: center;
            background-color: #e7f5fa;
            border: 1px solid #8cd0e5;
            border-radius: 3px;
            padding: 4px 8px;
        }
        
        .email-tag .remove-email {
            border: none;
            background: none;
            color: #FF5252;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            margin-left: 8px;
            padding: 0 2px;
        }
        
        .email-tag .remove-email:hover {
            color: #FF0000;
        }
        
        .email-input-container {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        #new_email {
            flex-grow: 1;
        }
    </style>
    
    <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                // Hide all tabs
                $('.email-template-tab').hide();
                
                // Show the selected tab
                $($(this).attr('href')).show();
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
            });
            
            // Email management
            function updateEmailsInput() {
                var emails = [];
                $('.email-tag span').each(function() {
                    emails.push($(this).text());
                });
                $('#moderator_emails').val(emails.join(','));
            }
            
            // Add a new email
            $('#add_email').on('click', function() {
                var email = $('#new_email').val().trim();
                if (!email) return;
                
                // Simple email validation
                if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    alert('Please enter a valid email address');
                    return;
                }
                
                // Check for duplicates
                var isDuplicate = false;
                $('.email-tag span').each(function() {
                    if ($(this).text() === email) {
                        isDuplicate = true;
                        return false;
                    }
                });
                
                if (isDuplicate) {
                    alert('This email address is already added');
                    return;
                }
                
                // Add new email tag
                var emailTag = $('<div class="email-tag"><span>' + email + '</span><button type="button" class="remove-email" data-email="' + email + '">&times;</button></div>');
                $('#email-tags-container').append(emailTag);
                
                // Clear input
                $('#new_email').val('').focus();
                
                // Update hidden input
                updateEmailsInput();
            });
            
            // Add email on pressing Enter
            $('#new_email').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#add_email').click();
                }
            });
            
            // Remove email
            $(document).on('click', '.remove-email', function() {
                $(this).closest('.email-tag').remove();
                updateEmailsInput();
            });
            
            // Initial update
            updateEmailsInput();
        });
    </script>
    <?php
}

/**
 * Parse a string containing multiple email addresses.
 * Accepts comma-separated or line-break-separated lists.
 *
 * @param string $email_string String containing multiple email addresses
 * @return array Array of valid email addresses
 */
function task_table_parse_email_list($email_string) {
    // Replace commas with line breaks to standardize the separator
    $email_string = str_replace(',', "\n", $email_string);
    
    // Split the string by line breaks
    $emails = explode("\n", $email_string);
    
    // Clean and validate each email
    $valid_emails = array();
    foreach ($emails as $email) {
        $email = trim($email);
        if (!empty($email) && is_email($email)) {
            $valid_emails[] = sanitize_email($email);
        }
    }
    
    return $valid_emails;
}

/**
 * Get all notification emails for new signups
 * This function ensures emails are sent individually to protect recipient privacy
 */
function task_table_get_signup_notification_emails() {
    $moderator_emails = get_option('moderator_email', get_option('admin_email'));
    return task_table_parse_email_list($moderator_emails);
}
