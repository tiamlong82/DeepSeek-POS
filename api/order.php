<?php
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

try {
    $db = getDB();

    // GET: 查询订单状态
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'status') {
            $orderNumber = $_GET['order_number'] ?? '';
            if (!$orderNumber) jsonError('缺少订单号', 400);

            $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch();
            if (!$order) jsonError('订单不存在', 404);

            $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll();

            jsonSuccess($order);
        }
        jsonError('未知操作', 400);
    }

    // POST: 创建订单
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) jsonError('无效的请求数据', 400);

        $tableNo = $input['table_no'] ?? '';
        $items = $input['items'] ?? [];
        $paymentMethod = $input['payment_method'] ?? 'CASH';
        $memberPhone = $input['member_phone'] ?? null;

        if (empty($items)) jsonError('请至少选择一件商品', 400);

        // 验证库存
        $dishIds = array_map(fn($i) => $i['dish_id'], $items);
        $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
        $stmt = $db->prepare("SELECT * FROM dishes WHERE id IN ($placeholders)");
        $stmt->execute($dishIds);
        $dbDishes = $stmt->fetchAll();
        $dishMap = [];
        foreach ($dbDishes as $d) $dishMap[$d['id']] = $d;

        foreach ($items as $item) {
            $dish = $dishMap[$item['dish_id']] ?? null;
            if (!$dish) jsonError("菜品ID {$item['dish_id']} 不存在", 400);
            $available = $dish['stock_daily'] - $dish['stock_used_today'];
            if ($available < $item['quantity']) {
                jsonError("{$dish['name_zh']} 库存不足 (剩余: $available)", 400);
            }
        }

        // 计算价格
        $subtotal = 0;
        $orderItems = [];
        foreach ($items as $item) {
            $dish = $dishMap[$item['dish_id']];
            $lineTotal = $dish['price'] * $item['quantity'];
            $subtotal += $lineTotal;
            $orderItems[] = [
                'dish_id' => $dish['id'],
                'dish_name_zh' => $dish['name_zh'],
                'dish_name_en' => $dish['name_en'],
                'name_ms' => $dish['name_ms'],
                'quantity' => $item['quantity'],
                'unit_price' => $dish['price'],
                'options_text' => $item['options_text'] ?? '',
                'remark' => $item['remark'] ?? '',
                'subtotal' => $lineTotal,
            ];
        }

        // 应用促销
        $promoResult = applyPromotions($db, $orderItems, $subtotal);
        $discountAmount = $promoResult['discount'];

        // 计算最终金额
        $pricing = calculateNetAmount($subtotal, $discountAmount);

        // 生成订单号
        $orderNumber = generateOrderNumber($db);

        // 会员处理
        $memberId = null;
        if ($memberPhone) {
            $member = findOrCreateMember($db, $memberPhone);
            $memberId = $member['id'];
        }

        $db->beginTransaction();
        try {
            // 创建订单
            $stmt = $db->prepare("
                INSERT INTO orders (store_id, order_number, table_no, member_id, member_phone,
                    subtotal, discount_amount, tax_amount, rounding_adjust, net_amount,
                    payment_method, payment_status, order_status, estimated_wait_minutes)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', 'NEW', ?)
            ");
            $stmt->execute([
                $orderNumber,
                $tableNo,
                $memberId,
                $memberPhone,
                $pricing['subtotal'],
                $pricing['discount_amount'],
                $pricing['tax_amount'],
                $pricing['rounding_adjust'],
                $pricing['net_amount'],
                $paymentMethod,
                DEFAULT_WAIT_MINUTES,
            ]);
            $orderId = $db->lastInsertId();

            // 插入订单明细 & 扣库存
            $stmtItem = $db->prepare("
                INSERT INTO order_items (order_id, dish_id, dish_name_zh, dish_name_en, quantity, unit_price, options_text, subtotal, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($orderItems as $item) {
                $stmtItem->execute([
                    $orderId,
                    $item['dish_id'],
                    $item['dish_name_zh'],
                    $item['dish_name_en'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['options_text'],
                    $item['subtotal'],
                    $item['remark'],
                ]);
                deductStock($db, $item['dish_id'], $item['quantity']);
            }

            // 会员积分
            if ($memberId) {
                $points = intval($pricing['net_amount']);
                earnPoints($db, $memberId, $points, 'order', $orderId);
            }

            // 创建打印任务
            $orderData = [
                'order_number' => $orderNumber,
                'table_no' => $tableNo,
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'rounding_adjust' => $pricing['rounding_adjust'],
                'net_amount' => $pricing['net_amount'],
                'estimated_wait_minutes' => DEFAULT_WAIT_MINUTES,
            ];
            createPrintJobs($db, $orderId, $orderItems, $orderData);

            $db->commit();

            $paymentNames = [
                'CASH' => '柜台付现金',
                'TNG' => 'Touch n Go',
                'GRABPAY' => 'GrabPay',
                'DUITNOW' => 'DuitNow QR',
                'QR' => 'QR Pay',
            ];

            jsonSuccess([
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'net_amount' => $pricing['net_amount'],
                'estimated_wait_minutes' => DEFAULT_WAIT_MINUTES,
                'payment_method' => $paymentMethod,
                'payment_method_name' => $paymentNames[$paymentMethod] ?? $paymentMethod,
                'table_no' => $tableNo,
                'items_count' => count($orderItems),
            ], '下单成功');

        } catch (Exception $e) {
            $db->rollBack();
            jsonError('下单失败: ' . $e->getMessage(), 500);
        }
    }

} catch (Exception $e) {
    jsonError('系统错误: ' . $e->getMessage(), 500);
}
