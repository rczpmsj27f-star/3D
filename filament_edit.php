<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: filaments.php');
    exit;
}

/* Load current filament version */
$stmt = $db->prepare("
    SELECT fv.*, f.id AS filament_id
    FROM current_filament cf
    JOIN filament_version fv ON fv.id = cf.version_id
    JOIN filament f ON f.id = cf.filament_id
    WHERE cf.filament_id = :id
");
$stmt->execute(['id' => $id]);
$filament = $stmt->fetch();

if (!$filament) {
    header('Location: filaments.php');
    exit;
}

/* Save new version */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_filament'])) {

    $data = [
        'brand'                 => $_POST['brand'] ?? $filament['brand'],
        'colour'                => $_POST['colour'] ?? $filament['colour'],
        'type'                  => $_POST['type'] ?? $filament['type'],
        'cost_per_spool'        => (float)($_POST['cost_per_spool'] ?? $filament['cost_per_spool']),
        'spool_weight_g'        => (int)($_POST['spool_weight_g'] ?? $filament['spool_weight_g']),
        'approx_length_m'       => (float)($_POST['approx_length_m'] ?? $filament['approx_length_m']),
        'current_weight_g'      => (int)($_POST['current_weight_g'] ?? $filament['current_weight_g']),
        'low_stock_threshold_g' => (int)($_POST['low_stock_threshold_g'] ?? $filament['low_stock_threshold_g']),
    ];

    updateFilamentVersion($db, $id, $data, 'edit');

    header('Location: filaments.php');
    exit;
}

include __DIR__ . '/header.php';
?>

<h1>Edit filament</h1>

<div class="card">
    <form method="post">
        <input type="hidden" name="save_filament" value="1">

        <div class="form-row">
            <label>Brand</label>
            <input name="brand" value="<?= htmlspecialchars($filament['brand']) ?>">
        </div>

        <div class="form-row">
            <label>Colour</label>
            <input name="colour" value="<?= htmlspecialchars($filament['colour']) ?>">
        </div>

        <div class="form-row">
            <label>Type</label>
            <select name="type">
                <option value="PLA" <?= $filament['type'] === 'PLA' ? 'selected' : '' ?>>PLA</option>
                <option value="PETG" <?= $filament['type'] === 'PETG' ? 'selected' : '' ?>>PETG</option>
                <option value="ABS" <?= $filament['type'] === 'ABS' ? 'selected' : '' ?>>ABS</option>
            </select>
        </div>

        <div class="form-row">
            <label>Cost per spool (£)</label>
            <input type="number" step="0.01" name="cost_per_spool"
                   value="<?= number_format($filament['cost_per_spool'], 2) ?>">
        </div>

        <div class="form-row">
            <label>Spool weight (g)</label>
            <input type="number" name="spool_weight_g"
                   value="<?= (int)$filament['spool_weight_g'] ?>">
        </div>

        <div class="form-row">
            <label>Approx length (m)</label>
            <input type="number" step="0.1" name="approx_length_m"
                   value="<?= number_format($filament['approx_length_m'], 1) ?>">
        </div>

        <div class="form-row">
            <label>Current weight (g)</label>
            <input type="number" name="current_weight_g"
                   value="<?= (int)$filament['current_weight_g'] ?>">
        </div>

        <div class="form-row">
            <label>Low stock threshold (g)</label>
            <input type="number" name="low_stock_threshold_g"
                   value="<?= (int)$filament['low_stock_threshold_g'] ?>">
        </div>

        <button class="btn" type="submit">Save changes</button>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
