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

echo '<div class="wp-block-task-table-task-table">';
if (!empty($category_name)) {
    echo '<h3 class="task-table-category-heading">' . esc_html($category_name) . '</h3>';
}
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>Was ist Angefragt?</th>';
echo '<th>Kontext</th>';
echo '<th>Name der Hochschule</th>';
echo '<th>Vorauss. TN-Anzahl</th>';
echo '<th>Anvisierter Zeitpunkt</th>';
echo '<th>Auftragdetails</th>';
echo '<th>Wer macht\'s?</th>';
// New dedicated signup column
 echo '<th>Anmelden</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (!empty($events)) {
    foreach ($events as $event) {
        echo '<tr>';
        echo '<td>' . esc_html($event['was_angefragt']) . '</td>';
        echo '<td>' . esc_html($event['kontext']) . '</td>';
        echo '<td>' . esc_html($event['name_hochschule']) . '</td>';
        echo '<td>' . esc_html($event['tn_anzahl']) . '</td>';
        echo '<td>' . esc_html($event['zeitpunkt']) . '</td>';
        echo '<td>' . esc_html($event['auftragdetails']) . '</td>';
        // Column: Wer macht's? (approved signups only)
        echo '<td>';
        
        // Fetch approved sign-ups for this event
        $signups_table = $wpdb->prefix . 'task_table_signups';
        $signups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $signups_table WHERE event_id = %d AND status = 'approved'", 
            $event['id']
        ));
        
        $hasApproved = !empty($signups);
        if ($hasApproved) {
            echo '<div class="approved-signups">';
            foreach ($signups as $signup) {
                echo '<span class="approved-person">' . esc_html($signup->user_name) . '</span><br>';
            }
            echo '</div>';
        }
        echo '</td>';

        // Column: Anmelden (button only)
        echo '<td>';
        echo '<button class="event-signup" data-event-id="' . esc_attr($event['id']) . '" data-has-approved="' . ($hasApproved ? '1' : '0') . '">Anmelden</button>';
        echo '</td>';
        echo '</tr>';
    }
} else {
    // Updated colspan from 7 to 8 to match new column count
    $empty_message = __('Noch keine Veranstaltungen hinzugefügt.', 'task-table');
    if (!empty($category_name)) {
        $empty_message = sprintf(__('Noch keine Veranstaltungen in der Kategorie “%s”.', 'task-table'), $category_name);
    }
    echo '<tr><td colspan="8">' . esc_html($empty_message) . '</td></tr>';
}

echo '</tbody></table>';

// Add notification div for success/error messages
echo '</div>'; // Close the main container

// Move the modal outside the main block container to avoid CSS scoping issues
include(TASK_TABLE_PLUGIN_DIR . 'src/templates/signup-modal.php');

// Add WordPress REST API nonce to the page
$nonce = wp_create_nonce('wp_rest');

// Add inline styles to ensure the modal is properly styled regardless of SCSS compilation
echo '<style>
    /* Modal styles - defined globally to ensure they apply */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    #signup-modal {
        background-color: white;
        padding: 2.5rem;
        border-radius: 8px;
        max-width: 450px;
        width: 90%;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        position: relative;
        transform: translateY(20px);
        opacity: 0;
        transition: transform 0.4s ease, opacity 0.3s ease;
    }
    
    .modal-overlay.active #signup-modal {
        transform: translateY(0);
        opacity: 1;
    }
    
    #signup-modal .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        font-size: 22px;
        cursor: pointer;
        color: #666;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s, color 0.2s;
    }
    
    #signup-modal .close-modal:hover {
        background-color: #f3f3f3;
        color: #333;
    }
    
    #signup-modal h3 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        color: #333;
        font-size: 1.6rem;
        text-align: center;
    }
    
    #signup-modal .form-group {
        margin-bottom: 1.25rem;
    }
    
    #signup-modal label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #444;
    }
    
    #signup-modal input, #signup-modal textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    
    #signup-modal input:focus, #signup-modal textarea:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
    }

    /* Ensure textarea cannot be manually resized and always matches input width */
    #signup-modal textarea {
        resize: none;
        overflow: hidden;
        width: 100%;
    }
    
    #signup-modal .signup-feedback {
        padding: 0.75rem;
        margin: 1rem 0;
        border-radius: 6px;
        font-size: 0.9rem;
        display: none;
    }
    
    #signup-modal .signup-feedback.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    #signup-modal .signup-feedback.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    #signup-modal .signup-feedback.show {
        display: block;
        animation: fadeIn 0.3s ease-in;
    }

    /* Replacement disclaimer */
    #signup-modal .replacement-disclaimer {
        background:#fff8e5;
        border:1px solid #f0d48a;
        color:#805800;
        padding:0.75rem 1rem;
        border-radius:6px;
        font-size:0.85rem;
        margin-bottom:1rem;
        line-height:1.3;
    }
    
    #signup-modal .tt-button-group, #signup-modal .button-group {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
    }
    
    #signup-modal button {
        background-color: #007cba;
        color: white;
        border: 0;
        outline: none;
        padding: 0.75rem 1.25rem;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s, transform 0.1s, box-shadow 0.2s;
        box-shadow: none;
    }
    
    #signup-modal button:hover {
        background-color: #006ba1;
    }
    
    #signup-modal button:active {
        transform: translateY(1px);
    }
    
    #signup-modal button.cancel-btn {
        background-color: #f5f5f5;
        color: #555;
        border: 0;
        box-shadow: none;
    }
    
    #signup-modal button.cancel-btn:hover {
        background-color: #e5e5e5;
    }
    
    #signup-modal button.loading {
        position: relative;
        color: transparent;
        pointer-events: none;
    }
    
    #signup-modal button.loading::after {
        content: "";
        position: absolute;
        top: calc(50% - 8px);
        left: calc(50% - 8px);
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.8s infinite linear;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>';

// Add JavaScript for front-end functionality
echo '<script>
if (!window.taskTableScriptLoaded) {
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
                    showFeedback("Bitte fülle alle Felder aus.", "error");
                    return;
                }
                
                showLoading(submitBtn);
                
                fetch("/wp-json/event-signups/v1/submit", {
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
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Network response was not ok");
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading(submitBtn);
                    
                    if (data.success) {
                        showFeedback("Deine Anmeldung wurde erfolgreich übermittelt und wird geprüft.", "success");
                        
                        // Close modal and reload after delay
                        setTimeout(() => {
                            closeModal();
                            window.location.reload();
                        }, 2000);
                    } else {
                        showFeedback(data.message || "Ein Fehler ist aufgetreten. Bitte versuche es später erneut.", "error");
                    }
                })
                .catch(error => {
                    hideLoading(submitBtn);
                    console.error("Error:", error);
                    showFeedback("Ein Fehler ist aufgetreten. Bitte versuche es später erneut.", "error");
                });
            });
        }

    });
}
</script>';