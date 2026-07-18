<?php
// DeepSeek POS — Specs & Addons Admin API
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $json = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = intval($_GET['id'] ?? 0);

    // ===== SPEC DIMENSIONS =====
    if ($action === 'dimensions') {
        if ($method === 'GET') {
            $stmt = $db->query("SELECT * FROM dish_spec_dimensions WHERE store_id = 1 ORDER BY sort_order");
            jsonSuccess($stmt->fetchAll());
        }
        if ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO dish_spec_dimensions (name_zh, name_en, name_my, input_type, is_required, max_select, sort_order) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$json['name_zh'], $json['name_en'], $json['name_my'], $json['input_type']??'BUTTON', $json['is_required']??1, $json['max_select']??1, $json['sort_order']??0]);
            jsonSuccess(['id' => $db->lastInsertId()], 'created');
        }
        if ($method === 'PUT' && $id) {
            $stmt = $db->prepare("UPDATE dish_spec_dimensions SET name_zh=?, name_en=?, name_my=?, input_type=?, is_required=?, max_select=?, sort_order=? WHERE id=?");
            $stmt->execute([$json['name_zh'], $json['name_en'], $json['name_my'], $json['input_type'], $json['is_required'], $json['max_select'], $json['sort_order'], $id]);
            jsonSuccess(null, 'updated');
        }
        if ($method === 'DELETE' && $id) {
            $db->prepare("DELETE FROM dish_spec_dimensions WHERE id=?")->execute([$id]);
            jsonSuccess(null, 'deleted');
        }
    }

    // ===== SPEC OPTIONS (per dish) =====
    if ($action === 'spec_options') {
        $dishId = intval($_GET['dish_id'] ?? 0);
        if ($method === 'GET' && $dishId) {
            $stmt = $db->prepare("
                SELECT dso.*, dsd.name_zh AS dim_name_zh
                FROM dish_spec_options dso
                JOIN dish_spec_dimensions dsd ON dsd.id = dso.dimension_id
                WHERE dso.dish_id = ?
                ORDER BY dsd.sort_order, dso.sort_order
            ");
            $stmt->execute([$dishId]);
            jsonSuccess($stmt->fetchAll());
        }
        if ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$json['dimension_id'], $json['dish_id'], $json['value_zh'], $json['value_en']??'', $json['value_my']??'', $json['price']??0, $json['is_default']??0, $json['sort_order']??0]);
            jsonSuccess(['id' => $db->lastInsertId()], 'created');
        }
        if ($method === 'DELETE' && $id) {
            $db->prepare("DELETE FROM dish_spec_options WHERE id=?")->execute([$id]);
            jsonSuccess(null, 'deleted');
        }
    }

    // ===== ADDONS =====
    if ($action === 'addons') {
        $dishId = intval($_GET['dish_id'] ?? 0);
        if ($method === 'GET' && $dishId) {
            $stmt = $db->prepare("SELECT * FROM dish_addons WHERE dish_id = ? ORDER BY sort_order");
            $stmt->execute([$dishId]);
            jsonSuccess($stmt->fetchAll());
        }
        if ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO dish_addons (dish_id, name_zh, name_en, name_my, price, sort_order) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$json['dish_id'], $json['name_zh'], $json['name_en']??'', $json['name_my']??'', $json['price']??0, $json['sort_order']??0]);
            jsonSuccess(['id' => $db->lastInsertId()], 'created');
        }
        if ($method === 'DELETE' && $id) {
            $db->prepare("DELETE FROM dish_addons WHERE id=?")->execute([$id]);
            jsonSuccess(null, 'deleted');
        }
    }

    // ===== TABLE STICKER =====
    if ($action === 'sticker') {
        $tableNo = $_GET['table'] ?? '1';
        $width = $_GET['width'] ?? '58mm';
        $config = $db->query("SELECT * FROM table_sticker_config WHERE store_id = 1 LIMIT 1")->fetch();
        $url = ($config['qrcode_base_url'] ?? '/abc-food/index.php?table=') . $tableNo;
        $name = $config['restaurant_name'] ?? 'ABC FOOD';
        $addr = $config['address'] ?? '';

        $qr = "█" . str_repeat("█", $width === '80mm' ? 28 : 20) . "█\n";
        $bar = str_repeat("═", $width === '80mm' ? 30 : 22);

        $html = "<pre style='font-family:monospace;font-size:10px;line-height:1.1'>";
        $html .= str_repeat("═", $width === '80mm' ? 34 : 26) . "\n";
        $html .= "    ★  {$name}  ★\n";
        $html .= "    {$addr}\n";
        $html .= str_repeat("─", $width === '80mm' ? 34 : 26) . "\n\n";
        // QR placeholder
        $html .= str_repeat("██████████████████████████████\n", 18);
        $html .= "\n" . str_repeat("─", $width === '80mm' ? 34 : 26) . "\n";
        $html .= "    📍 桌号: {$tableNo}\n";
        $html .= "    📱 扫码点餐\n";
        $html .= str_repeat("═", $width === '80mm' ? 34 : 26) . "\n";
        $html .= "（沿此线剪下贴在桌面）\n";
        $html .= str_repeat("═", $width === '80mm' ? 34 : 26) . "\n";
        $html .= "</pre>";

        $html .= "<style>@media print{body{-webkit-print-color-adjust:exact}}button{display:none}</style>";
        $html .= "<button onclick='window.print()' style='padding:10px 20px;font-size:16px;cursor:pointer'>🖨️ 打印桌贴</button>";

        echo $html;
        exit;
    }

    jsonError('Unknown action', 400);

} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
