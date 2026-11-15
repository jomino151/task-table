<?php
// Get events from the database instead of attributes
global $wpdb;
$events_table = $wpdb->prefix . 'task_table_events';

$category_id = isset($attributes['categoryId']) ? (int) $attributes['categoryId'] : 0;
if (!$category_id) {
    $category_id = task_table_get_default_category_id();
}

$category_name = '';
if ($category_id) {
    $category = task_table_get_category($category_id);
    if ($category) {
        $category_name = $category['name'];
    }
}

// Only show non-archived events on the public frontend. If the archived column
// does not exist yet (e.g. race condition before upgrade hook), fall back to all.
$has_archived_col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$events_table} LIKE %s", 'archived'));
if ($has_archived_col) {
    if ($category_id) {
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $events_table WHERE archived = 0 AND category_id = %d ORDER BY created_at DESC",
            $category_id
        ), ARRAY_A);
    } else {
        $events = $wpdb->get_results("SELECT * FROM $events_table WHERE archived = 0 ORDER BY created_at DESC", ARRAY_A);
    }
} else {
    if ($category_id) {
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $events_table WHERE category_id = %d ORDER BY created_at DESC",
            $category_id
        ), ARRAY_A);
    } else {
        $events = $wpdb->get_results("SELECT * FROM $events_table ORDER BY created_at DESC", ARRAY_A);
    }
}

echo '<div ' . get_block_wrapper_attributes() . '>';
if (!empty($category_name)) {
    echo '<h3 class="task-table-category-heading">' . esc_html($category_name) . '</h3>';
}

$render_event_cell = static function ($value) {
    $content = is_scalar($value) ? (string) $value : '';

    if ($content === '') {
        return '';
    }

    if (function_exists('task_table_sanitize_event_text')) {
        $content = task_table_sanitize_event_text($content);
        $content = make_clickable($content);

        return task_table_sanitize_event_text($content);
    }

    return wp_kses_post(make_clickable($content));
};

$column_labels = task_table_get_column_labels();
echo '<table class="task-table-grid">';
echo '<colgroup>';
echo '<col class="tt-col-request" />';
echo '<col class="tt-col-context" />';
echo '<col class="tt-col-university" />';
echo '<col class="tt-col-tn" />';
echo '<col class="tt-col-time" />';
echo '<col class="tt-col-details" />';
echo '<col class="tt-col-signups" />';
echo '</colgroup>';
echo '<thead>';
echo '<tr>';
echo '<th class="tt-col-request">' . esc_html($column_labels['was_angefragt']) . '</th>';
echo '<th class="tt-col-context">' . esc_html($column_labels['kontext']) . '</th>';
echo '<th class="tt-col-university">' . esc_html($column_labels['name_hochschule']) . '</th>';
echo '<th class="tt-col-tn">' . esc_html($column_labels['tn_anzahl']) . '</th>';
echo '<th class="tt-col-time">' . esc_html($column_labels['zeitpunkt']) . '</th>';
echo '<th class="tt-col-details">' . esc_html($column_labels['auftragdetails']) . '</th>';
echo '<th class="tt-col-signups">' . esc_html($column_labels['wer_macht']) . '</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($events)) {
    foreach ($events as $event) {
        echo '<tr>';
        echo '<td class="tt-col-request">' . $render_event_cell($event['was_angefragt']) . '</td>';
        echo '<td class="tt-col-context">' . $render_event_cell($event['kontext']) . '</td>';
        echo '<td class="tt-col-university">' . $render_event_cell($event['name_hochschule']) . '</td>';
        echo '<td class="tt-col-tn"><span class="tt-tn-value">' . $render_event_cell($event['tn_anzahl']) . '</span></td>';
        echo '<td class="tt-col-time">' . $render_event_cell($event['zeitpunkt']) . '</td>';
        echo '<td class="tt-col-details">' . $render_event_cell($event['auftragdetails']) . '</td>';
        // Fetch approved sign-ups for this event.
        $signups_table = $wpdb->prefix . 'task_table_signups';
        $signups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $signups_table WHERE event_id = %d AND status = 'approved'", 
            $event['id']
        ));
        
        $hasApproved = !empty($signups);

        // Combined column: approved names on top, signup button below.
        echo '<td class="tt-col-signups">';
        echo '<div class="tt-signup-stack">';
        if ($hasApproved) {
            echo '<div class="approved-signups">';
            foreach ($signups as $signup) {
                echo '<span class="approved-person">' . esc_html($signup->user_name) . '</span>';
            }
            echo '</div>';
        }
        echo '<button class="event-signup" data-event-id="' . esc_attr($event['id']) . '" data-has-approved="' . ($hasApproved ? '1' : '0') . '">' . esc_html__('Anmelden', 'task-table') . '</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
} else {
    // Updated colspan to match the combined signups/action column.
    $empty_message = __('Noch keine Veranstaltungen hinzugefügt.', 'task-table');
    if (!empty($category_name)) {
        $empty_message = sprintf(__('Noch keine Veranstaltungen in der Kategorie “%s”.', 'task-table'), $category_name);
    }
    echo '<tr><td colspan="7">' . esc_html($empty_message) . '</td></tr>';
}

echo '</tbody></table>';

// Add notification div for success/error messages
echo '</div>'; // Close the main container

// Move the modal outside the main block container to avoid CSS scoping issues
include(TASK_TABLE_PLUGIN_DIR . 'build/templates/signup-modal.php');

// Add WordPress REST API nonce to the page
$nonce = wp_create_nonce('wp_rest');

