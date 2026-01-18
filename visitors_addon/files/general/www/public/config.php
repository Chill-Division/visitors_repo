<?php

date_default_timezone_set('Pacific/Auckland');

// Error reporting - disable in production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://ajax.googleapis.com; style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Constants for rate limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes in seconds

// Get admin password from Home Assistant addon config
// Try local copy first (from startup script), then direct mount, then strict default
$configLocations = [
    __DIR__ . '/options.json',
    '/visitors_config/options.json',
    '/data/options.json'
];

$configFound = false;
$adminPassword = null;

foreach ($configLocations as $configPath) {
    error_log("DEBUG: Checking for config at: " . $configPath);

    if (file_exists($configPath)) {
        error_log("DEBUG: Config file found at " . $configPath);
        $json_content = file_get_contents($configPath);

        if ($json_content === false) {
            error_log("ERROR: Failed to read content from " . $configPath);
            continue;
        }

        $config = json_decode($json_content, true);
        if ($config === null) {
            error_log("ERROR: Failed to decode JSON from " . $configPath . ". Error: " . json_last_error_msg());
        } else {
            $configFound = true;
            $adminPassword = $config['admin_password'] ?? null;
            if ($adminPassword) {
                error_log("DEBUG: admin_password found in " . $configPath . " (length: " . strlen($adminPassword) . ")");
            } else {
                error_log("WARNING: admin_password key missing or empty in " . $configPath);
            }
            break; // Stop checking if we found a valid file
        }
    } else {
        error_log("DEBUG: Config file NOT found at " . $configPath);
    }
}

if (!$configFound) {
    error_log("WARNING: No valid config file found in any location.");
}

if (!$adminPassword) {
    error_log("DEBUG: Using default password.");
    $adminPassword = "cannabis";
}

// Database configuration with restricted permissions
$dbfile = __DIR__ . '/visitor_signin.db';
$dbPermissions = 0600; // Read/write for owner only

// Ensure database file has correct permissions
if (file_exists($dbfile)) {
    chmod($dbfile, $dbPermissions);
}

// Function to validate and sanitize input
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to validate visitor name
function validate_visitor_name($name)
{
    return !empty($name) && strlen($name) <= 100 && preg_match('/^[a-zA-Z0-9\s\-\'\.]+$/', $name);
}

// Function to validate contact number
function validate_contact($contact)
{
    return !empty($contact) && strlen($contact) <= 20 && preg_match('/^[0-9\+\-\(\)\s]+$/', $contact);
}
?>