<?php
// Prevent direct file access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// =========================================================================
// GMAIL SMTP CONFIGURATION CREDENTIALS [1]
// =========================================================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // Use Port 587 for secure STARTTLS [2]

// Your active registration email [1]
define('SMTP_USER', 'jonomdigitalindia@gmail.com');

// Paste the 16-character Google App Password you generated for jonomdigitalindia@gmail.com here (with NO spaces) [2]
define('SMTP_PASS', 'vjhciyooetptdcue'); 

define('SMTP_FROM', 'jonomdigitalindia@gmail.com');
define('SMTP_FROM_NAME', 'Jonom Digital Indian Distributor'); // Your custom Display Name [1]