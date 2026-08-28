<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../config/config.php');

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$errorMessage = '';
$successMessage = '';

// --- SAVE SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newSettings = [
        'company_name'    => trim($_POST['company_name'] ?? ''),
        'company_email'   => trim($_POST['company_email'] ?? ''),
        'company_phone'   => trim($_POST['company_phone'] ?? ''),
        'company_address' => trim($_POST['company_address'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? '$'),
        'currency_code'   => trim($_POST['currency_code'] ?? 'USD'),
        'tax_rate'        => (float)($_POST['tax_rate'] ?? 0),
        'invoice_prefix'  => trim($_POST['invoice_prefix'] ?? 'INV-'),
        'reminder_days'   => array_map('intval', explode(',', trim($_POST['reminder_days'] ?? '3,7,14'))),
        'base_url'        => rtrim(trim($_POST['base_url'] ?? ''), '/')
    ];   

    file_put_contents($settings_file, json_encode($newSettings, JSON_PRETTY_PRINT));
    header('Location: settings.php?saved=1');
    exit;
}   

// --- SAVE SMTP SETTINGS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    $envFile = __DIR__ . '/../.env';
    $envContent = "SMTP_HOST=" . trim($_POST['smtp_host'] ?? '') . "\n";
    $envContent .= "SMTP_PORT=" . (int)($_POST['smtp_port'] ?? 587) . "\n";
    $envContent .= "SMTP_USER=" . trim($_POST['smtp_user'] ?? '') . "\n";
    $envContent .= "SMTP_PASS=" . trim($_POST['smtp_pass'] ?? '') . "\n";
    $envContent .= "SMTP_FROM=" . trim($_POST['smtp_from'] ?? '') . "\n";
    $envContent .= "SMTP_FROM_NAME=" . trim($_POST['smtp_from_name'] ?? '') . "\n";
    file_put_contents($envFile, $envContent);
    header('Location: settings.php?saved=1');
    exit;  
}

// Load current values
$settings = $site_settings;
$smtpHost = $_ENV['SMTP_HOST'] ?? '';
$smtpPort = $_ENV['SMTP_PORT'] ?? '587';
$smtpUser = $_ENV['SMTP_USER'] ?? '';
$smtpPass = $_ENV['SMTP_PASS'] ?? '';
$smtpFrom = $_ENV['SMTP_FROM'] ?? '';
$smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Invoice Automation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo">Invoice System</div>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="invoices.php">Invoices</a></li>
            <li class="active"><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Settings</h1>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <!-- COMPANY SETTINGS -->
        <div class="form-card">
            <h2>Company Information</h2>
            <form method="POST" class="data-form">
                <input type="hidden" name="save_settings" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Company Email</label>
                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Company Phone</label>
                        <input type="text" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Company Address</label>
                        <input type="text" name="company_address" value="<?php echo htmlspecialchars($settings['company_address'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? '$'); ?>" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Currency Code</label>
                        <input type="text" name="currency_code" value="<?php echo htmlspecialchars($settings['currency_code'] ?? 'USD'); ?>" maxlength="3">
                    </div>
                    <div class="form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate'] ?? 0); ?>" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV-'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Reminder Days (comma separated)</label>
                        <input type="text" name="reminder_days" value="<?php echo htmlspecialchars(implode(',', $settings['reminder_days'] ?? [3, 7, 14])); ?>" placeholder="3,7,14">
                    </div>
                </div>
                <div class="form-group">
                    <label>Website Base URL</label>
                    <input type="url" name="base_url" value="<?php echo htmlspecialchars($settings['base_url'] ?? ''); ?>" placeholder="https://yoursite.com/invoice-automation">
                </div>   
                <div class="form-actions">
                    <button type="submit" class="btn" style="background:#2271b1;color:#fff;">Save Company Settings</button>
                </div>
            </form>
        </div>

        <!-- SMTP SETTINGS -->
        <div class="form-card">
            <h2>Email (SMTP) Configuration</h2>
            <p style="font-size:13px;color:#64748b;margin-bottom:15px;">Used to send invoices and overdue reminders to clients.</p>
            <form method="POST" class="data-form">
                <input type="hidden" name="save_smtp" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($smtpHost); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($smtpPort); ?>" placeholder="587">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($smtpUser); ?>" placeholder="you@gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password / App Password</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($smtpPass); ?>" placeholder="Your app password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($smtpFrom); ?>" placeholder="invoices@yourcompany.com">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($smtpFromName); ?>" placeholder="My Company Invoices">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn" style="background:#198754;color:#fff;">Save SMTP Settings</button>
                </div>
            </form>
        </div>

        <!-- HELP -->
        <div class="form-card">
            <h2>SMTP Setup Help</h2>
            <div style="font-size:13px;color:#555;line-height:1.8;">
                <p><strong>Gmail:</strong></p>
                <ul style="margin-left:20px;margin-bottom:15px;">
                    <li>Host: <code>smtp.gmail.com</code></li>
                    <li>Port: <code>587</code></li>
                    <li>Username: Your Gmail address</li>
                    <li>Password: Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a> and generate one</li>
                </ul>
                <p><strong>Yahoo:</strong></p>
                <ul style="margin-left:20px;margin-bottom:15px;">
                    <li>Host: <code>smtp.mail.yahoo.com</code></li>
                    <li>Port: <code>587</code></li>
                    <li>Username: Your Yahoo address</li>
                    <li>Password: Go to Yahoo Account Security → App Passwords</li>
                </ul>
                <p><strong>Custom / cPanel:</strong></p>
                <ul style="margin-left:20px;">
                    <li>Host: <code>yourdomain.com</code></li>
                    <li>Port: <code>465</code> or <code>587</code></li>
                    <li>Username: Your email address</li>
                    <li>Password: Your email password</li>
                </ul>
            </div>
        </div>
    </main>
</div>

<style>
.form-card { background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:20px; margin-bottom:25px; }
.form-card h2 { font-size:16px; margin-bottom:15px; color:#1e293b; }
.data-form .form-row { display:flex; gap:15px; margin-bottom:15px; }
.data-form .form-group { flex:1; margin-bottom:10px; }
.data-form .form-group label { display:block; font-size:13px; font-weight:bold; color:#555; margin-bottom:5px; }
.data-form .form-group input { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box; }
.form-actions { display:flex; gap:10px; margin-top:15px; }
code { background:#f1f5f9; padding:2px 6px; border-radius:3px; font-size:12px; }
</style>
</body>
</html>   