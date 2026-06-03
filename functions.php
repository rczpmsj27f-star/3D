<?php
// functions.php
require_once __DIR__ . '/db.php';

/* ----------------- AUTHENTICATION ----------------- */

function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['authenticated'])) {
        header('Location: login.php');
        exit;
    }
}

/* ----------------- CSRF PROTECTION ----------------- */

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

/* ----------------- CURRENT MODELS / FILAMENTS ----------------- */

function getCurrentModels(): array {
    $db = getDb();
    $sql = "
        SELECT m.id AS model_id, mv.*
        FROM model m
        JOIN model_version mv ON mv.id = m.current_version_id
        ORDER BY mv.name ASC
    ";
    return $db->query($sql)->fetchAll();
}

function getCurrentFilaments(): array {
    $db = getDb();
    $sql = "
        SELECT f.id AS filament_id, fv.*
        FROM filament f
        JOIN filament_version fv ON fv.id = f.current_version_id
        ORDER BY fv.brand, fv.colour
    ";
    return $db->query($sql)->fetchAll();
}

/* ----------------- MODEL / FILAMENT LOOKUPS ----------------- */

function getModelById(int $id): ?array {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM model_version
        WHERE model_id = :id
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function getFilamentById(int $id): ?array {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM filament_version
        WHERE filament_id = :id
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

/* ----------------- COST VARIABLES ----------------- */

function getCostVariables(PDO $db): array {
    $sql = "
        SELECT
            cv.id AS cost_variable_id,
            cv.name,
            cvv.value,
            cvv.type
        FROM cost_variable cv
        JOIN cost_variable_version cvv
            ON cvv.id = cv.current_version_id
        ORDER BY cv.name ASC
    ";
    $rows = $db->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[$row['name']] = [
            'id'    => (int)$row['cost_variable_id'],
            'name'  => $row['name'],
            'value' => (float)$row['value'],
            'type'  => $row['type'],
        ];
    }
    return $out;
}

/**
 * Cost engine:
 * - Material:        (cost_per_spool / spool_weight_g) * grams * qty
 * - Electricity:     per_kwh * minutes * qty
 * - Time:            per_minute * minutes * qty
 * - Fixed per model: fixed_per_model * qty
 * - Fixed per order / other: once per order
 * - Markup:          percentage of subtotal
 */
function calculateVariableCostsForOrder(
    PDO $db,
    array $items,
    array $selectedNames,
    array $allVariables
): array {
    $perOrderCosts      = 0.0;
    $perItemCosts       = [];
    $materialTotal      = 0.0;
    $electricityTotal   = 0.0;
    $timeTotal          = 0.0;
    $fixedPerModelTotal = 0.0;

    // Extract markup %
    $markupPercent = 0.0;
    foreach ($selectedNames as $name) {
        if (isset($allVariables[$name]) && $allVariables[$name]['type'] === 'percentage_markup') {
            $markupPercent = (float)$allVariables[$name]['value'];
        }
    }

    // Per-order fixed costs
    foreach ($selectedNames as $name) {
        if (!isset($allVariables[$name])) continue;
        $var = $allVariables[$name];
        if ($var['type'] === 'fixed_per_order' || $var['type'] === 'other') {
            $perOrderCosts += (float)$var['value'];
        }
    }

    // Per-item costs
    foreach ($items as $item) {
        $id      = (int)$item['id'];
        $qty     = (int)$item['quantity'];
        $grams   = (int)$item['estimated_filament_use_g'];
        $minutes = (int)$item['estimated_print_time_min'];
        $cost    = 0.0;

        // MATERIAL COST
        if (!empty($item['filament_version_id'])) {
            $stmt = $db->prepare("
                SELECT cost_per_spool, spool_weight_g
                FROM filament_version
                WHERE id = :vid
                LIMIT 1
            ");
            $stmt->execute(['vid' => $item['filament_version_id']]);
            $fv = $stmt->fetch();
            if ($fv && $fv['spool_weight_g'] > 0) {
                $costPerG    = ((float)$fv['cost_per_spool']) / (float)$fv['spool_weight_g'];
                $itemMat     = $costPerG * $grams * $qty;
                $cost       += $itemMat;
                $materialTotal += $itemMat;
            }
        }

        // ELECTRICITY COST (per_kwh = cost per minute of electricity)
        foreach ($selectedNames as $name) {
            if (!isset($allVariables[$name])) continue;
            $var = $allVariables[$name];
            if ($var['type'] === 'per_kwh') {
                $amt = ((float)$var['value'] * $minutes) * $qty;
                $cost += $amt;
                $electricityTotal += $amt;
            }
        }

        // TIME COST (per_minute)
        foreach ($selectedNames as $name) {
            if (!isset($allVariables[$name])) continue;
            $var = $allVariables[$name];
            if ($var['type'] === 'per_minute') {
                $amt = ((float)$var['value'] * $minutes) * $qty;
                $cost += $amt;
                $timeTotal += $amt;
            }
        }

        // FIXED PER MODEL
        foreach ($selectedNames as $name) {
            if (!isset($allVariables[$name])) continue;
            $var = $allVariables[$name];
            if ($var['type'] === 'fixed_per_model') {
                $amt = ((float)$var['value'] * $qty);
                $cost += $amt;
                $fixedPerModelTotal += $amt;
            }
        }

        $perItemCosts[$id] = $cost;
    }

    $subtotal = array_sum($perItemCosts) + $perOrderCosts;
    $markup   = $subtotal * ($markupPercent / 100.0);
    $total    = $subtotal + $markup;

    return [
        'per_order'      => $perOrderCosts,
        'per_item'       => $perItemCosts,
        'markup'         => $markup,
        'subtotal'       => $subtotal,
        'total'          => $total,
        'markup_percent' => $markupPercent,
        'material'       => $materialTotal,
        'electricity'    => $electricityTotal,
        'time_cost'      => $timeTotal,
        'other'          => $fixedPerModelTotal + $perOrderCosts,
    ];
}

/* ----------------- STATUSES (fixed lists) ----------------- */

function getOrderStatuses(): array {
    return [
        'Received',
        'In progress',
        'On hold',
        'Awaiting stock',
        'Completed',
        'Dispatched',
        'Cancelled'
    ];
}

function getPaymentStatuses(): array {
    return [
        'Unpaid',
        'Part paid',
        'Paid',
        'Refunded'
    ];
}

/* ----------------- POSTAGE ----------------- */

function getPostageServices(PDO $db): array {
    $sql = "
        SELECT id, service_name, base_price, max_weight_g, active, is_royal_mail
        FROM postage_services
        WHERE active = 1
        ORDER BY service_name ASC
    ";
    return $db->query($sql)->fetchAll();
}

function getOrderTotalWeight(PDO $db, int $orderId): int {
    $stmt = $db->prepare("
        SELECT SUM(quantity * estimated_filament_use_g) AS total_weight
        FROM order_items
        WHERE order_id = :oid
    ");
    $stmt->execute(['oid' => $orderId]);
    $row = $stmt->fetch();
    return (int)($row['total_weight'] ?? 0);
}

function recommendPostageService(array $services, int $weightG): ?array {
    foreach ($services as $svc) {
        if ($svc['max_weight_g'] === null || $weightG <= (int)$svc['max_weight_g']) {
            return $svc;
        }
    }
    return null;
}

/* ----------------- FILAMENT VERSION UPDATE ----------------- */

function updateFilamentVersion(PDO $db, int $filamentId, array $data, string $reason = 'manual'): void {
    $stmt = $db->prepare("
        INSERT INTO filament_version
            (filament_id, brand, colour, type, cost_per_spool, spool_weight_g,
             approx_length_m, current_weight_g, low_stock_threshold_g, created_at)
        VALUES
            (:filament_id, :brand, :colour, :type, :cost_per_spool, :spool_weight_g,
             :approx_length_m, :current_weight_g, :low_stock_threshold_g, NOW())
    ");
    $stmt->execute([
        'filament_id'           => $filamentId,
        'brand'                 => $data['brand'],
        'colour'                => $data['colour'],
        'type'                  => $data['type'],
        'cost_per_spool'        => $data['cost_per_spool'],
        'spool_weight_g'        => $data['spool_weight_g'],
        'approx_length_m'       => $data['approx_length_m'],
        'current_weight_g'      => $data['current_weight_g'],
        'low_stock_threshold_g' => $data['low_stock_threshold_g'],
    ]);

    $newVersionId = (int)$db->lastInsertId();

    $stmt = $db->prepare("
        UPDATE filament
        SET current_version_id = :vid
        WHERE id = :fid
    ");
    $stmt->execute([
        'vid' => $newVersionId,
        'fid' => $filamentId,
    ]);
}
