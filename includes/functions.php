<?php
// =====================================================
// INVOICE AUTOMATION SYSTEM - SHARED FUNCTIONS
// =====================================================

require_once __DIR__ . '/../config/config.php';

// --- CALCULATE INVOICE TOTALS ---
function calculate_invoice_totals($items, $tax_rate) {
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float)$item['quantity'] * (float)$item['unit_price'];
    }
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total = $subtotal + $tax_amount;
    return [
        'subtotal'   => round($subtotal, 2),
        'tax_amount' => round($tax_amount, 2),
        'total'      => round($total, 2)
    ];
}

// --- BUILD PRINTABLE INVOICE HTML ---
function build_invoice_html($invoice, $items, $client, $settings) {
    $currency = $settings['currency_symbol'] ?? '$';
    $statusColors = [
        'DRAFT'     => '#6c757d',
        'SENT'      => '#0d6efd',
        'PAID'      => '#198754',
        'OVERDUE'   => '#dc3545'
    ];
    $statusColor = $statusColors[$invoice['status']] ?? '#6c757d';

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= "<tr>
            <td style='padding:10px 12px;border-bottom:1px solid #eee;'>{$item['description']}</td>
            <td style='padding:10px 12px;border-bottom:1px solid #eee;text-align:center;'>{$item['quantity']}</td>
            <td style='padding:10px 12px;border-bottom:1px solid #eee;text-align:right;'>{$currency} " . number_format($item['unit_price'], 2) . "</td>
            <td style='padding:10px 12px;border-bottom:1px solid #eee;text-align:right;'>{$currency} " . number_format($item['line_total'], 2) . "</td>
        </tr>";
    }

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$invoice['invoice_id']}</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; color: #333; padding: 40px; }
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
            table.items th:nth-child(2), table.items th:nth-child(3), table.items th:nth-child(4) { text-align: center; }
            table.items th:nth-child(3), table.items th:nth-child(4) { text-align: right; }
            .totals { margin-top: 20px; margin-left: auto; width: 280px; }
            .totals-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
            .totals-row.grand { border-top: 2px solid #333; margin-top: 8px; padding-top: 12px; font-size: 18px; font-weight: bold; }
            .notes { margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #555; }
            .notes h4 { font-size: 12px; color: #999; text-transform: uppercase; margin-bottom: 6px; }
            @media print {
                body { padding: 20px; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class='invoice-header'>
            <div class='company-info'>
                <h1>" . htmlspecialchars($settings['company_name'] ?? 'My Company') . "</h1>
                <p>" . htmlspecialchars($settings['company_address'] ?? '') . "</p>
                <p>" . htmlspecialchars($settings['company_email'] ?? '') . " | " . htmlspecialchars($settings['company_phone'] ?? '') . "</p>
            </div>
            <div class='invoice-meta'>
                <h2>INVOICE</h2>
                <p><strong>Number:</strong> " . htmlspecialchars($invoice['invoice_id']) . "</p>
                <p><strong>Issue Date:</strong> " . date('M d, Y', strtotime($invoice['issue_date'])) . "</p>
                <p><strong>Due Date:</strong> " . date('M d, Y', strtotime($invoice['due_date'])) . "</p>
                <span class='status-badge' style='background:" . $statusColor . ";'>" . strtoupper($invoice['status']) . "</span>
            </div>
        </div>

        <div class='bill-to'>
            <h3>Bill To</h3>
            <p><strong>" . htmlspecialchars($client['name']) . "</strong></p>
            <p>" . htmlspecialchars($client['company'] ?? '') . "</p>
            <p>" . htmlspecialchars($client['email']) . "</p>
            <p>" . htmlspecialchars($client['phone'] ?? '') . "</p>
            <p>" . htmlspecialchars($client['address'] ?? '') . "</p>
        </div>

        <table class='items'>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>{$itemsHtml}</tbody>
        </table>

        <div class='totals'>
            <div class='totals-row'><span>Subtotal</span><span>{$currency} " . number_format($invoice['subtotal'], 2) . "</span></div>
            <div class='totals-row'><span>Tax (" . $settings['tax_rate'] . "%)</span><span>{$currency} " . number_format($invoice['tax_amount'], 2) . "</span></div>
            <div class='totals-row grand'><span>Total</span><span>{$currency} " . number_format($invoice['total'], 2) . "</span></div>
        </div>

        " . (!empty($invoice['notes']) ? "<div class='notes'><h4>Notes</h4><p>" . htmlspecialchars($invoice['notes']) . "</p></div>" : "") . "

        <div class='no-print' style='margin-top:40px;text-align:center;'>
            <button onclick='window.print()' style='padding:12px 30px;background:#2271b1;color:#fff;border:none;border-radius:6px;font-size:14px;cursor:pointer;'>Print / Save as PDF</button>
        </div>
    </body>
    </html>";

    return $html;
}

// --- BUILD EMAIL BODY ---
function build_email_body($invoice, $client, $settings) {
    $currency = $settings['currency_symbol'] ?? '$';
    $company = htmlspecialchars($settings['company_name'] ?? 'My Company');
    $viewUrl = $settings['base_url'] ?? '';

    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
        <div style='background:#222;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>
            <h1 style='color:#fff;font-size:20px;margin:0;'>{$company}</h1>
        </div>
        <div style='background:#fff;padding:30px;border:1px solid #eee;border-top:none;border-radius:0 0 8px 8px;'>
            <h2 style='font-size:18px;color:#333;margin:0 0 15px 0;'>Invoice " . htmlspecialchars($invoice['invoice_id']) . "</h2>
            <p style='font-size:14px;color:#555;line-height:1.6;'>Hi " . htmlspecialchars($client['name']) . ",</p>
            <p style='font-size:14px;color:#555;line-height:1.6;'>Please find your invoice below. The total amount due is <strong>{$currency} " . number_format($invoice['total'], 2) . "</strong>.</p>
            <p style='font-size:14px;color:#555;line-height:1.6;margin-top:10px;'><strong>Due Date:</strong> " . date('M d, Y', strtotime($invoice['due_date'])) . "</p>
            " . (!empty($viewUrl) ? "<p style='margin-top:20px;'><a href='{$viewUrl}/public/pay.php?invoice=" . urlencode($invoice['invoice_id']) . "' style='display:inline-block;padding:12px 24px;background:#2271b1;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;'>View Full Invoice</a></p>" : "") . "
            <p style='font-size:13px;color:#999;margin-top:30px;border-top:1px solid #eee;padding-top:15px;'>This is an automated email. Please do not reply directly.</p>
        </div>
    </div>";
}

// --- CHECK AND SEND OVERDUE REMINDERS ---
function check_and_send_reminders() {
    global $db, $site_settings, $reminder_days;

    $db = get_db_connection();
    $invoicesTable = table_name('invoices');
    $clientsTable = table_name('clients');
    $today = date('Y-m-d');

    // Find invoices that are past due and not yet paid
    $stmt = $db->prepare("SELECT * FROM $invoicesTable WHERE status = 'SENT' AND due_date < :today");
    $stmt->execute([':today' => $today]);
    $overdueInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sentToday = [];

    foreach ($overdueInvoices as $inv) {
        $daysOverdue = (strtotime($today) - strtotime($inv['due_date'])) / 86400;

        // Check if this invoice should get a reminder today
        $shouldRemind = false;
        foreach ($reminder_days as $day) {
            if ((int)round($daysOverdue) === $day) {      
                $shouldRemind = true;
                break;
            }
        }

        if (!$shouldRemind) continue;

        // Avoid sending more than one reminder per invoice per day
        $reminderKey = $inv['invoice_id'] . '_' . $today;
        if (in_array($reminderKey, $sentToday)) continue;

        // Get client
        $clientStmt = $db->prepare("SELECT * FROM $clientsTable WHERE id = :id");
        $clientStmt->execute([':id' => $inv['client_id']]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if (!$client || empty($client['email'])) continue;

        // Update status to OVERDUE if not already
        if ($inv['status'] !== 'OVERDUE') {
            $db->prepare("UPDATE $invoicesTable SET status = 'OVERDUE' WHERE id = :id")->execute([':id' => $inv['id']]);
        }

        // Send reminder email
        $subject = "Payment Reminder: Invoice {$inv['invoice_id']} is " . (int)$daysOverdue . " days overdue";
        $body = build_email_body($inv, $client, $site_settings);

        if (send_email($client['email'], $client['name'], $subject, $body)) {
            $sentToday[] = $reminderKey;
        }
    }

    return count($sentToday);
}

// --- MARK INVOICE AS PAID ---
function mark_invoice_paid($invoice_id) {
    global $db;
    if (!isset($db)) $db = get_db_connection();
    $invoicesTable = table_name('invoices');
    $db->prepare("UPDATE $invoicesTable SET status = 'PAID', paid_at = :paid_at WHERE invoice_id = :inv_id")
       ->execute([':paid_at' => date('Y-m-d H:i:s'), ':inv_id' => $invoice_id]);
}   