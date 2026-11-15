/**
 * Admin JavaScript for Task Table plugin
 */
document.addEventListener('DOMContentLoaded', function() {
    // Email settings tab functionality
    const tabLinks = document.querySelectorAll('.email-templates-tabs .nav-tab');
    
    if (tabLinks.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get target ID
                const targetId = this.getAttribute('href');
                const targetTab = document.querySelector(targetId);
                
                // Hide all tabs and remove active class
                document.querySelectorAll('.email-template-tab').forEach(tab => {
                    tab.style.display = 'none';
                });
                document.querySelectorAll('.nav-tab').forEach(tab => {
                    tab.classList.remove('nav-tab-active');
                });
                
                // Show the target tab and add active class to the link
                if (targetTab) {
                    targetTab.style.display = 'block';
                }
                this.classList.add('nav-tab-active');
            });
        });
    }
});
