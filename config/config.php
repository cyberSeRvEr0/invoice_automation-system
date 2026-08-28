<?php
// =====================================================
// INVOICE AUTOMATION SYSTEM - CONFIGURATION
// =====================================================

// --- MANUAL .env LOADER ---
function load_env($file) {
    if (!file_exists($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $pair = explode('=', $line, 2);
        if (count($pair) === 2) {
            $_ENV[trim($pair[0])] = trim($pair[1], "\"' ");
        }
    }
}
load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/../includes/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../includes/phpmailer/SMTP.php';
require_once __DIR__ . '/../includes/phpmailer/Exception.php';
// -----------------------------------------------

// --- DATABASE ---
$db_type = 'sqlite';
$db_file = __DIR__ . '/../data/database.sqlite';

$db_host = 'localhost';
$db_name = 'invoice_db';
$db_user = 'root';
$db_pass = '';
$db_prefix = 'inv_';

// --- TIMEZONE ---
$app_timezone = 'UTC';
date_default_timezone_set($app_timezone);

// --- SMTP (For sending invoices and reminders) ---
$smtp_host     = $_ENV['SMTP_HOST'] ?? '';
$smtp_port     = (int)($_ENV['SMTP_PORT'] ?? 587);
$smtp_user     = $_ENV['SMTP_USER'] ?? '';
$smtp_pass     = $_ENV['SMTP_PASS'] ?? '';
$smtp_from     = $_ENV['SMTP_FROM'] ?? '';
$smtp_from_name = $_ENV['SMTP_FROM_NAME'] ?? 'Invoice System';

// --- FILE PATHS ---
$base_path = dirname(__DIR__);
$data_dir = $base_path . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
$uploads_dir = $base_path . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

// --- SETTINGS ---
$settings_file = $data_dir . 'settings.json';
$site_settings = file_exists($settings_file) ? json_decode(file_get_contents($settings_file), true) : [];

$company_name    = $site_settings['company_name'] ?? 'My Company';
$company_email   = $site_settings['company_email'] ?? '';
$company_phone   = $site_settings['company_phone'] ?? '';
$company_address = $site_settings['company_address'] ?? '';
$currency_symbol = $site_settings['currency_symbol'] ?? '$';
$currency_code   = $site_settings['currency_code'] ?? 'USD';
$tax_rate        = (float)($site_settings['tax_rate'] ?? 0);
$invoice_prefix  = $site_settings['invoice_prefix'] ?? 'INV-';
$base_url        = $site_settings['base_url'] ?? '';   

// --- REMINDER SCHEDULE (days after due date) ---
$reminder_days = $site_settings['reminder_days'] ?? [3, 7, 14];

// --- DATABASE CONNECTION ---
function get_db_connection() {
    global $db_type, $db_file, $db_host, $db_name, $db_user, $db_pass, $db_prefix;
    if ($db_type === 'sqlite') {
        $pdo = new PDO("sqlite:" . $db_file);
    } else {
        $pdo = new PDO(
            "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4",
            $db_user,
            $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function table_name($base) {
    global $db_type, $db_prefix;
    if ($db_type === 'mysql') {
        return $db_prefix . $base;
    }
    return $base;
}

// --- EMAIL SENDING (using PHPMailer via cURL fallback) ---
function send_email($to, $to_name, $subject, $html_body, $attachments = []) {
    global $smtp_host, $smtp_port, $smtp_user, $smtp_pass, $smtp_from, $smtp_from_name;

    if (empty($smtp_host) || empty($smtp_user)) {
        error_log("SMTP not configured.");
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->Port = $smtp_port;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom($smtp_from, $smtp_from_name);
        $mail->addAddress($to, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    } catch (\Throwable $e) {
        error_log("Email fatal: " . $e->getMessage());
        return false;
    }
}    

// --- GENERATE UNIQUE INVOICE ID ---
function generate_invoice_id($prefix) {
    return $prefix . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}   