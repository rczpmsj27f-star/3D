<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_model'])) {
    $db->prepare("INSERT INTO model (created_at) VALUES (NOW())")->execute();
    $modelId = (int)$db->lastInsertId();

    $gcodeFilamentId = !empty($_POST['gcode_filament_id']) ? (int)$_POST['gcode_filament_id'] : null;
    $weightG = (int)($_POST['model_weight_g'] ?? 0);
    $timeMin = (int)($_POST['estimated_print_time_min'] ?? 0);

    $rawCost = (float)($_POST['raw_cost'] ?? 0);
    $sellPrice = (float)($_POST['sell_price'] ?? 0);

    if ($gcodeFilamentId && $weightG > 0 && $timeMin > 0 && isset($_POST['auto_cost'])) {
        $materialCost = estimateMaterialCostFromFilament($db, $gcodeFilamentId, $weightG);
        $costs = calculateModelCost($db, $materialCost, $timeMin);
        $rawCost = $costs['base_cost'];
        $sellPrice = $costs['sell_price'];
    }

    $data = [
        'name' => $_POST['name'] ?? '',
        'is_sliced' => isset($_POST['is_sliced']) ? 1 : 0,
        'gcode_filament_id' => $gcodeFilamentId,
        'model_weight_g' => $weightG,
        'estimated_print_time_min' => $timeMin,
        'filament_length_m' => (int)($_POST['filament_length_m'] ?? 0),
        'raw_cost' => $rawCost,
        'sell_price' => $sellPrice,
    ];
    updateModelVersion($db, $modelId, $data, 'admin');
    header('Location: models.php');
    exit;
}

$models = getCurrentModels($db);
$filaments = getCurrentFilaments($db);
include __DIR__ . '/header.php';
?>
<h1>Models</h1>

<div class="card">
    <h2 class="mb-2">Current models</h2>
    <table>
        <tr>
            <th>ID</th><th>Name</th><th>Sliced</th><th>Filament</th>
            <th>Weight (g)</th><th>Time (min)</th>
            <th>Raw cost</th><th>Sell price</th><th>Margin</th><th>Actions</th>
        </tr>
        <?php foreach ($models as $m): ?>
            <?php
                $raw = (float)$m['raw_cost'];
                $sell = (float)$m['sell_price'];
                $margin = $sell > 0 ? ($sell - $raw) / $sell * 100.0 : 0;
            ?>
            <tr>
                <td><?= (int)$m['model_id'] ?></td>
                <td><?= htmlspecialchars($m['name']) ?></td>
                <td><?= $m['is_sliced'] ? 'Yes' : 'No' ?></td>
                <td><?= $m['gcode_filament_id'] ? (int)$m['gcode_filament_id'] : '-' ?></td>
                <td><?= (int)$m['model_weight_g'] ?></td>
                <td><?= (int)$m['estimated_print_time_min'] ?></td>
                <td>£<?= number_format($raw, 2) ?></td>
                <td>£<?= number_format($sell, 2) ?></td>
                <td><?= $sell > 0 ? number_format($margin, 1) . '%' : '-' ?></td>
                <td><a class="btn btn-secondary" href="model_edit.php?id=<?= (int)$m['model_id'] ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2 class="mb-2">Add new model</h2>
    <form method="post">
        <input type="hidden" name="create_model" value="1">
        <div class="form-row">
            <label>Name</label>
            <input name="name" required>
        </div>
        <div class="form-row">
            <label>Sliced?</label>
            <input type="checkbox" name="is_sliced" value="1">
        </div>
        <div class="form-row">
            <label>GCODE filament</label>
            <select name="gcode_filament_id">
                <option value="">-- none --</option>
                <?php foreach ($filaments as $f): ?>
                    <option value="<?= (int)$f['filament_id'] ?>">
                        <?= htmlspecialchars($f['brand'] . ' ' . $f['colour'] . ' (' . $f['type'] . ') - ' . $f['current_weight_g'] . 'g') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Model weight (g)</label>
            <input type="number" name="model_weight_g">
        </div>
        <div class="form-row">
            <label>Estimated print time (min)</label>
            <input type="number" name="estimated_print_time_min">
        </div>
        <div class="form-row">
            <label>Filament length (m)</label>
            <input type="number" name="filament_length_m">
        </div>
        <div class="form-row">
            <label>Raw cost (£)</label>
            <input type="number" step="0.01" name="raw_cost">
        </div>
        <div class="form-row">
            <label>Sell price (£)</label>
            <input type="number" step="0.01" name="sell_price">
        </div>
        <div class="form-row">
            <label>Auto-calculate costs from filament + time?</label>
            <input type="checkbox" name="auto_cost" value="1" checked>
        </div>
        <button class="btn" type="submit">Create model</button>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
