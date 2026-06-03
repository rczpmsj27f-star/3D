<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('APP_NAME') ? htmlspecialchars(APP_NAME) : '3D Print Manager' ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            color: #111827;
            font-size: 0.95rem;
        }

        nav {
            background: #111827;
            color: white;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
        }

        nav .brand {
            font-weight: 700;
            font-size: 1rem;
            color: white;
            text-decoration: none;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

        nav ul li a {
            color: #d1d5db;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.875rem;
            transition: background 0.15s;
        }

        nav ul li a:hover { background: #374151; color: white; }

        nav .logout {
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.8rem;
            padding: 4px 10px;
            border: 1px solid #374151;
            border-radius: 4px;
        }

        nav .logout:hover { color: white; border-color: #6b7280; }

        main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        h1 { font-size: 1.5rem; margin: 0 0 1.25rem; }
        h2 { font-size: 1.1rem; margin: 0 0 1rem; }

        .card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        th, td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        th { font-weight: 600; background: #f9fafb; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fafb; }

        .btn {
            display: inline-block;
            padding: 7px 14px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn:hover { background: #1e40af; }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover { background: #4b5563; }

        .btn-danger {
            background: #dc2626;
        }

        .btn-danger:hover { background: #b91c1c; }

        .btn-sm {
            padding: 4px 10px;
            font-size: 0.8rem;
        }

        .form-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 1rem;
            max-width: 480px;
        }

        .form-row label {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: #374151;
        }

        .form-row input,
        .form-row select,
        .form-row textarea {
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
            font-family: inherit;
        }

        .form-row textarea { min-height: 80px; resize: vertical; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
        }

        .summary-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 16px;
        }

        .summary-label { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .summary-value { font-size: 1.25rem; font-weight: 700; margin-top: 4px; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }

        .text-muted { color: #6b7280; font-size: 0.85rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 1rem; }

        .warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 4px;
            padding: 10px 14px;
            color: #92400e;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .low-stock td { background: #fff7ed; }
    </style>
</head>
<body>
<nav>
    <a class="brand" href="index.php">3D Print Manager</a>
    <ul>
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="filaments.php">Filaments</a></li>
        <li><a href="models.php">Models</a></li>
        <li><a href="cost_variables.php">Cost variables</a></li>
        <li><a href="orders.php">Orders</a></li>
    </ul>
    <a class="logout" href="logout.php">Log out</a>
</nav>
<main>