// Add JavaScript for front-end functionality
$signup_i18n = array(
    'fillAll' => __('Bitte fülle alle Felder aus.', 'task-table'),
    'success' => __('Deine Anmeldung wurde erfolgreich übermittelt und wird geprüft.', 'task-table'),
    'error'   => __('Ein Fehler ist aufgetreten. Bitte versuche es später erneut.', 'task-table'),
);

echo '<script>
if (!window.taskTableScriptLoaded) {
    window.taskTableSignupI18n = ' . wp_json_encode($signup_i18n) . ';
    window.taskTableScriptLoaded = true;

    document.addEventListener("DOMContentLoaded", function () {
        // DOM elements
        const signupButtons = document.querySelectorAll(".event-signup");
        const modalOverlay = document.getElementById("signup-modal-overlay");
        const modal = document.getElementById("signup-modal");
        const form = document.getElementById("signup-form");
        const closeModalBtn = document.getElementById("close-modal");
        const cancelBtn = document.getElementById("cancel-signup");
        const submitBtn = document.getElementById("submit-signup");
        const feedbackEl = document.getElementById("signup-feedback");
        const freeTextArea = document.getElementById("free-text");

        // Auto-resize textarea
        const autoResize = (el) => {
            if (!el) return;
            el.style.height = "auto";
            el.style.height = el.scrollHeight + "px";
        };
        if (freeTextArea) {
            freeTextArea.addEventListener("input", () => autoResize(freeTextArea));
            // Initialize height if prefilled
            autoResize(freeTextArea);
        }

        // Ensure modal is initially hidden
        if (modalOverlay) {
            modalOverlay.classList.remove("active");
        }

        // Helper functions
        const showFeedback = (message, type) => {
            if (!feedbackEl) return;
            feedbackEl.textContent = message;
            feedbackEl.className = "signup-feedback " + type + " show";
            
            setTimeout(() => {
                feedbackEl.classList.remove("show");
            }, 5000);
        };
        
        const showLoading = (button) => {
            if (!button) return;
            button.classList.add("loading");
            button.disabled = true;
        };
        
        const hideLoading = (button) => {
            if (!button) return;
            button.classList.remove("loading");
            button.disabled = false;
        };

        // Event signup buttons
        signupButtons.forEach(button => {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                const eventId = this.getAttribute("data-event-id");
                const hasApproved = this.getAttribute("data-has-approved") === "1";
                if (document.getElementById("event-id")) {
                    document.getElementById("event-id").value = eventId;
                }
                if (modalOverlay) {
                    modalOverlay.classList.add("active");
                    document.body.style.overflow = "hidden"; // Prevent scrolling
                }
                
                // Reset form and feedback
                if (form) form.reset();
                if (feedbackEl) feedbackEl.className = "signup-feedback";

                // Replacement disclaimer logic
                const disclaimer = document.getElementById("replacement-disclaimer");
                const replacementFlag = document.getElementById("replacement-signup-flag");
                if (hasApproved) {
                    if (disclaimer) disclaimer.style.display = "block";
                    if (replacementFlag) replacementFlag.value = "1";
                } else {
                    if (disclaimer) disclaimer.style.display = "none";
                    if (replacementFlag) replacementFlag.value = "";
                }
            });
        });

        // Close modal handlers
        const closeModal = () => {
            if (modalOverlay) {
                modalOverlay.classList.remove("active");
                document.body.style.overflow = ""; // Re-enable scrolling
            }
        };
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener("click", closeModal);
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener("click", closeModal);
        }

        // Handle click outside modal
        if (modalOverlay) {
            modalOverlay.addEventListener("click", function(e) {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });
        }
        
        // Handle escape key press
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && modalOverlay && modalOverlay.classList.contains("active")) {
                closeModal();
            }
        });

        // Handle signup form submission
        if (form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                
                const eventId = document.getElementById("event-id").value;
                const userName = document.getElementById("user-name").value;
                const userEmail = document.getElementById("user-email").value;
                const freeTextEl = document.getElementById("free-text");
                let freeText = freeTextEl ? freeTextEl.value : "";
                const replacementFlag = document.getElementById("replacement-signup-flag");
                if (replacementFlag && replacementFlag.value === "1") {
                    if (!/^\[Ersatz-Anmeldung\]/.test(freeText)) {
                        freeText = "[Ersatz-Anmeldung] " + freeText;
                    }
                }
                
                if (!userName.trim() || !userEmail.trim()) {
                    showFeedback(window.taskTableSignupI18n.fillAll, "error");
                    return;
                }
                
                showLoading(submitBtn);
                
                fetch(' . wp_json_encode(esc_url_raw(rest_url('event-signups/v1/submit'))) . ', {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": "' . $nonce . '"
                    },
                    body: JSON.stringify({
                        event_id: eventId,
                        user_name: userName,
                        user_email: userEmail,
                        free_text: freeText
                    })
                })
                .then(response => response.json().catch(() => {
                    throw new Error("Network response was not ok");
                }))
                .then(data => {
                    hideLoading(submitBtn);
                    
                    if (data.success) {
                        showFeedback(window.taskTableSignupI18n.success, "success");
                        
                        // Close modal and reload after delay
                        setTimeout(() => {
                            closeModal();
                            window.location.reload();
                        }, 2000);
                    } else {
                        showFeedback(data.message || window.taskTableSignupI18n.error, "error");
                    }
                })
                .catch(error => {
                    hideLoading(submitBtn);
                    console.error("Error:", error);
                    showFeedback(window.taskTableSignupI18n.error, "error");
                });
            });
        }

    });
}
</script>';