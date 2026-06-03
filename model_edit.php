<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: models.php'); exit; }

$stmt = $db->prepare("SELECT * FROM current_model WHERE model_id = :id");
$stmt->execute(['id' => $id]);
$current = $stmt->fetch();
if (!$current) { header('Location: models.php'); exit; }

$filaments = getCurrentFilaments($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    /* ------------------------------
       Extract posted values
    ------------------------------ */

    $gcodeFilamentId = !empty($_POST['gcode_filament_id'])
        ? (int)$_POST['gcode_filament_id']
        : (int)$current['gcode_filament_id'];

    $weightG = (int)($_POST['model_weight_g'] ?? $current['model_weight_g']);
    $timeMin = (int)($_POST['estimated_print_time_min'] ?? $current['estimated_print_time_min']);
    $lengthM = (int)($_POST['filament_length_m'] ?? $current['filament_length_m']);

    $rawCost = $_POST['raw_cost'] ?? $current['raw_cost'];
    $sellPrice = $_POST['sell_price'] ?? $current['sell_price'];

    /* ------------------------------
       AUTO‑COST CALCULATION
    ------------------------------ */

    if (isset($_POST['auto_cost']) && $gcodeFilamentId && $weightG > 0 && $timeMin > 0) {

        // Material cost from filament + weight
        $materialCost = estimateMaterialCostFromFilament($db, $gcodeFilamentId, $weightG);

        // Full cost engine (electricity, per-minute, markup, etc.)
        $costs = calculateModelCost($db, $materialCost, $timeMin);

        $rawCost = $costs['base_cost'];
        $sellPrice = $costs['sell_price'];
    }

    /* ------------------------------
       Build version data
    ------------------------------ */

    $data = [
        'name' => $_POST['name'] ?? $current['name'],
        'is_sliced' => isset($_POST['is_sliced']) ? 1 : 0,
        'gcode_filament_id' => $gcodeFilamentId ?: null,
        'model_weight_g' => $weightG,
        'estimated_print_time_min' => $timeMin,
        'filament_length_m' => $lengthM,
        'raw_cost' => (float)$rawCost,
        'sell_price' => (float)$sellPrice,
    ];

    /* ------------------------------
       Save new version
    ------------------------------ */

    updateModelVersion($db, $id, $data, 'admin');

    header('Location: models.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<h1>Edit model #<?= (int)$id ?></h1>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

    <div class="form-row">
        <label>Name</label>
        <input name="name" value="<?= htmlspecialchars($current['name']) ?>">
    </div>

    <div class="form-row">
        <label>Sliced?</label>
        <input type="checkbox" name="is_sliced" value="1" <?= $current['is_sliced'] ? 'checked' : '' ?>>
    </div>

    <div class="form-row">
        <label>GCODE filament</label>
        <select name="gcode_filament_id">
            <option value="">-- none --</option>
            <?php foreach ($filaments as $f): ?>
                <option value="<?= (int)$f['filament_id'] ?>"
                    <?= $current['gcode_filament_id'] == $f['filament_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['brand'] . ' ' . $f['colour'] . ' (' . $f['type'] . ') - ' . $f['current_weight_g'] . 'g') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <label>Model weight (g)</label>
        <input type="number" name="model_weight_g" value="<?= (int)$current['model_weight_g'] ?>">
    </div>

    <div class="form-row">
        <label>Estimated print time (min)</label>
        <input type="number" name="estimated_print_time_min" value="<?= (int)$current['estimated_print_time_min'] ?>">
    </div>

    <div class="form-row">
        <label>Filament length (m)</label>
        <input type="number" name="filament_length_m" value="<?= (int)$current['filament_length_m'] ?>">
    </div>

    <div class="form-row">
        <label>Raw cost (£)</label>
        <input type="number" step="0.01" name="raw_cost" value="<?= htmlspecialchars($current['raw_cost']) ?>">
    </div>

    <div class="form-row">
        <label>Sell price (£)</label>
        <input type="number" step="0.01" name="sell_price" value="<?= htmlspecialchars($current['sell_price']) ?>">
    </div>

    <div class="form-row">
        <label>Auto‑calculate costs from filament + time?</label>
        <input type="checkbox" name="auto_cost" value="1">
    </div>

    <button class="btn" type="submit">Save</button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
