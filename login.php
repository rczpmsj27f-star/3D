<?php
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Already logged in
if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login – 3D Print Manager</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            width: 320px;
        }
        h1 { font-size: 1.25rem; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 4px; }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            margin-bottom: 1rem;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        button:hover { background: #1e40af; }
        .error { color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>3D Print Manager</h1>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <label>Username</label>
        <input type="text" name="username" autofocus autocomplete="username">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password">
        <button type="submit">Log in</button>
    </form>
</div>
</body>
</html>
