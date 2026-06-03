<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['save_service'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = [
            'service_name'   => trim($_POST['service_name']),
            'base_price'     => (float)$_POST['base_price'],
            'max_weight_g'   => $_POST['max_weight_g'] !== '' ? (int)$_POST['max_weight_g'] : null,
            'active'         => isset($_POST['active']) ? 1 : 0,
            'is_royal_mail'  => isset($_POST['is_royal_mail']) ? 1 : 0,
        ];

        if ($id > 0) {
            $stmt = $db->prepare("
                UPDATE postage_services
                SET service_name   = :service_name,
                    base_price     = :base_price,
                    max_weight_g   = :max_weight_g,
                    active         = :active,
                    is_royal_mail  = :is_royal_mail
                WHERE id = :id
            ");
            $data['id'] = $id;
            $stmt->execute($data);
        } else {
            $stmt = $db->prepare("
                INSERT INTO postage_services
                (service_name, base_price, max_weight_g, active, is_royal_mail)
                VALUES (:service_name, :base_price, :max_weight_g, :active, :is_royal_mail)
            ");
            $stmt->execute($data);
        }

        header('Location: postage_services.php');
        exit;
    }

    if (isset($_POST['delete_service'])) {
        $id = (int)$_POST['delete_service'];
        $stmt = $db->prepare("DELETE FROM postage_services WHERE id = :id");
        $stmt->execute(['id' => $id]);
        header('Location: postage_services.php');
        exit;
    }
}

$services = $db->query("
    SELECT *
    FROM postage_services
    ORDER BY is_royal_mail DESC, active DESC, service_name ASC
")->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Postage services</h1>

<div class="card">
    <h2>Add / edit service</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="id" id="svc-id">
        <div class="form-row">
            <label>Name</label>
            <input name="service_name" id="svc-name" required>
        </div>
        <div class="form-row">
            <label>Base price (£)</label>
            <input type="number" step="0.01" name="base_price" id="svc-price" required>
        </div>
        <div class="form-row">
            <label>Max weight (g) (blank = no limit)</label>
            <input type="number" name="max_weight_g" id="svc-weight">
        </div>
        <div class="form-row">
            <label><input type="checkbox" name="active" id="svc-active" checked> Active</label>
        </div>
        <div class="form-row">
            <label><input type="checkbox" name="is_royal_mail" id="svc-rm"> Royal Mail</label>
        </div>
        <button class="btn" type="submit" name="save_service" value="1">Save service</button>
    </form>
</div>

<div class="card">
    <h2>Existing services</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Price</th>
            <th>Max weight (g)</th>
            <th>Royal Mail</th>
            <th>Active</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($services as $s): ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= htmlspecialchars($s['service_name']) ?></td>
                <td>£<?= number_format($s['base_price'], 2) ?></td>
                <td><?= $s['max_weight_g'] !== null ? (int)$s['max_weight_g'] : '—' ?></td>
                <td><?= $s['is_royal_mail'] ? 'Yes' : 'No' ?></td>
                <td><?= $s['active'] ? 'Yes' : 'No' ?></td>
                <td>
                    <button class="btn btn-secondary" type="button"
                        onclick="editService(<?= (int)$s['id'] ?>,
                                             '<?= htmlspecialchars($s['service_name'], ENT_QUOTES) ?>',
                                             '<?= $s['base_price'] ?>',
                                             '<?= $s['max_weight_g'] ?>',
                                             <?= (int)$s['active'] ?>,
                                             <?= (int)$s['is_royal_mail'] ?>)">
                        Edit
                    </button>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <button class="btn btn-danger" type="submit" name="delete_service"
                                value="<?= (int)$s['id'] ?>"
                                onclick="return confirm('Delete this service?');">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<script>
function editService(id, name, price, weight, active, rm) {
    document.getElementById('svc-id').value = id;
    document.getElementById('svc-name').value = name;
    document.getElementById('svc-price').value = price;
    document.getElementById('svc-weight').value = weight && weight !== 'null' ? weight : '';
    document.getElementById('svc-active').checked = active == 1;
    document.getElementById('svc-rm').checked = rm == 1;
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
