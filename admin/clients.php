<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/../config/config.php');

if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}

$db = get_db_connection();
$clientsTable = table_name('clients');

// --- HANDLE ACTIONS ---
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_client' || $action === 'edit_client') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($name) || empty($email)) {
            $errorMessage = 'Name and email are required.';
        } else {
            try {
                if ($action === 'add_client') {
                    $stmt = $db->prepare("INSERT INTO $clientsTable (name, email, phone, company, address, created_at) VALUES (:name, :email, :phone, :company, :address, :created_at)");
                    $stmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':company' => $company,
                        ':address' => $address,
                        ':created_at' => date('Y-m-d H:i:s')
                    ]);
                    $successMessage = 'Client added successfully.';
                } else {
                    $clientId = (int)($_POST['client_id'] ?? 0);
                    $stmt = $db->prepare("UPDATE $clientsTable SET name = :name, email = :email, phone = :phone, company = :company, address = :address WHERE id = :id");
                    $stmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':company' => $company,
                        ':address' => $address,
                        ':id' => $clientId
                    ]);
                    $successMessage = 'Client updated successfully.';
                }
            } catch (PDOException $e) {
                $errorMessage = 'Database error: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            $db->prepare("DELETE FROM $clientsTable WHERE id = :id")->execute([':id' => $clientId]);
            $successMessage = 'Client deleted.';
        }
    }
}

// --- FETCH ALL CLIENTS ---
$allClients = $db->query("SELECT * FROM $clientsTable ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- EDIT MODE ---
$editingClient = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM $clientsTable WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editingClient = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients - Invoice Automation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="logo">Invoice System</div>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li class="active"><a href="clients.php">Clients</a></li>
            <li><a href="invoices.php">Invoices</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1>Clients</h1>
            <a href="clients.php" class="btn" style="background:#2271b1;color:#fff;">+ Add New Client</a>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($editingClient): ?>
            <div class="form-card">
                <h2>Edit Client</h2>
                <form method="POST" class="data-form">
                    <input type="hidden" name="action" value="edit_client">
                    <input type="hidden" name="client_id" value="<?php echo $editingClient['id']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($editingClient['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($editingClient['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($editingClient['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Company</label>
                            <input type="text" name="company" value="<?php echo htmlspecialchars($editingClient['company']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2"><?php echo htmlspecialchars($editingClient['address']); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn" style="background:#198754;color:#fff;">Save Changes</button>
                        <a href="clients.php" class="btn" style="background:#6c757d;color:#fff;">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="form-card">
                <h2>Add New Client</h2>
                <form method="POST" class="data-form">
                    <input type="hidden" name="action" value="add_client">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" placeholder="e.g., John Smith" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" placeholder="john@company.com" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" placeholder="+1 234 567 890">
                        </div>
                        <div class="form-group">
                            <label>Company</label>
                            <input type="text" name="company" placeholder="Company name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2" placeholder="Street, City, Country"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn" style="background:#2271b1;color:#fff;">Add Client</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <h2>All Clients (<?php echo count($allClients); ?>)</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allClients)): ?>
                        <tr><td colspan="6" class="empty-state">No clients yet. Add your first client above.</td></tr>
                    <?php else: foreach ($allClients as $client): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($client['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                            <td><?php echo htmlspecialchars($client['company']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                            <td>
                                <a href="clients.php?edit=<?php echo $client['id']; ?>" class="btn btn-sm" style="background:#0d6efd;color:#fff;">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this client? Their invoices will remain but lose the client link.');">
                                    <input type="hidden" name="action" value="delete_client">
                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background:#dc3545;color:#fff;">Delete</button>
                                </form>
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
.data-form .form-group input, .data-form .form-group textarea { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box; }
.form-actions { display:flex; gap:10px; margin-top:15px; }
.alert-error { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }
</style>
</body>
</html>   