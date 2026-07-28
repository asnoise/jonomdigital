<?php
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'jonomdigitalindia@gmail.com');
define('SMTP_PASS', 'vjhciyooetptdcue');
define('SMTP_FROM', 'jonomdigitalindia@gmail.com');
define('SMTP_FROM_NAME', 'Jonom Digital Indian Distributor');
