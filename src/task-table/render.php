<?php
$attributes = isset($attributes['rows']) ? $attributes['rows'] : [];

echo '<div class="wp-block-task-table-task-table">';
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>Was ist angefragt?</th>';
echo '<th>Kontext</th>';
echo '<th>Name der Hochschule</th>';
echo '<th>Vorauss. TN-Anzahl</th>';
echo '<th>Anvisierter Zeitpunkt</th>';
echo '<th>Auftragdetails</th>';
echo '<th>Wer macht\'s?</th>'; // Moved to the end
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($attributes)) {
    foreach ($attributes as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['wasAngefragt']) . '</td>';
        echo '<td>' . esc_html($row['kontext']) . '</td>';
        echo '<td>' . esc_html($row['nameHochschule']) . '</td>';
        echo '<td>' . esc_html($row['tnAnzahl']) . '</td>';
        echo '<td>' . esc_html($row['zeitpunkt']) . '</td>';
        echo '<td>' . esc_html($row['auftragdetails']) . '</td>';
        echo '<td>';
        echo '<div>' . esc_html($row['werMacht']) . '</div>'; // Display "Wer macht's?"
        echo '<button class="event-signup">Anmelden</button>'; // Sign-up button
        echo '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="7">No events added yet.</td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';