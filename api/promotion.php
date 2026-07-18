<?php
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    if ($action === 'upsell') {
        $dishId = intval($_GET['dish_id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("SELECT * FROM promotions WHERE store_id = 1 AND is_active = 1 AND type = 'UPSELL' AND start_date <= ? AND end_date >= ?");
        $stmt->execute([$now, $now]);
        $promos = $stmt->fetchAll();

        $upsellItems = [];
        foreach ($promos as $promo) {
            $rule = json_decode($promo['rule_json'], true);
            $triggerDishIds = $rule['trigger_dish_ids'] ?? [];
            $upsellDishIds = $rule['upsell_dish_ids'] ?? [];

            if (in_array($dishId, $triggerDishIds)) {
                foreach ($upsellDishIds as $uid) {
                    $stmt = $db->prepare("SELECT id AS dish_id, name_zh, name_en, name_ms, price FROM dishes WHERE id = ? AND is_available = 1");
                    $stmt->execute([$uid]);
                    $dish = $stmt->fetch();
                    if ($dish) $upsellItems[] = $dish;
                }
            }
        }

        jsonSuccess($upsellItems);
    }

    jsonError('未知操作', 400);
} catch (Exception $e) {
    jsonError('系统错误: ' . $e->getMessage(), 500);
}
