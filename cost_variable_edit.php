<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: cost_variables.php'); exit; }

$stmt = $db->prepare("SELECT * FROM current_cost_variable WHERE cost_variable_id = :id");
$stmt->execute(['id' => $id]);
$current = $stmt->fetch();
if (!$current) { header('Location: cost_variables.php'); exit; }

$systemVars = [
    'Electricity Cost (per kWh)',
    'Printer Cost (per minute)',
];
$isSystem = in_array($current['name'], $systemVars, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $data = [
        'type' => $_POST['type'] ?? $current['type'],
        'value' => (float)($_POST['value'] ?? $current['value']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($current['name'] === 'Electricity Cost (per kWh)') {
        $data['type'] = 'per_kwh';
    } elseif ($current['name'] === 'Printer Cost (per minute)') {
        $data['type'] = 'per_minute';
    }

    updateCostVariableVersion($db, $id, $data, 'admin');
    header('Location: cost_variables.php');
    exit;
}

include __DIR__ . '/header.php';
?>
<h1>Edit cost variable #<?= (int)$id ?></h1>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <div class="form-row">
        <label>Name</label>
        <input value="<?= htmlspecialchars($current['name']) ?>" disabled>
    </div>
    <div class="form-row">
        <label>Type</label>
        <select name="type" <?= $isSystem ? 'disabled' : '' ?>>
            <?php foreach (['per_kwh','per_minute','fixed_per_order','fixed_per_model','percentage_markup','other'] as $t): ?>
                <option value="<?= $t ?>" <?= $current['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row">
        <label>Value</label>
        <input type="number" step="0.0001" name="value" value="<?= htmlspecialchars($current['value']) ?>">
    </div>
    <div class="form-row">
        <label>Active?</label>
        <input type="checkbox" name="is_active" value="1" <?= $current['is_active'] ? 'checked' : '' ?>>
    </div>
    <button class="btn" type="submit">Save</button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
