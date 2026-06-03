<?php
require_once __DIR__ . '/functions.php';
$db = getDb();

/* -----------------------------------------------------------
   ADD NEW COST VARIABLE
   ----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_variable'])) {

    $name = trim($_POST['new_name']);
    $value = (float)$_POST['new_value'];
    $type = $_POST['new_type'];

    if ($name !== '') {

        // Create empty cost_variable row
        $stmt = $db->prepare("
            INSERT INTO cost_variable (name, created_at)
            VALUES (:name, NOW())
        ");
        $stmt->execute(['name' => $name]);

        $varId = (int)$db->lastInsertId();

        // Create first version row
        $stmt = $db->prepare("
            INSERT INTO cost_variable_version
                (cost_variable_id, type, value, is_active, valid_from)
            VALUES
                (:id, :type, :value, 1, NOW())
        ");
        $stmt->execute([
            'id'    => $varId,
            'type'  => $type,
            'value' => $value,
        ]);

        $versionId = (int)$db->lastInsertId();

        // Link cost_variable → version
        $stmt = $db->prepare("
            UPDATE cost_variable
            SET current_version_id = :vid
            WHERE id = :id
        ");
        $stmt->execute([
            'vid' => $versionId,
            'id'  => $varId,
        ]);
    }

    header('Location: cost_variables.php');
    exit;
}

/* -----------------------------------------------------------
   SAVE UPDATED COST VARIABLES (CREATE NEW VERSION)
   ----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    foreach ($_POST['vars'] as $varId => $newValue) {

        // Fetch cost variable
        $stmt = $db->prepare("
            SELECT *
            FROM cost_variable
            WHERE id = :id
        ");
        $stmt->execute(['id' => $varId]);
        $cv = $stmt->fetch();

        if (!$cv) continue;

        // Fetch current version to preserve type
        $stmt = $db->prepare("
            SELECT type
            FROM cost_variable_version
            WHERE id = :vid
        ");
        $stmt->execute(['vid' => $cv['current_version_id']]);
        $currentVersion = $stmt->fetch();

        $type = $currentVersion ? $currentVersion['type'] : 'other';

        // Create new version
        $stmt = $db->prepare("
            INSERT INTO cost_variable_version
                (cost_variable_id, type, value, is_active, valid_from)
            VALUES
                (:id, :type, :value, 1, NOW())
        ");
        $stmt->execute([
            'id'    => $varId,
            'type'  => $type,
            'value' => $newValue,
        ]);

        $newVersionId = (int)$db->lastInsertId();

        // Update pointer
        $stmt = $db->prepare("
            UPDATE cost_variable
            SET current_version_id = :vid
            WHERE id = :id
        ");
        $stmt->execute([
            'vid' => $newVersionId,
            'id'  => $varId,
        ]);
    }

    header('Location: cost_variables.php');
    exit;
}

/* -----------------------------------------------------------
   LOAD CURRENT VARIABLES
   ----------------------------------------------------------- */
$stmt = $db->query("
    SELECT 
        cv.id,
        cv.name,
        cv.current_version_id,
        cvv.value,
        cvv.type
    FROM cost_variable cv
    LEFT JOIN cost_variable_version cvv
        ON cvv.id = cv.current_version_id
    ORDER BY cv.id ASC
");

$vars = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>
<h1>Cost Variables</h1>

<div class="card">
    <h2>Add New Cost Variable</h2>
    <form method="post">
        <input type="hidden" name="add_variable" value="1">

        <div class="form-row">
            <label>Name</label>
            <input name="new_name" required>
        </div>

        <div class="form-row">
            <label>Type</label>
            <select name="new_type">
                <option value="per_kwh">Per kWh</option>
                <option value="per_minute">Per Minute</option>
                <option value="fixed_per_order">Fixed Per Order</option>
                <option value="fixed_per_model">Fixed Per Model</option>
                <option value="percentage_markup">Percentage Markup</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-row">
            <label>Initial Value</label>
            <input type="number" step="0.0001" name="new_value" required>
        </div>

        <button class="btn" type="submit">Add Variable</button>
    </form>
</div>

<div class="card">
    <h2>Existing Variables</h2>
    <form method="post">
        <table>
            <tr>
                <th>Name</th>
                <th>Current Value</th>
            </tr>

            <?php foreach ($vars as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['name']) ?></td>
                    <td>
                        <input type="number"
                               step="0.0001"
                               name="vars[<?= $v['id'] ?>]"
                               value="<?= htmlspecialchars($v['value']) ?>">
                    </td>
                </tr>
            <?php endforeach; ?>

        </table>

        <button class="btn" type="submit" name="save" value="1">Save Changes</button>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
