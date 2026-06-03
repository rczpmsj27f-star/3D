<?php
// header.php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <style>
        :root {
            --bg: #f5f5f7;
            --card-bg: #ffffff;
            --border: #ddd;
            --text: #222;
            --muted: #666;
            --primary: #0069d9;
            --primary-dark: #0053ad;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #28a745;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        header {
            background: #111827;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header .brand {
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        nav a {
            color: #e5e7eb;
            margin-left: 15px;
            text-decoration: none;
            font-size: 0.95rem;
        }
        nav a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        main {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2, h3 {
            margin-top: 0;
        }
        .card {
            background: var(--card-bg);
            border-radius: 6px;
            border: 1px solid var(--border);
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: var(--card-bg);
        }
        th, td {
            border: 1px solid var(--border);
            padding: 8px 10px;
            font-size: 0.9rem;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background: var(--primary);
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: var(--primary-dark);
        }
        .btn-danger {
            background: var(--danger);
        }
        .btn-danger:hover {
            background: #b21f2d;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .low-stock {
            background: #fff7e6;
        }
        .form-row {
            margin-bottom: 10px;
            max-width: 450px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 3px;
            font-size: 0.9rem;
        }
        input, select, textarea {
            width: 100%;
            padding: 6px 8px;
            font-size: 0.9rem;
            border-radius: 4px;
            border: 1px solid var(--border);
        }
        textarea {
            min-height: 70px;
            resize: vertical;
        }
        .warning {
            color: var(--danger);
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75rem;
        }
        .badge-warning { background: var(--warning); }
        .badge-success { background: var(--success); color: #fff; }
        .badge-muted { background: #e5e7eb; color: #374151; }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }
        .summary-item {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        .summary-label {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .summary-value {
            font-weight: 600;
            margin-top: 3px;
        }
        .text-right { text-align: right; }
        .text-muted { color: var(--muted); }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .flex { display: flex; align-items: center; }
        .flex-between { justify-content: space-between; }
        .chip {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 0.75rem;
        }
        @media (max-width: 768px) {
            main { padding: 10px; }
            header { flex-direction: column; align-items: flex-start; }
            nav { margin-top: 8px; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand"><?= htmlspecialchars(APP_NAME) ?></div>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="filaments.php">Filaments</a>
        <a href="models.php">Models</a>
        <a href="cost_variables.php">Cost variables</a>
        <a href="orders.php">Orders</a>
    </nav>
</header>
<main>
