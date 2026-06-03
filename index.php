<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

$lowStock = $db->query("SELECT * FROM current_filament_low_stock")->fetchAll();
$totalFilaments = (int)$db->query("SELECT COUNT(*) AS c FROM filament")->fetch()['c'];
$totalModels = (int)$db->query("SELECT COUNT(*) AS c FROM model")->fetch()['c'];
$totalOrders = (int)$db->query("SELECT COUNT(*) AS c FROM orders")->fetch()['c'];
$openOrders = (int)$db->query("SELECT COUNT(*) AS c FROM orders WHERE status NOT IN ('Completed','Dispatched')")->fetch()['c'];

include __DIR__ . '/header.php';
?>
<h1>Dashboard</h1>

<div class="summary-grid mb-3">
    <div class="summary-item">
        <div class="summary-label">Filaments</div>
        <div class="summary-value"><?= $totalFilaments ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Models</div>
        <div class="summary-value"><?= $totalModels ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Orders (total)</div>
        <div class="summary-value"><?= $totalOrders ?></div>
    </div>
    <div class="summary-item">
        <div class="summary-label">Open orders</div>
        <div class="summary-value"><?= $openOrders ?></div>
    </div>
</div>

<div class="card">
    <h2>Low stock filaments</h2>
    <?php if (!$lowStock): ?>
        <p class="text-muted">No low stock filaments.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Brand</th><th>Colour</th><th>Type</th><th>Current (g)</th><th>Threshold (g)</th>
            </tr>
            <?php foreach ($lowStock as $f): ?>
                <tr class="low-stock">
                    <td><?= htmlspecialchars($f['brand']) ?></td>
                    <td><?= htmlspecialchars($f['colour']) ?></td>
                    <td><?= htmlspecialchars($f['type']) ?></td>
                    <td><?= (int)$f['current_weight_g'] ?></td>
                    <td><?= (int)$f['low_stock_threshold_g'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
