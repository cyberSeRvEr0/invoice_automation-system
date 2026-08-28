<?php
require_once(__DIR__ . '/../config/config.php');

$db = get_db_connection();
$invoicesTable = table_name('invoices');
$clientsTable = table_name('clients');

$invoiceId = $_GET['invoice'] ?? '';

if (empty($invoiceId)) {
    die("Invoice not found.");
}

$stmt = $db->prepare("SELECT * FROM $invoicesTable WHERE invoice_id = :inv_id");
$stmt->execute([':inv_id' => $invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

$clientStmt = $db->prepare("SELECT * FROM $clientsTable WHERE id = :id");
$clientStmt->execute([':id' => $invoice['client_id']]);
$client = $clientStmt->fetch(PDO::FETCH_ASSOC);

$settings = $site_settings;
$currency = $settings['currency_symbol'] ?? '$';

// If already paid, redirect to view
if ($invoice['status'] === 'PAID') {
    header("Location: view.php?invoice=" . urlencode($invoiceId));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice - <?php echo htmlspecialchars($invoice['invoice_id']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .pay-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px; text-align: center; }
        .pay-card h1 { font-size: 20px; color: #1e293b; margin-bottom: 8px; }
        .pay-card .invoice-ref { font-family: monospace; font-size: 14px; color: #64748b; margin-bottom: 25px; }
        .amount-display { font-size: 42px; font-weight: bold; color: #1e293b; margin: 20px 0; }
        .amount-display span { font-size: 18px; color: #64748b; font-weight: normal; }
        .due-info { font-size: 14px; color: #64748b; margin-bottom: 25px; }
        .due-info .overdue { color: #dc3545; font-weight: bold; }
        .pay-methods { text-align: left; margin-bottom: 25px; }
        .pay-methods h3 { font-size: 14px; color: #555; margin-bottom: 12px; text-transform: uppercase; }
        .pay-method { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8f9fa; border-radius: 6px; margin-bottom: 10px; font-size: 14px; }
        .pay-method .icon { font-size: 20px; }
        .pay-method .label { font-weight: bold; color: #333; }
        .pay-method .detail { color: #64748b; font-size: 13px; }
        .btn-view { display: inline-block; padding: 12px 30px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold; margin-top: 10px; }
        .btn-view:hover { background: #1a5c99; }
        .btn-secondary { display: inline-block; padding: 12px 20px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; margin-top: 10px; margin-left: 10px; }
        .btn-secondary:hover { background: #5a6268; }
        .status-note { margin-top: 20px; padding: 12px; background: #fff3cd; border-radius: 6px; font-size: 13px; color: #856404; }
    </style>
</head>
<body>
<div class="pay-card">
    <h1><?php echo htmlspecialchars($settings['company_name'] ?? 'My Company'); ?></h1>
    <p class="invoice-ref">Invoice: <?php echo htmlspecialchars($invoice['invoice_id']); ?></p>

    <div class="amount-display">
        <?php echo $currency; ?> <?php echo number_format($invoice['total'], 2); ?>
        <span>total due</span>
    </div>

    <div class="due-info">
        <?php if (strtotime($invoice['due_date']) < strtotime(date('Y-m-d'))): ?>
            <span class="overdue">Overdue since <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
        <?php else: ?>
            Due date: <strong><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></strong>
        <?php endif; ?>
    </div>

    <div class="pay-methods">
        <h3>Payment Methods</h3>

        <?php if (!empty($settings['company_email'])): ?>
        <div class="pay-method">
            <span class="icon">💳</span>
            <div>
                <div class="label">Bank Transfer / Email</div>
                <div class="detail">Contact: <?php echo htmlspecialchars($settings['company_email']); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($settings['company_phone'])): ?>
        <div class="pay-method">
            <span class="icon">📞</span>
            <div>
                <div class="label">Phone / Mobile Money</div>
                <div class="detail"><?php echo htmlspecialchars($settings['company_phone']); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($settings['payment_instructions'] ?? '')): ?>
        <div class="pay-method">
            <span class="icon">📋</span>
            <div>
                <div class="label">Instructions</div>
                <div class="detail"><?php echo nl2br(htmlspecialchars($settings['payment_instructions'])); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="status-note">
        After making your payment, please contact us with your transaction reference. We will mark this invoice as paid and you will receive a confirmation.
    </div>

    <div style="margin-top:25px;">
        <a href="view.php?invoice=<?php echo urlencode($invoiceId); ?>" class="btn-view">View Full Invoice</a>
        <a href="view.php?invoice=<?php echo urlencode($invoiceId); ?>" class="btn-secondary" onclick="window.print(); return false;">Print</a>
    </div>
</div>
</body>
</html>   