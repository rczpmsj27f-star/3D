<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

/* Add new filament */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_filament'])) {

    // Create master filament row
    $db->prepare("INSERT INTO filament (created_at) VALUES (NOW())")->execute();
    $filamentId = (int)$db->lastInsertId();

    // Prepare first version
    $data = [
        'brand'                 => $_POST['brand'] ?? '',
        'colour'                => $_POST['colour'] ?? '',
        'type'                  => $_POST['type'] ?? 'PLA',
        'cost_per_spool'        => (float)($_POST['cost_per_spool'] ?? 0),
        'spool_weight_g'        => (int)($_POST['spool_weight_g'] ?? 0),
        'approx_length_m'       => (float)($_POST['approx_length_m'] ?? 0),
        'current_weight_g'      => (int)($_POST['spool_weight_g'] ?? 0),
        'low_stock_threshold_g' => (int)($_POST['low_stock_threshold_g'] ?? 200),
    ];

    updateFilamentVersion($db, $filamentId, $data, 'initial');

    header('Location: filaments.php');
    exit;
}

/* Load current filaments */
$filaments = getCurrentFilaments();

include __DIR__ . '/header.php';
?>

<h1>Filaments</h1>

<div class="card">
    <h2 class="mb-2">Add new filament</h2>
    <form method="post">
        <input type="hidden" name="add_filament" value="1">

        <div class="form-row">
            <label>Brand</label>
            <input name="brand" required>
        </div>

        <div class="form-row">
            <label>Colour</label>
            <input name="colour" required>
        </div>

        <div class="form-row">
            <label>Type</label>
            <select name="type">
                <option value="PLA">PLA</option>
                <option value="PETG">PETG</option>
                <option value="ABS">ABS</option>
            </select>
        </div>

        <div class="form-row">
            <label>Cost per spool (£)</label>
            <input type="number" step="0.01" name="cost_per_spool" required>
        </div>

        <div class="form-row">
            <label>Spool weight (g)</label>
            <input type="number" name="spool_weight_g" required>
        </div>

        <div class="form-row">
            <label>Approx length (m)</label>
            <input type="number" step="0.1" name="approx_length_m">
        </div>

        <div class="form-row">
            <label>Low stock threshold (g)</label>
            <input type="number" name="low_stock_threshold_g" value="200">
        </div>

        <button class="btn" type="submit">Add filament</button>
    </form>
</div>

<div class="card">
    <h2 class="mb-2">Current filaments</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Brand</th>
            <th>Colour</th>
            <th>Type</th>
            <th>Cost/spool</th>
            <th>Spool weight</th>
            <th>Current weight</th>
            <th>Actions</th>
        </tr>

        <?php foreach ($filaments as $f): ?>
            <tr>
                <td><?= (int)$f['filament_id'] ?></td>
                <td><?= htmlspecialchars($f['brand']) ?></td>
                <td><?= htmlspecialchars($f['colour']) ?></td>
                <td><?= htmlspecialchars($f['type']) ?></td>
                <td>£<?= number_format($f['cost_per_spool'], 2) ?></td>
                <td><?= (int)$f['spool_weight_g'] ?>g</td>
                <td><?= (int)$f['current_weight_g'] ?>g</td>
                <td>
                    <a class="btn btn-secondary" href="filament_edit.php?id=<?= (int)$f['filament_id'] ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php include __DIR__ . '/footer.php'; ?>
