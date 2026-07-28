<?php
// Prevent direct file access
if (!defined('SECURE_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// System Configurations
define('SUPABASE_URL', 'https://txbifjicxgdzfnmdruxy.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InR4YmlmamljeGdkemZubWRydXh5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODM0MDEyMTIsImV4cCI6MjA5ODk3NzIxMn0.IKrOpsKb8VhIjjJbBadi7h1o4N6PtnHsfbTil9SLip8');
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InR4YmlmamljeGdkemZubWRydXh5Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MzQwMTIxMiwiZXhwIjoyMDk4OTc3MjEyfQ.f4DRGIc-z42Vd0tNK8aURH2-dc-3UahHuOnTWK5w-w4'); // Used securely only on the server-side

// Application URL
define('SITE_URL', 'https://jddashboard.unaux.com');

// Error Reporting (Turn off in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_errors.log');