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
echo '<th>Wer macht\'s?</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($attributes)) {
    foreach ($attributes as $row) {
        error_log("Row data: " . print_r($row, true)); // Debugging
        if (!isset($row['id'])) {
            error_log("Missing 'id' key in row data"); // Debugging
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
        error_log("Fetching sign-ups for event ID: " . $row['id']); // Debugging
        if (!empty($signups)) {
            foreach ($signups as $signup) {
                error_log("Found approved sign-up: " . $signup->user_name); // Debugging
                echo esc_html($signup->user_name) . '<br>';
            }
        } else {
            error_log("No approved sign-ups found for event ID: " . $row['id']); // Debugging
            echo '<button class="event-signup" data-event-id="' . esc_attr($row['id']) . '">Sign Up</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="7">No events added yet.</td></tr>';
}

echo '</tbody></table></div>';

// handle frontend form submission
echo '<div id="signup-modal" style="display: none;">
    <form id="signup-form">
        <input type="hidden" id="event-id" name="event_id">
        <label for="user-name">Name:</label>
        <input type="text" id="user-name" name="user_name" required>
        <label for="user-email">Email:</label>
        <input type="email" id="user-email" name="user_email" required>
        <button type="submit">Sign Up</button>
    </form>
</div>';

echo '<script>
if (!window.taskTableScriptLoaded) {
    window.taskTableScriptLoaded = true;

    document.addEventListener("DOMContentLoaded", function () {
        const signupButtons = document.querySelectorAll(".event-signup");
        const modal = document.getElementById("signup-modal");
        const form = document.getElementById("signup-form");

        signupButtons.forEach(button => {
            button.addEventListener("click", function () {
                const eventId = this.dataset.eventId;
                console.log("Event ID:", eventId); // Debugging
                document.getElementById("event-id").value = eventId;
                modal.style.display = "block";
            });
        });

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const data = {
                event_id: document.getElementById("event-id").value,
                user_name: document.getElementById("user-name").value,
                user_email: document.getElementById("user-email").value
            };

            fetch("/wp-json/event-signups/v1/submit", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert("Sign-up submitted!");
                    modal.style.display = "none";
                } else {
                    console.error("Sign-up submission failed:", result.message); // Debugging
                }
            })
            .catch(error => {
                console.error("Error submitting sign-up:", error); // Debugging
            });
        });
    });
}
</script>';