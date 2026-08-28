<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/functions.php');   

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$db = get_db_connection();
$clientsTable = table_name('clients');
$invoicesTable = table_name('invoices');
$itemsTable = table_name('invoice_items');

$errorMessage = '';
$successMessage = '';

// --- MARK AS PAID ---
if (isset($_GET['mark_paid']) && $_GET['mark_paid'] == 1 && isset($_GET['id'])) {
    $invId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT invoice_id FROM $invoicesTable WHERE id = :id");
    $stmt->execute([':id' => $invId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        mark_invoice_paid($row['invoice_id']);
        $successMessage = 'Invoice marked as paid.';
    }
    header('Location: invoices.php');
    exit;
}

// --- SEND DRAFT INVOICE TO CLIENT ---
if (isset($_GET['send']) && isset($_GET['id'])) {
    $invId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM $invoicesTable WHERE id = :id");
    $stmt->execute([':id' => $invId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice && $invoice['status'] === 'DRAFT') {
        $clientStmt = $db->prepare("SELECT * FROM $clientsTable WHERE id = :id");
        $clientStmt->execute([':id' => $invoice['client_id']]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

        if ($client && !empty($client['email'])) {
            $emailBody = build_email_body($invoice, $client, $site_settings);
            $subject = "Invoice {$invoice['invoice_id']} - Total: {$currency_symbol} " . number_format($invoice['total'], 2);

            send_email($client['email'], $client['name'], $subject, $emailBody);

            $db->prepare("UPDATE $invoicesTable SET status = 'SENT' WHERE id = :id")->execute([':id' => $invId]);
            header('Location: invoices.php?sent=1');
            exit;
        }
    }
    header('Location: invoices.php?error=1');
    exit;
}   

// --- CREATE / SAVE INVOICE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_invoice') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
    $notes = trim($_POST['notes'] ?? '');
    $sendEmail = isset($_POST['send_email']);

    // Parse line items
    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_price'] ?? [];

    $items = [];
    $hasValidItem = false;
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        $qty = (float)($quantities[$i] ?? 1);
        $price = (float)($prices[$i] ?? 0);
        if (!empty($desc) && $price > 0) {
            $items[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2)
            ];
            $hasValidItem = true;
        }
    }

    if (empty($clientId)) {
        $errorMessage = 'Please select a client.';
    } elseif (!$hasValidItem) {
        $errorMessage = 'Add at least one line item with a description and price.';
    } else {
        try {
            $totals = calculate_invoice_totals($items, $tax_rate);
            $invoiceId = generate_invoice_id($invoice_prefix);

            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO $invoicesTable (invoice_id, client_id, status, issue_date, due_date, subtotal, tax_amount, total, notes, created_at) VALUES (:inv_id, :client_id, :status, :issue, :due, :subtotal, :tax, :total, :notes, :created)");
            $stmt->execute([
                ':inv_id' => $invoiceId,
                ':client_id' => $clientId,
                ':status' => $sendEmail ? 'SENT' : 'DRAFT',
                ':issue' => $issueDate,
                ':due' => $dueDate,
                ':subtotal' => $totals['subtotal'],
                ':tax' => $totals['tax_amount'],
                ':total' => $totals['total'],
                ':notes' => $notes,
                ':created' => date('Y-m-d H:i:s')
            ]);

            $itemStmt = $db->prepare("INSERT INTO $itemsTable (invoice_id, description, quantity, unit_price, line_total) VALUES (:inv_id, :desc, :qty, :price, :total)");
            foreach ($items as $item) {
                $itemStmt->execute([
                    ':inv_id' => $invoiceId,
                    ':desc' => $item['description'],
                    ':qty' => $item['quantity'],
                    ':price' => $item['unit_price'],
                    ':total' => $item['line_total']
                ]);
            }

            $db->commit();

            // Send email if requested
            if ($sendEmail) {
                $clientStmt = $db->prepare("SELECT * FROM $clientsTable WHERE id = :id");
                $clientStmt->execute([':id' => $clientId]);
                $client = $clientStmt->fetch(PDO::FETCH_ASSOC);

                $invoiceStmt = $db->prepare("SELECT * FROM $invoicesTable WHERE invoice_id = :inv_id");
                $invoiceStmt->execute([':inv_id' => $invoiceId]);
                $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);

                $emailBody = build_email_body($invoice, $client, $site_settings);
                $subject = "Invoice {$invoiceId} - Total: {$currency_symbol} " . number_format($totals['total'], 2);

                send_email($client['email'], $client['name'], $subject, $emailBody);
                $successMessage = "Invoice {$invoiceId} created and emailed to {$client['name']}.";
            } else {
                $successMessage = "Invoice {$invoiceId} created as draft.";
            }

            header('Location: invoices.php?success=1');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errorMessage = 'Error: ' . $e->getMessage();
        }
    }
}

