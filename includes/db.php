<?php
// includes/db.php

// Start session immediately so $_SESSION is available before header.php is included.
// Uses session_status() guard to avoid double-start on pages that call session_start() themselves.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = 'mysql'; // Docker network hostname for mysql if used, or localhost
$dbname = 'al_fauzan_db';
$username = 'root'; // Update with your DB username
$password = 'root'; // Update with your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Fetch objects by default
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    // Ensure critical tables exist — one exec() per statement for MySQL PDO compatibility
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_name VARCHAR(50) NOT NULL,
        component_key VARCHAR(50) NOT NULL,
        component_value TEXT,
        UNIQUE(page_name, component_key)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT,
        action VARCHAR(100),
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        leads_profile_id INT NULL,
        phone_number VARCHAR(20) NOT NULL,
        message_body TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'Sent',
        api_response TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        description VARCHAR(255)
    )");

    // Seed default settings rows (INSERT IGNORE = skip if already exists)
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
        ('app_title',           'Admin',                    'Main Application Title'),
        ('whatsapp_provider',   'wawp',                     'Active WhatsApp provider (wawp or waha)'),
        ('wawp_api_token',      '',                         'WAWP API Bearer Token'),
        ('wawp_device_id',      '',                         'WAWP Device ID'),
        ('wawp_server',         'https://api.wawp.net',     'WAWP Server URL'),
        ('waha_server_url',     '',                         'WAHA Server URL (e.g. http://localhost:3000)'),
        ('waha_api_key',        '',                         'WAHA API Key (X-Api-Key header)'),
        ('waha_session',        'default',                  'WAHA Session Name'),
        ('smtp_host',           '',                         'SMTP Host'),
        ('smtp_port',           '587',                      'SMTP Port'),
        ('smtp_encryption',     'tls',                      'SMTP Encryption: tls (STARTTLS/587), ssl (465), none (25)'),
        ('smtp_user',           '',                         'SMTP Username'),
        ('smtp_pass',           '',                         'SMTP Password'),
        ('smtp_from_email',     '',                         'SMTP From Email'),
        ('smtp_from_name',      '',                         'SMTP From Name')
    ");

} catch (PDOException $e) {
    // Log error and display generic message
    error_log("Connection failed: " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    die("Database connection failed. Please check the system logs.");
}

// Load base_url and app_title from settings
$base_url  = '';
$app_title = 'Admin';
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('base_url', 'app_title')");
    foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
        if ($row->setting_key === 'base_url' && $row->setting_value) {
            $base_url = rtrim($row->setting_value, '/');
        }
        if ($row->setting_key === 'app_title' && $row->setting_value) {
            $app_title = $row->setting_value;
        }
    }
} catch (Exception $e) {
    $base_url  = '';
    $app_title = 'Admin';
}
if (empty($base_url)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_url = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

// BASE_PATH: the subfolder the app lives in, e.g. '/admin' or '' for root.
// Auto-detected from the script path so it works on both root and subfolders.
if (!defined('BASE_PATH')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Walk up until we find the app root (where db.php's includes/ folder sits)
    // We detect by checking if the current script is inside an 'includes' subfolder
    if (basename($scriptDir) === 'includes') {
        $scriptDir = dirname($scriptDir);
    }
    define('BASE_PATH', rtrim($scriptDir === '/' ? '' : $scriptDir, '/'));
}
?>