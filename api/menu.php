<?php
// DeepSeek POS — Menu API v3 (with specs + addons)
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();

    // GET spec options + addons for a dish
    if ($action === 'options') {
        $dishId = intval($_GET['dish_id'] ?? 0);
        if (!$dishId) jsonError('Missing dish_id', 400);

        $specs = $db->prepare("
            SELECT dsd.id AS dim_id, dsd.name_zh AS dim_name_zh, dsd.name_en AS dim_name_en, 
                   dsd.name_my AS dim_name_my, dsd.input_type, dsd.is_required, dsd.max_select,
                   dso.id AS opt_id, dso.value_zh, dso.value_en, dso.value_my, 
                   dso.price, dso.is_default
            FROM dish_spec_dimensions dsd
            JOIN dish_spec_options dso ON dso.dimension_id = dsd.id AND dso.dish_id = ?
            ORDER BY dsd.sort_order, dso.sort_order
        ");
        $specs->execute([$dishId]);

        $grouped = [];
        foreach ($specs as $r) {
            $dimId = $r['dim_id'];
            if (!isset($grouped[$dimId])) {
                $grouped[$dimId] = [
                    'id' => $dimId,
                    'name_zh' => $r['dim_name_zh'],
                    'name_en' => $r['dim_name_en'],
                    'name_my' => $r['dim_name_my'],
                    'input_type' => $r['input_type'],
                    'is_required' => (bool)$r['is_required'],
                    'max_select' => (int)$r['max_select'],
                    'options' => []
                ];
            }
            $grouped[$dimId]['options'][] = [
                'id' => $r['opt_id'],
                'value_zh' => $r['value_zh'],
                'value_en' => $r['value_en'],
                'value_my' => $r['value_my'],
                'price' => floatval($r['price']),
                'is_default' => (bool)$r['is_default'],
            ];
        }

        // Addons
        $addons = $db->prepare("SELECT * FROM dish_addons WHERE dish_id = ? AND is_available = 1 ORDER BY sort_order");
        $addons->execute([$dishId]);

        jsonSuccess([
            'spec_groups' => array_values($grouped),
            'addons' => $addons->fetchAll(),
        ]);
    }

    // Full menu (default)
    $cats = $db->query("SELECT * FROM categories WHERE store_id = 1 AND is_active = 1 ORDER BY sort_order")->fetchAll();
    $dishes = $db->query("SELECT * FROM dishes WHERE store_id = 1 AND is_available = 1 ORDER BY sort_order")->fetchAll();

    // Attach spec dimensions summary + addons to each dish
    $dishSpecs = $db->query("
        SELECT dsd.id AS dim_id, dsd.name_zh AS dim_name, dsd.input_type, dsd.max_select,
               dso.dish_id, dso.price, dso.value_zh
        FROM dish_spec_dimensions dsd
        JOIN dish_spec_options dso ON dso.dimension_id = dsd.id
        ORDER BY dsd.sort_order, dso.sort_order
    ")->fetchAll();

    $dishAddons = $db->query("SELECT * FROM dish_addons WHERE is_available = 1 ORDER BY dish_id, sort_order")->fetchAll();

    // Map specs to dishes
    $specMap = [];
    foreach ($dishSpecs as $s) {
        $did = $s['dish_id'];
        if (!isset($specMap[$did])) $specMap[$did] = [];
        $dimId = $s['dim_id'];
        if (!isset($specMap[$did][$dimId])) {
            $specMap[$did][$dimId] = [
                'id' => $dimId,
                'name_zh' => $s['dim_name'],
                'input_type' => $s['input_type'],
                'max_select' => (int)$s['max_select'],
                'options' => []
            ];
        }
        $specMap[$did][$dimId]['options'][] = ['value_zh' => $s['value_zh'], 'price' => floatval($s['price'])];
    }

    $addonMap = [];
    foreach ($dishAddons as $a) {
        $addonMap[$a['dish_id']][] = $a;
    }

    foreach ($dishes as &$d) {
        $d['has_specs'] = isset($specMap[$d['id']]);
        $d['spec_groups'] = isset($specMap[$d['id']]) ? array_values($specMap[$d['id']]) : [];
        $d['has_addons'] = isset($addonMap[$d['id']]);
        $d['addons'] = $addonMap[$d['id']] ?? [];
        $d['price'] = floatval($d['base_price'] ?? $d['price'] ?? 0);
        $d['is_popular'] = (bool)($d['is_popular'] ?? false);
        $d['is_available'] = (bool)($d['is_available'] ?? true);
        $d['stock_daily'] = intval($d['stock_daily'] ?? 999);
        $d['stock_used_today'] = intval($d['stock_used_today'] ?? 0);
    }

    jsonSuccess(['categories' => $cats, 'dishes' => $dishes]);

} catch (Exception $e) {
    jsonError('System error: ' . $e->getMessage(), 500);
}