// --- FETCH ALL INVOICES ---
$allInvoices = $db->query("SELECT i.*, c.name as client_name, c.email as client_email FROM $invoicesTable i LEFT JOIN $clientsTable c ON i.client_id = c.id ORDER BY i.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH CLIENTS FOR DROPDOWN ---
$allClients = $db->query("SELECT id, name, company FROM $clientsTable ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$currency = $currency_symbol;
$statusColors = ['DRAFT' => '#6c757d', 'SENT' => '#0d6efd', 'PAID' => '#198754', 'OVERDUE' => '#dc3545'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices - Invoice Automation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo">Invoice System</div>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="clients.php">Clients</a></li>
            <li class="active"><a href="invoices.php">Invoices</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Invoices</h1>
        </div>
        
        <?php if (isset($_GET['sent'])): ?>
            <div class="alert alert-success">Invoice sent to client.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">Could not send invoice. Client may not have an email address.</div>
        <?php endif; ?>   
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Invoice created successfully.</div>
        <?php endif; ?>

        <!-- CREATE INVOICE FORM -->
        <div class="form-card">
            <h2>Create New Invoice</h2>
            <form method="POST" class="data-form">
                <input type="hidden" name="action" value="create_invoice">
                <div class="form-row">
                    <div class="form-group">
                        <label>Client *</label>
                        <select name="client_id" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($allClients as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ($c['company'] ? ' (' . $c['company'] . ')' : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issue_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                    </div>
                </div>

                <h3 style="font-size:14px; margin:15px 0 10px; color:#555;">Line Items</h3>
                <div id="items-container">
                    <div class="item-row" data-index="0">
                        <input type="text" name="item_description[]" placeholder="Description" class="item-desc">
                        <input type="number" name="item_quantity[]" value="1" min="0" step="0.01" class="item-qty">
                        <input type="number" name="item_price[]" value="0" min="0" step="0.01" class="item-price" placeholder="0.00">
                        <button type="button" class="btn btn-sm btn-remove" onclick="removeItemRow(this)" style="background:#dc3545;color:#fff;">&times;</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm" style="background:#6c757d;color:#fff;margin:10px 0;" onclick="addItemRow()">+ Add Line Item</button>

                <div class="form-group" style="margin-top:15px;">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Payment terms, thank you message, etc."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn" style="background:#2271b1;color:#fff;">Create Invoice (Draft)</button>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#555;margin-left:10px;">
                        <input type="checkbox" name="send_email"> Send email to client immediately
                    </label>
                </div>
            </form>
        </div>

        <!-- INVOICES TABLE -->
        <div class="table-card">
            <h2>All Invoices (<?php echo count($allInvoices); ?>)</h2>
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
                    <?php if (empty($allInvoices)): ?>
                        <tr><td colspan="7" class="empty-state">No invoices yet. Create one above.</td></tr>
                    <?php else: foreach ($allInvoices as $inv): ?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars($inv['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['issue_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                            <td><strong><?php echo $currency; ?> <?php echo number_format($inv['total'], 2); ?></strong></td>
                            <td><span class="badge" style="background:<?php echo $statusColors[$inv['status']] ?? '#6c757d'; ?>"><?php echo $inv['status']; ?></span></td>
                            <td>
                                <?php if ($inv['status'] === 'DRAFT'): ?>
                                <a href="invoices.php?send=1&id=<?php echo $inv['id']; ?>" class="btn btn-sm" style="background:#fd7e14;color:#fff;" onclick="return confirm('Send this invoice to the client now?')">Send</a>
                                <?php endif; ?> 

                                <a href="../public/view.php?invoice=<?php echo urlencode($inv['invoice_id']); ?>" target="_blank" class="btn btn-sm" style="background:#0d6efd;color:#fff;">View</a>
                                <?php if ($inv['status'] !== 'PAID'): ?>
                                <a href="invoices.php?id=<?php echo $inv['id']; ?>&mark_paid=1" class="btn btn-sm" style="background:#198754;color:#fff;" onclick="return confirm('Mark as paid?')">Paid</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<style>
.form-card { background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:20px; margin-bottom:25px; }
.form-card h2 { font-size:16px; margin-bottom:15px; color:#1e293b; }
.data-form .form-row { display:flex; gap:15px; margin-bottom:15px; }
.data-form .form-group { flex:1; margin-bottom:10px; }
.data-form .form-group label { display:block; font-size:13px; font-weight:bold; color:#555; margin-bottom:5px; }
.data-form .form-group input, .data-form .form-group textarea, .data-form .form-group select { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box; }
.form-actions { display:flex; gap:10px; margin-top:15px; align-items:center; }
.alert-error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
.item-row { display:flex; gap:10px; margin-bottom:8px; align-items:center; }
.item-desc { flex:3; }
.item-qty { flex:1; max-width:80px; }
.item-price { flex:1; max-width:120px; }
.item-row input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; }
</style>

<script>
let itemIndex = 1;
function addItemRow() {
    const container = document.getElementById('items-container');
    const row = document.createElement('div');
    row.className = 'item-row';
    row.dataset.index = itemIndex;
    row.innerHTML = `
        <input type="text" name="item_description[]" placeholder="Description" class="item-desc">
        <input type="number" name="item_quantity[]" value="1" min="0" step="0.01" class="item-qty">
        <input type="number" name="item_price[]" value="0" min="0" step="0.01" class="item-price" placeholder="0.00">
        <button type="button" class="btn btn-sm btn-remove" onclick="removeItemRow(this)" style="background:#dc3545;color:#fff;">&times;</button>
    `;
    container.appendChild(row);
    itemIndex++;
}
function removeItemRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.closest('.item-row').remove();
    } else {
        alert('At least one line item is required.');
    }
}
</script>
</body>
</html>   