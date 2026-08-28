<?php
// =====================================================
// INVOICE AUTOMATION SYSTEM - INSTALLER
// Run this once in your browser, then DELETE this file.
// =====================================================

require_once('config/config.php');

$clientsTable = table_name('clients');
$invoicesTable = table_name('invoices');
$itemsTable = table_name('invoice_items');
$errors = [];
$successes = [];

try {
    $db = get_db_connection();

    if ($db_type === 'sqlite') {
        if (!file_exists($db_file)) {
            $db = new PDO("sqlite:" . $db_file);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $successes[] = "Created SQLite database file: " . basename($db_file);
        }

        // Clients Table
        $db->exec("CREATE TABLE IF NOT EXISTS $clientsTable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT DEFAULT '',
            company TEXT DEFAULT '',
            address TEXT DEFAULT '',
            created_at TEXT DEFAULT ''
        )");
        $successes[] = "SQLite '$clientsTable' table created.";

        // Invoices Table
        $db->exec("CREATE TABLE IF NOT EXISTS $invoicesTable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id TEXT NOT NULL UNIQUE,
            client_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'DRAFT',
            issue_date TEXT NOT NULL,
            due_date TEXT NOT NULL,
            subtotal REAL NOT NULL DEFAULT 0,
            tax_amount REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            notes TEXT DEFAULT '',
            paid_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT ''
        )");
        $successes[] = "SQLite '$invoicesTable' table created.";

        // Invoice Items Table
        $db->exec("CREATE TABLE IF NOT EXISTS $itemsTable (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id TEXT NOT NULL,
            description TEXT NOT NULL,
            quantity REAL NOT NULL DEFAULT 1,
            unit_price REAL NOT NULL DEFAULT 0,
            line_total REAL NOT NULL DEFAULT 0
        )");
        $successes[] = "SQLite '$itemsTable' table created.";

        // Users Table
        $db->exec("CREATE TABLE IF NOT EXISTS " . table_name('users') . " (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'customer',
            created_at TEXT DEFAULT ''
        )");
        $successes[] = "SQLite 'users' table created.";   

    } else {
        // MySQL
        $db->exec("CREATE TABLE IF NOT EXISTS $clientsTable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) DEFAULT '',
            company VARCHAR(100) DEFAULT '',
            address TEXT DEFAULT '',
            created_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $successes[] = "MySQL '$clientsTable' table created.";

        $db->exec("CREATE TABLE IF NOT EXISTS $invoicesTable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id VARCHAR(50) NOT NULL UNIQUE,
            client_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            issue_date DATE NOT NULL,
            due_date DATE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            notes TEXT DEFAULT '',
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $successes[] = "MySQL '$invoicesTable' table created.";

        $db->exec("CREATE TABLE IF NOT EXISTS $itemsTable (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $successes[] = "MySQL '$itemsTable' table created.";

        $db->exec("CREATE TABLE IF NOT EXISTS " . table_name('users') . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'customer',
            created_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $successes[] = "MySQL 'users' table created.";   
    }

    // Create settings file if it doesn't exist
    if (!file_exists($settings_file)) {
        $defaultSettings = [
            'company_name' => 'My Company',
            'company_email' => '',
            'company_phone' => '',
            'company_address' => '',
            'currency_symbol' => '$',
            'currency_code' => 'USD',
            'tax_rate' => 0,
            'invoice_prefix' => 'INV-',
            'reminder_days' => [3, 7, 14]
        ];
        file_put_contents($settings_file, json_encode($defaultSettings, JSON_PRETTY_PRINT));
        $successes[] = "Created default settings.json";

    }

    // Create uploads folder
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
        $successes[] = "Created uploads folder";
    }

} catch (PDOException $e) {
    $errors[] = "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $errors[] = "Error: " . $e->getMessage();
}

echo "<!DOCTYPE html><html><head><title>Installer</title></head><body style='font-family:sans-serif;max-width:600px;margin:50px auto;padding:20px;'>";
echo "<h1>Invoice Automation System - Installer</h1>";

if (empty($errors)) {
    echo "<div style='background:#d4edda;border-left:4px solid #28a745;padding:15px;margin:20px 0;'>";
    echo "<h3 style='margin-top:0;color:#155724;'>Installation Successful</h3>";
    foreach ($successes as $msg) {
        echo "<p style='margin:5px 0;color:#155724;'>✅ $msg</p>";
    }
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;border-left:4px solid #dc3545;padding:15px;margin:20px 0;'>";
    echo "<h3 style='margin-top:0;color:#721c24;'>Installation Errors</h3>";
    foreach ($errors as $msg) {
        echo "<p style='margin:5px 0;color:#721c24;'>❌ $msg</p>";
    }
    echo "</div>";
}

echo "<div style='background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;'>";
echo "<h3 style='margin-top:0;color:#856404;'>⚠️ Important: Delete This File</h3>";
echo "<p style='margin:5px 0;color:#856404;'>For security, <strong>delete install.php</strong> from your server now.</p>";
echo "</div>";

echo "<p><a href='admin/dashboard.php' style='background:#2271b1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;'>Go to Dashboard →</a></p>";
echo "</body></html>";   