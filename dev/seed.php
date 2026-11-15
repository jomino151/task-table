<?php
/**
 * Dev seed data (fictional). Run via: dev/wp.sh eval-file wp-content/plugins/task-table/dev/seed.php
 */
global $wpdb;
$events  = $wpdb->prefix . 'task_table_events';
$signups = $wpdb->prefix . 'task_table_signups';
$cat     = task_table_get_default_category_id();
$now     = current_time('mysql');

$rows = array(
    array('Workshop: Intro to Git', 'Onboarding week', 'Example University', '25', 'March 2027', 'Internal note: budget 500 EUR', '', 0),
    array('Panel discussion', 'Career day', 'Sample College', '80', 'April 2027', 'Contact: dean office, room 4.12', '', 0),
    array('Archived seminar', 'Old request', 'Test Institute', '10', 'Jan 2025', 'Confidential: client paid late', 'Alex', 1),
);
foreach ($rows as $r) {
    $wpdb->insert($events, array(
        'was_angefragt' => $r[0], 'kontext' => $r[1], 'name_hochschule' => $r[2], 'tn_anzahl' => $r[3],
        'zeitpunkt' => $r[4], 'auftragdetails' => $r[5], 'wer_macht' => $r[6], 'category_id' => $cat,
        'archived' => $r[7], 'created_at' => $now, 'updated_at' => $now,
    ));
}
$first = (int) $wpdb->get_var("SELECT MIN(id) FROM $events");
$wpdb->insert($signups, array(
    'event_id' => $first, 'user_name' => 'Pat Example', 'user_email' => 'pat@example.test',
    'free_text' => 'Happy to help', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
));
echo "Seeded " . count($rows) . " events and 1 signup.\n";
