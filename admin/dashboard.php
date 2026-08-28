<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/functions.php');  // ← ADD THIS   

// Simple login gate (first user = admin, same as restaurant system)
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$db = get_db_connection();
$clientsTable = table_name('clients');
$invoicesTable = table_name('invoices');
$itemsTable = table_name('invoice_items');

// --- STATS ---
$totalInvoices = $db->query("SELECT COUNT(*) FROM $invoicesTable")->fetchColumn();
$paidInvoices = $db->query("SELECT COUNT(*) FROM $invoicesTable WHERE status = 'PAID'")->fetchColumn();
$overdueInvoices = $db->query("SELECT COUNT(*) FROM $invoicesTable WHERE status = 'OVERDUE'")->fetchColumn();
$pendingInvoices = $db->query("SELECT COUNT(*) FROM $invoicesTable WHERE status = 'SENT'")->fetchColumn();
$totalRevenue = $db->query("SELECT COALESCE(SUM(total), 0) FROM $invoicesTable WHERE status = 'PAID'")->fetchColumn();
$pendingAmount = $db->query("SELECT COALESCE(SUM(total), 0) FROM $invoicesTable WHERE status IN ('SENT', 'OVERDUE')")->fetchColumn();

// --- RECENT INVOICES (last 10) ---
$recentInvoices = $db->query("SELECT i.*, c.name as client_name, c.email as client_email FROM $invoicesTable i LEFT JOIN $clientsTable c ON i.client_id = c.id ORDER BY i.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// --- CHECK REMINDERS (run on every dashboard load) ---
$remindersSent = 0;
if (isset($_POST['run_reminders'])) {
    $remindersSent = check_and_send_reminders();
}

$currency = $currency_symbol;
$statusColors = ['DRAFT' => '#6c757d', 'SENT' => '#0d6efd', 'PAID' => '#198754', 'OVERDUE' => '#dc3545'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Invoice Automation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo">Invoice System</div>
        <ul>
            <li class="active"><a href="dashboard.php">Dashboard</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li><a href="invoices.php">Invoices</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Dashboard</h1>
            <div class="topbar-right">
                <form method="POST" style="display:inline;">
                    <button type="submit" name="run_reminders" value="1" class="btn btn-warning" onclick="return confirm('Send overdue reminders now?')">Send Reminders</button>
                </form>
                <span class="greeting">Howdy, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
            </div>
        </div>

        <?php if ($remindersSent > 0): ?>
            <div class="alert alert-success"><?php echo $remindersSent; ?> reminder(s) sent successfully.</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Invoices</h3>
                <p class="stat-value"><?php echo $totalInvoices; ?></p>
            </div>
            <div class="stat-card">
                <h3>Paid</h3>
                <p class="stat-value green"><?php echo $paidInvoices; ?></p>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <p class="stat-value blue"><?php echo $pendingInvoices; ?></p>
            </div>
            <div class="stat-card">
                <h3>Overdue</h3>
                <p class="stat-value red"><?php echo $overdueInvoices; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <p class="stat-value green"><?php echo $currency; ?> <?php echo number_format($totalRevenue, 2); ?></p>
            </div>
            <div class="stat-card">
                <h3>Outstanding</h3>
                <p class="stat-value orange"><?php echo $currency; ?> <?php echo number_format($pendingAmount, 2); ?></p>
            </div>
        </div>

        <div class="table-card">
            <h2>Recent Invoices</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr><td colspan="7" class="empty-state">No invoices yet. <a href="invoices.php">Create one</a>.</td></tr>
                    <?php else: foreach ($recentInvoices as $inv): ?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars($inv['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['issue_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                            <td><strong><?php echo $currency; ?> <?php echo number_format($inv['total'], 2); ?></strong></td>
                            <td><span class="badge" style="background:<?php echo $statusColors[$inv['status']] ?? '#6c757d'; ?>"><?php echo $inv['status']; ?></span></td>
                            <td>
                                <a href="../public/view.php?invoice=<?php echo urlencode($inv['invoice_id']); ?>" target="_blank" class="btn btn-sm">View</a>
                                <?php if ($inv['status'] !== 'PAID'): ?>
                                <a href="invoices.php?id=<?php echo $inv['id']; ?>&mark_paid=1" class="btn btn-sm btn-success">Mark Paid</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>   