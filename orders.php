<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    // Create a blank order
    $stmt = $db->prepare("
        INSERT INTO orders (customer_name, status, payment_status, created_at)
        VALUES ('', 'Received', 'Unpaid', NOW())
    ");
    $stmt->execute();

    $newId = (int)$db->lastInsertId();

    header("Location: order_edit.php?id=" . $newId);
    exit;
}
?>


<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

$stmt = $db->query("
    SELECT o.*,
           ps.service_name AS postage_name
    FROM orders o
    LEFT JOIN postage_services ps ON ps.id = o.postage_id
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Orders</h1>

<p>
    <form method="post" style="display:inline;">
        <button class="btn" name="create_order" value="1">Add Order</button>
    </form>
</p>


<div class="card">
    <table>
        <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Payment</th>
            <th>P&P</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= (int)$o['id'] ?></td>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td><?= htmlspecialchars($o['payment_status']) ?></td>
                <td>
                    <?php if (!empty($o['postage_name'])): ?>
                        <?= htmlspecialchars($o['postage_name']) ?>
                        <?php if (isset($o['postage_cost'])): ?>
                            (£<?= number_format((float)$o['postage_cost'], 2) ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td>
                    <a class="btn btn-secondary" href="order_edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php include __DIR__ . '/footer.php'; ?>
