<?php
/**
 * Dev only: route all wp_mail() through the Mailpit container so emails can be inspected at http://localhost:8025.
 * Mounted as a must-use plugin by dev/up.sh. Never ship this file.
 */
// WordPress defaults to wordpress@localhost here, which PHPMailer rejects as invalid.
add_filter('wp_mail_from', function () {
    return 'wordpress@example.test';
});

add_action('phpmailer_init', function ($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = '127.0.0.1';
    $phpmailer->Port = 1025;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPAutoTLS = false;
});
