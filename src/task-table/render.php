<?php
/**
 * Renders the task table block on the frontend
 */
$attributes = isset($attributes['rows']) ? $attributes['rows'] : [];

echo '<div class="wp-block-task-table-task-table">';

// Add notification element
echo '<div id="signup-notification" class="signup-notification"></div>';

echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>Was ist angefragt?</th>';
echo '<th>Kontext</th>';
echo '<th>Name der Hochschule</th>';
echo '<th>Vorauss. TN-Anzahl</th>';
echo '<th>Anvisierter Zeitpunkt</th>';
echo '<th>Auftragdetails</th>';
echo '<th>Wer macht\'s?</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($attributes)) {
    foreach ($attributes as $row) {
        if (!isset($row['id'])) {
            continue;
        }
        echo '<tr>';
        echo '<td>' . esc_html($row['wasAngefragt']) . '</td>';
        echo '<td>' . esc_html($row['kontext']) . '</td>';
        echo '<td>' . esc_html($row['nameHochschule']) . '</td>';
        echo '<td>' . esc_html($row['tnAnzahl']) . '</td>';
        echo '<td>' . esc_html($row['zeitpunkt']) . '</td>';
        echo '<td>' . esc_html($row['auftragdetails']) . '</td>';
        echo '<td>';
        // Fetch approved sign-ups for this event
        global $wpdb;
        $table_name = $wpdb->prefix . 'event_signups';
        $signups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE event_id = %d AND status = 'approved'", $row['id']));
        
        if (!empty($signups)) {
            foreach ($signups as $signup) {
                echo esc_html($signup->user_name) . '<br>';
            }
        } else {
            echo '<button class="event-signup" data-event-id="' . esc_attr($row['id']) . '">Sign Up</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="7">No events added yet.</td></tr>';
}

echo '</tbody></table>';

// Add signup modal
echo '<div id="signup-modal">
    <form id="signup-form">
        <input type="hidden" id="event-id" name="event_id">
        <label for="user-name">Name:</label>
        <input type="text" id="user-name" name="user_name" required>
        <label for="user-email">Email:</label>
        <input type="email" id="user-email" name="user_email" required>
        <button type="submit">Sign Up</button>
    </form>
</div>';

echo '</div>'; // Close the main container