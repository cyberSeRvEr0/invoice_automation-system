<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/functions.php');   

$db = get_db_connection();
$invoicesTable = table_name('invoices');
$itemsTable = table_name('invoice_items');
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

$itemsStmt = $db->prepare("SELECT * FROM $itemsTable WHERE invoice_id = :inv_id");
$itemsStmt->execute([':inv_id' => $invoiceId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$settings = $site_settings;
$invoiceHtml = build_invoice_html($invoice, $items, $client, $settings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($invoice['invoice_id']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; }
        .invoice-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .company-info h1 { font-size: 22px; color: #222; }
        .company-info p { font-size: 13px; color: #666; margin-top: 4px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 28px; color: #222; }
        .invoice-meta p { font-size: 13px; color: #666; margin-top: 4px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; color: #fff; font-size: 12px; font-weight: bold; margin-top: 8px; }
        .bill-to { margin-bottom: 30px; }
        .bill-to h3 { font-size: 14px; color: #999; text-transform: uppercase; margin-bottom: 8px; }
        .bill-to p { font-size: 14px; line-height: 1.6; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.items th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 12px; color: #666; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        table.items th:nth-child(2), table.items td:nth-child(2) { text-align: center; }
        table.items th:nth-child(3), table.items td:nth-child(3),
        table.items th:nth-child(4), table.items td:nth-child(4) { text-align: right; }
        .totals { margin-top: 20px; margin-left: auto; width: 280px; }
        .totals-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .totals-row.grand { border-top: 2px solid #333; margin-top: 8px; padding-top: 12px; font-size: 18px; font-weight: bold; }
        .notes { margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #555; }
        .notes h4 { font-size: 12px; color: #999; text-transform: uppercase; margin-bottom: 6px; }
        .action-bar { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .action-bar button { padding: 12px 30px; background: #2271b1; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .action-bar button:hover { background: #1a5c99; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice-container { box-shadow: none; border-radius: 0; padding: 20px; }
            .action-bar { display: none; }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="invoice-header">
        <div class="company-info">
            <h1><?php echo htmlspecialchars($settings['company_name'] ?? 'My Company'); ?></h1>
            <p><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></p>
            <p><?php echo htmlspecialchars($settings['company_email'] ?? ''); ?> | <?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?></p>
        </div>
        <div class="invoice-meta">
            <h2>INVOICE</h2>
            <p><strong>Number:</strong> <?php echo htmlspecialchars($invoice['invoice_id']); ?></p>
            <p><strong>Issue Date:</strong> <?php echo date('M d, Y', strtotime($invoice['issue_date'])); ?></p>
            <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></p>
            <?php
            $statusColors = ['DRAFT' => '#6c757d', 'SENT' => '#0d6efd', 'PAID' => '#198754', 'OVERDUE' => '#dc3545'];
            $statusColor = $statusColors[$invoice['status']] ?? '#6c757d';
            ?>
            <span class="status-badge" style="background:<?php echo $statusColor; ?>"><?php echo strtoupper($invoice['status']); ?></span>
        </div>
    </div>

    <div class="bill-to">
        <h3>Bill To</h3>
        <p><strong><?php echo htmlspecialchars($client['name']); ?></strong></p>
        <?php if (!empty($client['company'])): ?><p><?php echo htmlspecialchars($client['company']); ?></p><?php endif; ?>
        <p><?php echo htmlspecialchars($client['email']); ?></p>
        <?php if (!empty($client['phone'])): ?><p><?php echo htmlspecialchars($client['phone']); ?></p><?php endif; ?>
        <?php if (!empty($client['address'])): ?><p><?php echo nl2br(htmlspecialchars($client['address'])); ?></p><?php endif; ?>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $settings['currency_symbol'] ?? '$'; ?> <?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo $settings['currency_symbol'] ?? '$'; ?> <?php echo number_format($item['line_total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row"><span>Subtotal</span><span><?php echo $settings['currency_symbol'] ?? '$'; ?> <?php echo number_format($invoice['subtotal'], 2); ?></span></div>
        <div class="totals-row"><span>Tax (<?php echo $settings['tax_rate'] ?? 0; ?>%)</span><span><?php echo $settings['currency_symbol'] ?? '$'; ?> <?php echo number_format($invoice['tax_amount'], 2); ?></span></div>
        <div class="totals-row grand"><span>Total</span><span><?php echo $settings['currency_symbol'] ?? '$'; ?> <?php echo number_format($invoice['total'], 2); ?></span></div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
        <div class="notes">
            <h4>Notes</h4>
            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="action-bar">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>
</body>
</html>   