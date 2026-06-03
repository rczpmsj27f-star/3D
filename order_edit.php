<?php
require_once __DIR__ . '/functions.php';

$db = getDb();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: orders.php');
    exit;
}

/* Load order */
$stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();
if (!$order) {
    header('Location: orders.php');
    exit;
}

/* Default status/payment if empty */
if (empty($order['status'])) {
    $order['status'] = 'Received';
}
if (empty($order['payment_status'])) {
    $order['payment_status'] = 'Unpaid';
}

/* Lookups */
$models          = getCurrentModels();
$filaments       = getCurrentFilaments();
$statuses        = getOrderStatuses();
$paymentStatuses = getPaymentStatuses();
$warnings        = [];

/* Cost variables */
$allCostVars = getCostVariables($db);

$selectedCostVarNames = [];
if (!empty($order['cost_variables_json'])) {
    $decoded = json_decode($order['cost_variables_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $name => $flag) {
            if ($flag) {
                $selectedCostVarNames[] = $name;
            }
        }
    }
}

/* Delete entire order */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = :id");
    $stmt->execute(['id' => $id]);

    $stmt = $db->prepare("DELETE FROM orders WHERE id = :id");
    $stmt->execute(['id' => $id]);

    header('Location: orders.php');
    exit;
}

/* Save variable cost selection */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cost_variables'])) {
    $posted = $_POST['cost_vars'] ?? [];
    $flags  = [];

    foreach ($allCostVars as $cv) {
        $name         = $cv['name'];
        $flags[$name] = isset($posted[$name]) ? true : false;
    }

    $stmt = $db->prepare("
        UPDATE orders
        SET cost_variables_json = :json,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'json' => json_encode($flags),
        'id'   => $id,
    ]);

    header('Location: order_edit.php?id=' . $id);
    exit;
}

/**
 * Simple unit price calculation:
 * - If user supplies unit_price, use it.
 * - Otherwise default to 0.00 (you can extend this later if you want auto-pricing).
 */
function calculateItemUnitPriceFromPost(?float $postedUnitPrice): float
{
    if ($postedUnitPrice !== null && $postedUnitPrice >= 0) {
        return $postedUnitPrice;
    }
    return 0.0;
}

