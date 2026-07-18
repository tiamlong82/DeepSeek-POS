<?php
// DeepSeek POS — Orders API v3 (with specs + addons + snapshots)
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
    $db = getDB();

    // GET: 查询订单
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'status') {
            $orderNumber = $_GET['order_number'] ?? '';
            if (!$orderNumber) jsonError('Missing order number', 400);
            $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();
            if (!$order) jsonError('Order not found', 404);
            $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll();
            jsonSuccess($order);
        }
        // AA split bill: get all orders for a table
        if ($action === 'table_orders') {
            $tableNo = $_GET['table_no'] ?? '';
            if (!$tableNo) jsonError('Missing table number', 400);
            $stmt = $db->prepare("SELECT * FROM orders WHERE table_no = ? AND DATE(created_at) = CURDATE() AND order_status != 'CANCELLED' ORDER BY created_at");
            $stmt->execute([$tableNo]);
            $orders = $stmt->fetchAll();
            foreach ($orders as &$o) {
                $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemStmt->execute([$o['id']]);
                $o['items'] = $itemStmt->fetchAll();
            }
            jsonSuccess(['table_no' => $tableNo, 'orders' => $orders]);
        }
        jsonError('Unknown action', 400);
    }

    // POST: 创建订单 (v3 with specs + addons)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) jsonError('Invalid request data', 400);

        $tableNo = $input['table_no'] ?? $input['tableNo'] ?? '';
        $items = $input['items'] ?? [];
        $paymentMethod = $input['payment_method'] ?? $input['paymentMethod'] ?? 'CASH';
        $memberPhone = $input['member_phone'] ?? $input['memberPhone'] ?? null;
        $remark = $input['remark'] ?? '';
        $isTakeaway = $input['is_takeaway'] ?? $input['isTakeaway'] ?? false;

        if (empty($items)) jsonError('No items in order', 400);

        // Load dishes + specs + addons
        $dishIds = array_map(fn($i) => intval($i['dish_id'] ?? $i['dishId'] ?? 0), $items);
        $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
        $stmt = $db->prepare("SELECT * FROM dishes WHERE id IN ($placeholders)");
        $stmt->execute($dishIds);
        $dishMap = [];
        foreach ($stmt->fetchAll() as $d) $dishMap[$d['id']] = $d;

        // Load all spec options for these dishes
        $specStmt = $db->query("
            SELECT dso.*, dsd.name_zh AS dim_name_zh, dsd.name_en AS dim_name_en 
            FROM dish_spec_options dso
            JOIN dish_spec_dimensions dsd ON dsd.id = dso.dimension_id
            WHERE dso.dish_id IN ($placeholders)
        ");
        $specStmt->execute($dishIds);
        $allSpecs = [];
        foreach ($specStmt->fetchAll() as $s) {
            $allSpecs[$s['dish_id']][$s['id']] = $s;
        }

        // Load all addons for these dishes
        $addonStmt = $db->prepare("SELECT * FROM dish_addons WHERE dish_id IN ($placeholders)");
        $addonStmt->execute($dishIds);
        $allAddons = [];
        foreach ($addonStmt->fetchAll() as $a) {
            $allAddons[$a['dish_id']][$a['id']] = $a;
        }

        // Validate stock
        foreach ($items as $item) {
            $did = intval($item['dish_id'] ?? $item['dishId'] ?? 0);
            $qty = intval($item['quantity'] ?? 1);
            $dish = $dishMap[$did] ?? null;
            if (!$dish) jsonError("Dish ID $did not found", 400);
            $available = intval($dish['stock_daily']) - intval($dish['stock_used_today']);
            if ($available < $qty) {
                jsonError("{$dish['name_zh']} out of stock (left: $available)", 400);
            }
        }

        // Calculate prices with specs + addons
        $subtotal = 0;
        $orderItems = [];
        $paymentNames = [
            'CASH' => 'Counter Cash', 'TNG' => 'Touch n Go',
            'GRABPAY' => 'GrabPay', 'DUITNOW' => 'DuitNow QR', 'QR' => 'QR Pay',
        ];

        foreach ($items as $item) {
            $did = intval($item['dish_id'] ?? $item['dishId'] ?? 0);
            $qty = intval($item['quantity'] ?? 1);
            $optionIds = $item['option_ids'] ?? $item['optionIds'] ?? [];
            $addonIds = $item['addon_ids'] ?? $item['addonIds'] ?? [];
            $itemRemark = $item['remark'] ?? '';

            $dish = $dishMap[$did];
            $basePrice = floatval($dish['price'] ?? $dish['base_price'] ?? 0);

            // Calculate spec price
            $specPrice = 0;
            $specSnapshot = [];
            if (!empty($optionIds) && isset($allSpecs[$did])) {
                foreach ((array)$optionIds as $oid) {
                    $opt = $allSpecs[$did][$oid] ?? null;
                    if ($opt) {
                        $optPrice = floatval($opt['price']) + floatval($opt['extra_price'] ?? 0);
                        $specPrice += $optPrice;
                        $specSnapshot[] = [
                            'id' => (int)$oid,
                            'dimension' => $opt['dim_name_zh'],
                            'value_zh' => $opt['value_zh'],
                            'value_en' => $opt['value_en'],
                            'price' => $optPrice,
                        ];
                    }
                }
            }

            // Calculate addon price
            $addonPrice = 0;
            $addonSnapshot = [];
            if (!empty($addonIds) && isset($allAddons[$did])) {
                foreach ((array)$addonIds as $aid) {
                    $ad = $allAddons[$did][$aid] ?? null;
                    if ($ad) {
                        $ap = floatval($ad['price']);
                        $addonPrice += $ap;
                        $addonSnapshot[] = [
                            'id' => (int)$aid,
                            'name_zh' => $ad['name_zh'],
                            'name_en' => $ad['name_en'],
                            'price' => $ap,
                        ];
                    }
                }
            }

            $unitPrice = $basePrice + $specPrice + $addonPrice;
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $orderItems[] = [
                'dish_id' => $did,
                'dish_name_zh' => $dish['name_zh'],
                'dish_name_en' => $dish['name_en'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'spec_snapshot' => json_encode($specSnapshot, JSON_UNESCAPED_UNICODE),
                'addon_snapshot' => json_encode($addonSnapshot, JSON_UNESCAPED_UNICODE),
                'remark' => $itemRemark,
                'subtotal' => $lineTotal,
            ];
        }

        // Apply promotions
        $promoResult = applyPromotions($db, $orderItems, $subtotal);
        $discountAmount = $promoResult['discount'] ?? 0;

        // Calculate final amount
        $pricing = calculateNetAmount($subtotal, $discountAmount);
        $orderNumber = generateOrderNumber($db);

        // Member handling
        $memberId = null;
        if ($memberPhone) {
            $member = findOrCreateMember($db, $memberPhone);
            $memberId = $member['id'];
        }

        $db->beginTransaction();
        try {
            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (store_id, order_number, table_no, member_id, customer_phone,
                    subtotal, discount_amount, tax_amount, rounding_adjust, net_amount,
                    payment_method, payment_status, order_status, estimated_wait_minutes, remark, is_takeaway)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', 'NEW', ?, ?, ?)
            ");
            $stmt->execute([
                $orderNumber, $tableNo, $memberId, $memberPhone ?: null,
                $pricing['subtotal'], $pricing['discount_amount'],
                $pricing['tax_amount'], $pricing['rounding_adjust'], $pricing['net_amount'],
                $paymentMethod, constant('DEFAULT_WAIT_MINUTES'),
                $remark, $isTakeaway ? 1 : 0,
            ]);
            $orderId = $db->lastInsertId();

            // Insert order items with spec/addon snapshots
            $stmtItem = $db->prepare("
                INSERT INTO order_items (order_id, dish_id, dish_name_snapshot_zh, dish_name_snapshot_en,
                    quantity, unit_price, spec_snapshot, addon_snapshot, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($orderItems as $item) {
                $stmtItem->execute([
                    $orderId, $item['dish_id'],
                    $item['dish_name_zh'], $item['dish_name_en'],
                    $item['quantity'], $item['unit_price'],
                    $item['spec_snapshot'], $item['addon_snapshot'],
                    $item['remark'],
                ]);
                deductStock($db, $item['dish_id'], $item['quantity']);
            }

            // Member points
            if ($memberId) {
                $points = intval($pricing['net_amount']);
                earnPoints($db, $memberId, $points, 'order', $orderId);
            }

            // Print jobs
            $orderData = [
                'order_number' => $orderNumber, 'table_no' => $tableNo,
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'rounding_adjust' => $pricing['rounding_adjust'],
                'net_amount' => $pricing['net_amount'],
                'estimated_wait_minutes' => constant('DEFAULT_WAIT_MINUTES'),
            ];
            createPrintJobs($db, $orderId, $orderItems, $orderData);

            $db->commit();

            jsonSuccess([
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'net_amount' => $pricing['net_amount'],
                'estimated_wait_minutes' => constant('DEFAULT_WAIT_MINUTES'),
                'payment_method' => $paymentMethod,
                'payment_method_name' => $paymentNames[$paymentMethod] ?? $paymentMethod,
                'table_no' => $tableNo,
                'items_count' => count($orderItems),
            ], 'Order created');

        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Order failed: ' . $e->getMessage(), 500);
        }
    }

} catch (Exception $e) {
    jsonError('System error: ' . $e->getMessage(), 500);
}
