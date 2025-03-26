/******/ (() => { // webpackBootstrap
/*!********************************!*\
  !*** ./src/task-table/view.js ***!
  \********************************/
/**
 * Frontend JavaScript for the Task Table block.
 */

document.addEventListener("DOMContentLoaded", function () {
  // Initialize only once
  if (window.taskTableScriptLoaded) return;
  window.taskTableScriptLoaded = true;

  // Get elements
  const signupButtons = document.querySelectorAll(".event-signup");
  const modal = document.getElementById("signup-modal");
  const form = document.getElementById("signup-form");
  const notification = document.getElementById("signup-notification");

  // Show notification function
  function showNotification(message, type) {
    notification.textContent = message;
    notification.className = "signup-notification";
    notification.classList.add(type);
    notification.classList.add("show");

    // Hide notification after animation completes
    setTimeout(() => {
      notification.classList.remove("show");
    }, 5000);
  }

  // Add event listeners to signup buttons
  signupButtons.forEach(button => {
    button.addEventListener("click", function () {
      const eventId = this.dataset.eventId;
      document.getElementById("event-id").value = eventId;
      modal.classList.add("active");
    });
  });

  // Close modal when clicking outside of it
  window.addEventListener("click", function (event) {
    if (event.target === modal) {
      modal.classList.remove("active");
    }
  });

  // Handle form submission
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    const data = {
      event_id: document.getElementById("event-id").value,
      user_name: document.getElementById("user-name").value,
      user_email: document.getElementById("user-email").value
    };
    fetch("/wp-json/event-signups/v1/submit", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(data)
    }).then(response => response.json()).then(result => {
      if (result.success) {
        showNotification("Sign-up submitted successfully! You'll receive an email when approved.", "success");
        modal.classList.remove("active");
        form.reset();

        // Disable the sign-up button for this event
        const eventId = data.event_id;
        const button = document.querySelector(`.event-signup[data-event-id="${eventId}"]`);
        if (button) {
          button.textContent = "Sign-up pending";
          button.disabled = true;
          button.classList.add("pending");
        }
      } else {
        showNotification("Sign-up submission failed: " + (result.message || "Unknown error"), "error");
      }
    }).catch(error => {
      console.error("Error submitting sign-up:", error);
      showNotification("Error submitting sign-up. Please try again.", "error");
    });
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map