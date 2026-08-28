<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once(__DIR__ . '/config/config.php');

if (isset($_SESSION['user'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$db = get_db_connection();
$usersTable = table_name('users');
$errorMessage = '';

// --- REGISTER (first user becomes admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $errorMessage = 'All fields are required.';
    } else {
        $check = $db->prepare("SELECT id FROM $usersTable WHERE LOWER(email) = LOWER(:email)");
        $check->execute([':email' => $email]);

        if ($check->fetch()) {
            $errorMessage = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO $usersTable (name, email, password, created_at) VALUES (:name, :email, :password, :created_at)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => $hash,
                ':created_at' => date('Y-m-d H:i:s')
            ]);

            // First user becomes admin
            $count = $db->query("SELECT COUNT(*) FROM $usersTable")->fetchColumn();
            if ($count == 1) {
                $db->prepare("UPDATE $usersTable SET role = 'admin' WHERE id = :id")->execute([':id' => $db->lastInsertId()]);
            }

            // Auto-login
            $stmt = $db->prepare("SELECT * FROM $usersTable WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'customer'
            ];

            header('Location: admin/dashboard.php');
            exit;
        }
    }
}

// --- LOGIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $errorMessage = 'All fields are required.';
    } else {
        $stmt = $db->prepare("SELECT * FROM $usersTable WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'customer'
            ];
            header('Location: admin/dashboard.php');
            exit;
        } else {
            $errorMessage = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Invoice Automation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 400px; width: 100%; padding: 40px; }
        .auth-card h1 { font-size: 22px; color: #1e293b; margin-bottom: 6px; }
        .auth-card .subtitle { font-size: 13px; color: #64748b; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; color: #555; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #2271b1; }
        .btn-submit { width: 100%; padding: 12px; background: #2271b1; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #1a5c99; }
        .btn-register { width: 100%; padding: 12px; background: #198754; color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-register:hover { background: #157347; }
        .error { background: #f8d7da; color: #721c24; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; }
        .divider { text-align: center; margin: 20px 0; color: #94a3b8; font-size: 12px; }
        .note { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
<div class="auth-card">
    <h1>Invoice Automation</h1>
    <p class="subtitle">Sign in to manage your invoices</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="you@company.com">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Your password">
        </div>
        <button type="submit" class="btn-submit">Sign In</button>
    </form>

    <div class="divider">— or —</div>

    <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required placeholder="Your name">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="you@company.com">
        </div>
        <div class="form-group">
            <label>Password (min 8 characters)</label>
            <input type="password" name="password" required minlength="8" placeholder="Create a password">
        </div>
        <button type="submit" class="btn-register">Create Account</button>
    </form>

</div>
</body>
</html>   