/* Add item */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $isCustom = isset($_POST['is_custom']) ? 1 : 0;

    $modelVersionId = null;
    $modelData      = null;

    if (!$isCustom && !empty($_POST['model_id'])) {
        $stmt = $db->prepare("
            SELECT cm.version_id, mv.*
            FROM current_model cm
            JOIN model_version mv ON mv.id = cm.version_id
            WHERE cm.model_id = :mid
        ");
        $stmt->execute(['mid' => (int)$_POST['model_id']]);
        $modelData = $stmt->fetch();
        if ($modelData) {
            $modelVersionId = (int)$modelData['id'];
        }
    }

    $filamentVersionId = null;
    $filamentData      = null;

    if (!empty($_POST['filament_id'])) {
        $stmt = $db->prepare("
            SELECT cf.version_id, fv.*, cf.filament_id
            FROM current_filament cf
            JOIN filament_version fv ON fv.id = cf.version_id
            WHERE cf.filament_id = :fid
        ");
        $stmt->execute(['fid' => (int)$_POST['filament_id']]);
        $filamentData = $stmt->fetch();
        if ($filamentData) {
            $filamentVersionId = (int)$filamentData['id'];
        }
    }

    $qty     = max(1, (int)($_POST['quantity'] ?? 1));
    $estUse  = (int)($_POST['estimated_filament_use_g'] ?? 0);
    $timeMin = (int)($_POST['estimated_print_time_min'] ?? 0);

    $colourOverride = $_POST['colour_override'] ?? null;
    $typeOverride   = $_POST['filament_type_override'] ?? null;
    $customDesc     = $_POST['custom_description'] ?? null;

    $postedUnitPrice = isset($_POST['unit_price']) && $_POST['unit_price'] !== ''
        ? (float)$_POST['unit_price']
        : null;

    $unitPrice = calculateItemUnitPriceFromPost($postedUnitPrice);
    $lineTotal = $unitPrice * $qty;

    $stmt = $db->prepare("
        INSERT INTO order_items
            (order_id, is_custom, model_version_id, filament_version_id,
             quantity, estimated_filament_use_g, estimated_print_time_min,
             colour_override, filament_type_override, custom_description,
             unit_price, line_total)
        VALUES
            (:oid, :is_custom, :mvid, :fvid,
             :qty, :est_g, :time_min,
             :colour_override, :filament_type_override, :custom_description,
             :unit_price, :line_total)
    ");
    $stmt->execute([
        'oid'                    => $id,
        'is_custom'              => $isCustom,
        'mvid'                   => $modelVersionId,
        'fvid'                   => $filamentVersionId,
        'qty'                    => $qty,
        'est_g'                  => $estUse,
        'time_min'               => $timeMin,
        'colour_override'        => $colourOverride,
        'filament_type_override' => $typeOverride,
        'custom_description'     => $customDesc,
        'unit_price'             => $unitPrice,
        'line_total'             => $lineTotal,
    ]);

    header('Location: order_edit.php?id=' . $id);
    exit;
}

/* Update item */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $itemId = (int)$_POST['item_id'];

    $stmt = $db->prepare("SELECT * FROM order_items WHERE id = :id AND order_id = :oid");
    $stmt->execute(['id' => $itemId, 'oid' => $id]);
    $item = $stmt->fetch();

    if ($item) {
        $qty     = max(1, (int)($_POST['quantity'] ?? $item['quantity']));
        $estUse  = (int)($_POST['estimated_filament_use_g'] ?? $item['estimated_filament_use_g']);
        $timeMin = (int)($_POST['estimated_print_time_min'] ?? $item['estimated_print_time_min']);

        $colourOverride = $_POST['colour_override'] ?? $item['colour_override'];
        $typeOverride   = $_POST['filament_type_override'] ?? $item['filament_type_override'];

        $customDescription = $_POST['custom_description'] ?? $item['custom_description'];

        $postedUnitPrice = isset($_POST['unit_price']) && $_POST['unit_price'] !== ''
            ? (float)$_POST['unit_price']
            : null;

        if ($postedUnitPrice !== null) {
            $unitPrice = calculateItemUnitPriceFromPost($postedUnitPrice);
        } else {
            $unitPrice = (float)$item['unit_price'];
        }

        $lineTotal = $unitPrice * $qty;

        $stmt = $db->prepare("
            UPDATE order_items
            SET quantity                 = :quantity,
                estimated_filament_use_g = :estimated_filament_use_g,
                estimated_print_time_min = :estimated_print_time_min,
                colour_override          = :colour_override,
                filament_type_override   = :filament_type_override,
                custom_description       = :custom_description,
                unit_price               = :unit_price,
                line_total               = :line_total
            WHERE id = :id AND order_id = :oid
        ");
        $stmt->execute([
            'quantity'                 => $qty,
            'estimated_filament_use_g' => $estUse,
            'estimated_print_time_min' => $timeMin,
            'colour_override'          => $colourOverride,
            'filament_type_override'   => $typeOverride,
            'custom_description'       => $customDescription,
            'unit_price'               => $unitPrice,
            'line_total'               => $lineTotal,
            'id'                       => $itemId,
            'oid'                      => $id,
        ]);
    }

    header('Location: order_edit.php?id=' . $id);
    exit;
}

/* Delete item */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $itemId = (int)$_POST['delete_item'];

    $stmt = $db->prepare("DELETE FROM order_items WHERE id = :id AND order_id = :oid");
    $stmt->execute(['id' => $itemId, 'oid' => $id]);

    header('Location: order_edit.php?id=' . $id);
    exit;
}

/* Update order (status, payment, notes, postage, stock) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $newStatus  = $_POST['status'] ?? $order['status'];
    $newPayment = $_POST['payment_status'] ?? $order['payment_status'];
    $notes      = $_POST['notes'] ?? $order['notes'];

    $postageId   = !empty($_POST['postage_id']) ? (int)$_POST['postage_id'] : null;
    $postageCost = 0.0;

    if ($postageId) {
        $stmt = $db->prepare("SELECT base_price FROM postage_services WHERE id = :id");
        $stmt->execute(['id' => $postageId]);
        $postageCost = (float)$stmt->fetchColumn();
    }

    // Stock update logic – behaviour C:
    // - If insufficient filament: add warning, do NOT mark stock_updated = 1
    // - Still allow save
    if (in_array($newStatus, ['Completed', 'Dispatched'], true) && !$order['stock_updated']) {
        $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = :oid");
        $stmt->execute(['oid' => $id]);
        $orderItems = $stmt->fetchAll();

        $allOk = true;

        foreach ($orderItems as $it) {
            if (empty($it['filament_version_id'])) {
                continue;
            }

            $stmt = $db->prepare("SELECT * FROM filament_version WHERE id = :id");
            $stmt->execute(['id' => $it['filament_version_id']]);
            $fv = $stmt->fetch();
            if (!$fv) {
                continue;
            }

            $qty    = (int)$it['quantity'];
            $grams  = (int)$it['estimated_filament_use_g'];
            $needed = $qty * $grams;

            $currentWeight = (int)$fv['current_weight_g'];

            if ($currentWeight < $needed) {
                $allOk = false;
                $warnings[] = sprintf(
                    'Not enough filament remaining on spool %s %s (%s) for item ID %d (needs %dg, has %dg).',
                    $fv['brand'],
                    $fv['colour'],
                    $fv['type'],
                    (int)$it['id'],
                    $needed,
                    $currentWeight
                );
                // behaviour C: do NOT deduct, do NOT mark stock updated
                continue;
            }

            // Deduct filament
            $stmt = $db->prepare("
                UPDATE filament_version
                SET current_weight_g = current_weight_g - :used
                WHERE id = :id
            ");
            $stmt->execute([
                'used' => $needed,
                'id'   => $fv['id'],
            ]);
        }

        if ($allOk) {
            $order['stock_updated'] = 1;
        } else {
            $order['stock_updated'] = 0;
        }
    }

    $stmt = $db->prepare("
        UPDATE orders
        SET status         = :status,
            payment_status = :payment_status,
            notes          = :notes,
            postage_id     = :postage_id,
            postage_cost   = :postage_cost,
            stock_updated  = :stock_updated,
            updated_at     = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        'status'         => $newStatus,
        'payment_status' => $newPayment,
        'notes'          => $notes,
        'postage_id'     => $postageId,
        'postage_cost'   => $postageCost,
        'stock_updated'  => $order['stock_updated'] ?? 0,
        'id'             => $id,
    ]);

    $order['status']         = $newStatus;
    $order['payment_status'] = $newPayment;
    $order['notes']          = $notes;
    $order['postage_id']     = $postageId;
    $order['postage_cost']   = $postageCost;

    header('Location: order_edit.php?id=' . $id);
    exit;
}

/* Load items */
$stmt = $db->prepare("
    SELECT oi.*,
           mv.name   AS model_name,
           fv.brand  AS filament_brand,
           fv.colour AS filament_colour,
           fv.type   AS filament_type
    FROM order_items oi
    LEFT JOIN model_version    mv ON mv.id = oi.model_version_id
    LEFT JOIN filament_version fv ON fv.id = oi.filament_version_id
    WHERE oi.order_id = :oid
    ORDER BY oi.id ASC
");
$stmt->execute(['oid' => $id]);
$items = $stmt->fetchAll();

/* Variable-cost totals and summary (high-level) */
$varCosts = calculateVariableCostsForOrder($db, $items, $selectedCostVarNames, $allCostVars);

$totalRevenue = 0.0;
foreach ($items as $it) {
    $totalRevenue += (float)$it['line_total'];
}

$totalCost   = $varCosts['total'] ?? 0.0;
$profit      = $totalRevenue - $totalCost;
$margin      = $totalRevenue > 0 ? ($profit / $totalRevenue * 100.0) : 0.0;

$vatAmount   = 0.0; // VAT removed from maths
$postageCost = isset($order['postage_cost']) ? (float)$order['postage_cost'] : 0.0;
$totalIncVat = $totalRevenue + $postageCost;

$postageServices    = getPostageServices($db);
$totalWeightG       = getOrderTotalWeight($db, $id);
$recommendedPostage = recommendPostageService($postageServices, $totalWeightG);

include __DIR__ . '/header.php';
?>

<h1>Order #<?= (int)$order['id'] ?></h1>

<?php foreach ($warnings as $w): ?>
    <p class="warning"><?= htmlspecialchars($w) ?></p>
<?php endforeach; ?>

<div class="card">
    <h2 class="mb-2">Order details</h2>
    <form method="post">
        <input type="hidden" name="update_order" value="1">

        <div class="form-row">
            <label>Customer name</label>
            <input value="<?= htmlspecialchars($order['customer_name']) ?>" disabled>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Payment status</label>
            <select name="payment_status">
                <?php foreach ($paymentStatuses as $ps): ?>
                    <option value="<?= htmlspecialchars($ps) ?>" <?= $order['payment_status'] === $ps ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ps) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>P&P (recommended based on total weight: <?= (int)$totalWeightG ?>g)</label>
            <select name="postage_id">
                <option value="">-- none --</option>
                <?php foreach ($postageServices as $ps): ?>
                    <?php
                        $isRecommended = $recommendedPostage && $recommendedPostage['id'] == $ps['id'];
                    ?>
                    <option value="<?= (int)$ps['id'] ?>"
                        <?= isset($order['postage_id']) && (int)$order['postage_id'] === (int)$ps['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ps['service_name']) ?>
                        (£<?= number_format($ps['base_price'], 2) ?>)
                        <?php if ($ps['max_weight_g'] !== null): ?>
                            — up to <?= (int)$ps['max_weight_g'] ?>g
                        <?php endif; ?>
                        <?= $isRecommended ? ' [Recommended]' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($recommendedPostage): ?>
                <p class="text-muted" style="margin-top:4px;">
                    Recommended: <?= htmlspecialchars($recommendedPostage['service_name']) ?>
                    (£<?= number_format($recommendedPostage['base_price'], 2) ?>)
                    for <?= $totalWeightG ?>g.
                </p>
            <?php else: ?>
                <p class="text-muted" style="margin-top:4px;">
                    No suitable postage service found for <?= $totalWeightG ?>g — check your postage services.
                </p>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
        </div>

        <p>
            Stock updated:
            <?= $order['stock_updated']
                ? '<span class="badge badge-success">Yes</span>'
                : '<span class="badge badge-warning">No</span>' ?>
        </p>

        <button class="btn" type="submit">Save order</button>
        <button class="btn btn-danger" type="submit" name="delete_order" value="1"
                onclick="return confirm('Are you sure you want to delete this entire order?');">
            Delete order
        </button>
    </form>
</div>

<div class="card">
    <h2 class="mb-2">Variable costs</h2>
    <form method="post">
        <input type="hidden" name="update_cost_variables" value="1">

        <table>
            <tr>
                <th>Include</th>
                <th>Name</th>
                <th>Type</th>
                <th>Value</th>
            </tr>
            <?php foreach ($allCostVars as $cv): ?>
                <?php
                    $name    = $cv['name'];
                    $checked = in_array($name, $selectedCostVarNames, true);
                ?>
                <tr>
                    <td>
                        <input type="checkbox"
                               name="cost_vars[<?= htmlspecialchars($name) ?>]"
                               value="1"
                               <?= $checked ? 'checked' : '' ?>>
                    </td>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($cv['type']) ?></td>
                    <td>
                        <?php if ($cv['type'] === 'percentage_markup'): ?>
                            <?= number_format($cv['value'], 2) ?>%
                        <?php else: ?>
                            £<?= number_format($cv['value'], 2) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <button class="btn" type="submit">Save variable costs</button>
    </form>
</div>

<div class="card">
    <h2 class="mb-2">Order summary</h2>

    <?php
        $materialCost    = 0.0;
        $electricityCost = 0.0;
        $timeCost        = 0.0;
        $otherCosts      = 0.0;

        foreach ($items as $it) {
            $qty     = (int)$it['quantity'];
            $grams   = (int)$it['estimated_filament_use_g'];
            $minutes = (int)$it['estimated_print_time_min'];

            // MATERIAL
            if (!empty($it['filament_version_id'])) {
                $stmt = $db->prepare("
                    SELECT cost_per_spool, spool_weight_g, current_weight_g
                    FROM filament_version
                    WHERE id = :vid
                    LIMIT 1
                ");
                $stmt->execute(['vid' => $it['filament_version_id']]);
                $fv = $stmt->fetch();

                if ($fv && $fv['spool_weight_g'] > 0) {
                    $costPerG = ((float)$fv['cost_per_spool']) / (float)$fv['spool_weight_g'];
                    $materialCost += ($costPerG * $grams) * $qty;
                }
            }

            // ELECTRICITY + TIME + OTHER (per-item)
            foreach ($selectedCostVarNames as $name) {
                if (!isset($allCostVars[$name])) {
                    continue;
                }
                $var   = $allCostVars[$name];
                $value = (float)$var['value'];

                switch ($var['type']) {
                    case 'per_kwh':
                        $electricityCost += ($value * $minutes) * $qty;
                        break;

                    case 'per_minute':
                        $timeCost += ($value * $minutes) * $qty;
                        break;

                    case 'fixed_per_model':
                        $otherCosts += ($value * $qty);
                        break;
                }
            }
        }

        // per-order fixed cost (if present in $varCosts)
        $otherCosts += $varCosts['per_order'] ?? 0.0;

        $subtotal      = $varCosts['subtotal']       ?? 0.0;
        $markup        = $varCosts['markup']         ?? 0.0;
        $totalCost     = $varCosts['total']          ?? 0.0;
        $markupPercent = $varCosts['markup_percent'] ?? 0.0;
    ?>

    <div class="summary-grid">

        <div class="summary-item">
            <div class="summary-label">Material cost</div>
            <div class="summary-value">£<?= number_format($materialCost, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Electricity cost</div>
            <div class="summary-value">£<?= number_format($electricityCost, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Time cost</div>
            <div class="summary-value">£<?= number_format($timeCost, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Other variable costs</div>
            <div class="summary-value">£<?= number_format($otherCosts, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Subtotal</div>
            <div class="summary-value">£<?= number_format($subtotal, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Markup (<?= number_format($markupPercent, 2) ?>%)</div>
            <div class="summary-value">£<?= number_format($markup, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Total cost</div>
            <div class="summary-value">£<?= number_format($totalCost, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Revenue (ex VAT)</div>
            <div class="summary-value">£<?= number_format($totalRevenue, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Profit</div>
            <div class="summary-value">£<?= number_format($profit, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Margin</div>
            <div class="summary-value">
                <?= $totalRevenue > 0 ? number_format($margin, 1) . '%' : '-' ?>
            </div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Postage cost</div>
            <div class="summary-value">£<?= number_format($postageCost, 2) ?></div>
        </div>

        <div class="summary-item">
            <div class="summary-label">Final total</div>
            <div class="summary-value">£<?= number_format($totalIncVat, 2) ?></div>
        </div>

    </div>
</div>

<div class="card">
    <h2 class="mb-2">Items</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Custom?</th>
            <th>Description / Model</th>
            <th>Filament</th>
            <th>Qty</th>
            <th>Est. filament (g)</th>
            <th>Est. time (min)</th>
            <th>Unit price</th>
            <th>Total</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($items as $it): ?>
            <?php
                $qty       = (int)$it['quantity'];
                $unitPrice = (float)$it['unit_price'];
                $lineTotal = (float)$it['line_total'];

                $filamentLabel = $it['filament_brand']
                    ? ($it['filament_brand'] . ' ' . $it['filament_colour'] . ' (' . $it['filament_type'] . ')')
                    : '-';

                $desc = $it['custom_description']
                    ?: ($it['is_custom']
                        ? $it['custom_description']
                        : ($it['model_name'] ?: ('Model version ID ' . $it['model_version_id'])));
            ?>
            <tr>
                <td><?= (int)$it['id'] ?></td>
                <td><?= $it['is_custom'] ? 'Yes' : 'No' ?></td>
                <td><?= nl2br(htmlspecialchars($desc)) ?></td>
                <td><?= htmlspecialchars($filamentLabel) ?></td>
                <td><?= $qty ?></td>
                <td><?= (int)$it['estimated_filament_use_g'] ?></td>
                <td><?= (int)$it['estimated_print_time_min'] ?></td>
                <td>£<?= number_format($unitPrice, 2) ?></td>
                <td>£<?= number_format($lineTotal, 2) ?></td>
                <td>
                    <button class="btn btn-secondary" type="button"
                            onclick="toggleEditPanel(<?= (int)$it['id'] ?>)">Edit</button>
                    <form method="post" style="display:inline;">
                        <button class="btn btn-danger" type="submit"
                                name="delete_item" value="<?= (int)$it['id'] ?>"
                                onclick="return confirm('Delete this item?');">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <tr id="edit-panel-<?= (int)$it['id'] ?>" style="display:none;">
                <td colspan="10">
                    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;">
                        <form method="post">
                            <input type="hidden" name="update_item" value="1">
                            <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">

                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Quantity</label>
                                    <input type="number" name="quantity" value="<?= $qty ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Estimated filament (g)</label>
                                    <input type="number" name="estimated_filament_use_g"
                                           value="<?= (int)$it['estimated_filament_use_g'] ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Estimated time (min)</label>
                                    <input type="number" name="estimated_print_time_min"
                                           value="<?= (int)$it['estimated_print_time_min'] ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Colour override</label>
                                    <input name="colour_override"
                                           value="<?= htmlspecialchars($it['colour_override'] ?? '') ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Filament type override</label>
                                    <input name="filament_type_override"
                                           value="<?= htmlspecialchars($it['filament_type_override'] ?? '') ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Description</label>
                                    <input name="custom_description"
                                           value="<?= htmlspecialchars($it['custom_description'] ?? $it['model_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label style="font-size:0.8rem;font-weight:600;">Unit price override (£)</label>
                                    <input type="number" step="0.01" name="unit_price">
                                    <div class="text-muted" style="font-size:0.75rem;margin-top:2px;">
                                        Leave blank to keep current.
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top:10px;display:flex;gap:8px;">
                                <button class="btn btn-secondary" type="submit">Save</button>
                                <button class="btn btn-secondary" type="button"
                                        onclick="toggleEditPanel(<?= (int)$it['id'] ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h2 class="mb-2">Add item</h2>
    <form method="post">
        <input type="hidden" name="add_item" value="1">

        <div class="form-row">
            <label>Custom item?</label>
            <input type="checkbox" name="is_custom" value="1">
        </div>

        <div class="form-row">
            <label>Preset model</label>
            <select name="model_id">
                <option value="">-- none / custom --</option>
                <?php foreach ($models as $m): ?>
                    <option value="<?= (int)$m['model_id'] ?>">
                        <?= htmlspecialchars($m['name']) ?> (ID <?= (int)$m['model_id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Custom description</label>
            <textarea name="custom_description"></textarea>
        </div>

        <div class="form-row">
            <label>Filament</label>
            <select name="filament_id">
                <option value="">-- none --</option>
                <?php foreach ($filaments as $f): ?>
                    <option value="<?= (int)$f['filament_id'] ?>">
                        <?= htmlspecialchars($f['brand'] . ' ' . $f['colour'] . ' (' . $f['type'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Quantity</label>
            <input type="number" name="quantity" value="1">
        </div>

        <div class="form-row">
            <label>Estimated filament (g)</label>
            <input type="number" name="estimated_filament_use_g" value="0">
        </div>

        <div class="form-row">
            <label>Estimated time (min)</label>
            <input type="number" name="estimated_print_time_min" value="0">
        </div>

        <div class="form-row">
            <label>Colour override</label>
            <input name="colour_override">
        </div>

        <div class="form-row">
            <label>Filament type override</label>
            <input name="filament_type_override">
        </div>

        <div class="form-row">
            <label>Unit price (£)</label>
            <input type="number" step="0.01" name="unit_price">
        </div>

        <button class="btn" type="submit">Add item</button>
    </form>
</div>

<script>
function toggleEditPanel(id) {
    var row = document.getElementById('edit-panel-' + id);
    if (!row) return;
    row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
