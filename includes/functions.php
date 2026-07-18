<?php
require_once __DIR__ . '/../config/database.php';

// ============================================================
// 会话管理
// ============================================================
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    startSession();
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}

// ============================================================
// 语言切换
// ============================================================
function t(string $zh, string $en, string $ms, string $lang = 'zh'): string {
    return match($lang) {
        'en' => $en,
        'ms' => $ms,
        default => $zh,
    };
}

function getLang(): string {
    return $_SESSION['lang'] ?? 'zh';
}

function setLang(string $lang): void {
    $_SESSION['lang'] = $lang;
}

// ============================================================
// 响应输出 (JSON)
// ============================================================
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess($data = null, string $message = 'ok'): void {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $code);
}

// ============================================================
// 订单编号生成 (每日重置)
// ============================================================
function generateOrderNumber(PDO $db, int $storeId = 1): string {
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE store_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$storeId, $today]);
    $row = $stmt->fetch();
    $seq = str_pad(($row['cnt'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    return chr(65 + ($storeId - 1)) . $seq;
}

// ============================================================
// 价格计算
// ============================================================
function calculateNetAmount(float $subtotal, float $discountAmount = 0.00, float $sstRate = 6.0): array {
    $afterDiscount = max(0, $subtotal - $discountAmount);
    $taxAmount = round($afterDiscount * $sstRate / 100, 2);
    $rawTotal = $afterDiscount + $taxAmount;
    $rounded = round($rawTotal / 0.05) * 0.05;
    $roundingAdjust = round($rounded - $rawTotal, 2);
    $netAmount = max(0, round($rawTotal + $roundingAdjust, 2));
    return [
        'subtotal' => round($subtotal, 2),
        'discount_amount' => round($discountAmount, 2),
        'tax_amount' => $taxAmount,
        'rounding_adjust' => $roundingAdjust,
        'net_amount' => $netAmount,
    ];
}

// ============================================================
// 库存检查与扣减
// ============================================================
function checkStock(PDO $db, int $dishId, int $quantity): bool {
    $stmt = $db->prepare("SELECT stock_daily, stock_used_today FROM dishes WHERE id = ? AND is_available = 1");
    $stmt->execute([$dishId]);
    $dish = $stmt->fetch();
    if (!$dish) return false;
    return ($dish['stock_daily'] - $dish['stock_used_today']) >= $quantity;
}

function deductStock(PDO $db, int $dishId, int $quantity): bool {
    try {
        $stmt = $db->prepare("SELECT stock_daily, stock_used_today FROM dishes WHERE id = ? FOR UPDATE");
        $stmt->execute([$dishId]);
        $dish = $stmt->fetch();
        if (!$dish || ($dish['stock_daily'] - $dish['stock_used_today']) < $quantity) {
            return false;
        }
        $stmt = $db->prepare("UPDATE dishes SET stock_used_today = stock_used_today + ? WHERE id = ?");
        $stmt->execute([$quantity, $dishId]);

        $stmt = $db->prepare("UPDATE dishes SET is_available = 0 WHERE id = ? AND stock_daily - stock_used_today <= 0");
        $stmt->execute([$dishId]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// 会员积分
// ============================================================
function earnPoints(PDO $db, int $memberId, int $points, string $refType = null, int $refId = null): void {
    $stmt = $db->prepare("UPDATE members SET points = points + ?, total_points_earned = total_points_earned + ? WHERE id = ?");
    $stmt->execute([$points, $points, $memberId]);

    $stmt = $db->prepare("INSERT INTO point_logs (member_id, points, type, reference_type, reference_id) VALUES (?, ?, 'EARN', ?, ?)");
    $stmt->execute([$memberId, $points, $refType, $refId]);
}

function redeemPoints(PDO $db, int $memberId, int $points, string $refType = null, int $refId = null): bool {
    $stmt = $db->prepare("SELECT points FROM members WHERE id = ? FOR UPDATE");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    if (!$member || $member['points'] < $points) return false;

    $stmt = $db->prepare("UPDATE members SET points = points - ? WHERE id = ?");
    $stmt->execute([$points, $memberId]);

    $stmt = $db->prepare("INSERT INTO point_logs (member_id, points, type, reference_type, reference_id) VALUES (?, ?, 'REDEEM', ?, ?)");
    $stmt->execute([$memberId, -$points, $refType, $refId]);
    return true;
}

// ============================================================
// 会员查找/注册
// ============================================================
function findOrCreateMember(PDO $db, string $phone, string $name = null): array {
    $stmt = $db->prepare("SELECT * FROM members WHERE phone = ? AND store_id = 1");
    $stmt->execute([$phone]);
    $member = $stmt->fetch();
    if ($member) return $member;

    $stmt = $db->prepare("INSERT INTO members (store_id, phone, name) VALUES (1, ?, ?)");
    $stmt->execute([$phone, $name]);
    $id = $db->lastInsertId();
    return ['id' => $id, 'phone' => $phone, 'name' => $name, 'points' => 0];
}

// ============================================================
// 促销计算
// ============================================================
function applyPromotions(PDO $db, array $items, float $subtotal): array {
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("SELECT * FROM promotions WHERE store_id = 1 AND is_active = 1 AND start_date <= ? AND end_date >= ?");
    $stmt->execute([$now, $now]);
    $promotions = $stmt->fetchAll();

    $discount = 0.00;
    $applied = [];

    foreach ($promotions as $promo) {
        $rule = json_decode($promo['rule_json'], true);

        switch ($promo['type']) {
            case 'SECOND_HALF':
                foreach ($items as $item) {
                    if (in_array($item['dish_id'], $rule['dish_ids'] ?? []) && $item['quantity'] >= 2) {
                        $halfCount = intdiv($item['quantity'], 2);
                        $discount += $halfCount * $item['unit_price'] * 0.5;
                        $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name_zh'], 'discount' => $halfCount * $item['unit_price'] * 0.5];
                    }
                }
                break;

            case 'COMBO':
                $comboDishIds = $rule['dish_ids'] ?? [];
                $comboPrice = floatval($rule['combo_price'] ?? 0);
                $foundIds = array_map(fn($i) => $i['dish_id'], $items);
                $hasAll = empty(array_diff($comboDishIds, $foundIds));
                if ($hasAll && $comboPrice > 0) {
                    $originalPrice = 0;
                    foreach ($items as $item) {
                        if (in_array($item['dish_id'], $comboDishIds)) {
                            $originalPrice += $item['unit_price'] * $item['quantity'];
                        }
                    }
                    if ($originalPrice > $comboPrice) {
                        $discount += $originalPrice - $comboPrice;
                        $applied[] = ['promotion_id' => $promo['id'], 'name' => $promo['name_zh'], 'discount' => $originalPrice - $comboPrice];
                    }
                }
                break;

            case 'UPSELL':
                break;
        }
    }

    return [
        'discount' => round($discount, 2),
        'applied' => $applied,
    ];
}

// ============================================================
// 打印内容生成
// ============================================================
function generatePrintContent(array $order, array $items, string $storeName = 'ABC FOOD'): string {
    $line = str_repeat('═', 38) . '╗' . "\n";
    $content = $line;
    $content .= "  {$storeName}\n";
    $content .= "  PSK Seri Kembangan\n";
    $content .= str_repeat('═', 38) . '╡' . "\n";
    $content .= "  订单: {$order['order_number']}  桌号: {$order['table_no']}\n";
    $content .= "  时间: " . date('Y-m-d H:i') . "\n";
    $content .= str_repeat('─', 38) . '┤' . "\n";

    foreach ($items as $item) {
        $lineText = "  {$item['quantity']}x {$item['dish_name_zh']}" . str_pad('', 2, ' ') . number_format($item['subtotal'], 2) . "\n";
        $content .= $lineText;
        if (!empty($item['options_text'])) {
            $content .= "    + {$item['options_text']}\n";
        }
        if (!empty($item['remark'])) {
            $content .= "    ({$item['remark']})\n";
        }
    }

    $content .= str_repeat('─', 38) . '┤' . "\n";
    $content .= "  小计:              " . str_pad(number_format($order['subtotal'], 2), 8, ' ', STR_PAD_LEFT) . "\n";
    $content .= "  折扣:              " . str_pad(number_format($order['discount_amount'], 2), 8, ' ', STR_PAD_LEFT) . "\n";
    $content .= "  SST 6%:            " . str_pad(number_format($order['tax_amount'], 2), 8, ' ', STR_PAD_LEFT) . "\n";
    $content .= "  四舍五入:          " . str_pad(number_format($order['rounding_adjust'], 2), 8, ' ', STR_PAD_LEFT) . "\n";
    $content .= str_repeat('─', 38) . '┤' . "\n";
    $content .= "  实付:            RM" . str_pad(number_format($order['net_amount'], 2), 7, ' ', STR_PAD_LEFT) . "\n";
    $content .= str_repeat('═', 38) . '╛' . "\n";
    $content .= "  取餐号: {$order['order_number']}\n";
    $content .= "  预计等待: {$order['estimated_wait_minutes']}分钟\n\n";
    $content .= "  感谢您的光临！\n\n\n";
    return $content;
}

// ============================================================
// 创建打印任务
// ============================================================
function createPrintJobs(PDO $db, int $orderId, array $items, array $order): void {
    $stmt = $db->prepare("SELECT * FROM printers WHERE store_id = 1 AND is_active = 1");
    $stmt->execute();
    $printers = $stmt->fetchAll();

    $content = generatePrintContent($order, $items);

    foreach ($printers as $printer) {
        $printContent = $content;
        if ($printer['category_id']) {
            $catItemIds = array_map(fn($i) => $i['dish_id'], $items);
            $stmt = $db->prepare("SELECT id FROM dishes WHERE category_id = ?");
            $stmt->execute([$printer['category_id']]);
            $catDishIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $hasMatch = !empty(array_intersect($catItemIds, $catDishIds));
            if (!$hasMatch) continue;
        }

        $stmt = $db->prepare("INSERT INTO print_jobs (order_id, printer_id, content, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->execute([$orderId, $printer['id'], $printContent]);
    }
}

// ============================================================
// 文件上传
// ============================================================
function uploadImage(array $file, string $subDir = 'dishes'): ?string {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($file['type'], $allowedTypes)) return null;
    if ($file['size'] > $maxSize) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $uploadDir = UPLOAD_PATH . '/' . $subDir;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        return DISH_IMAGE_PATH . $filename;
    }
    return null;
}

// ============================================================
// 库存重置 (每日定时任务)
// ============================================================
function resetDailyStock(PDO $db, int $storeId = 1): int {
    $today = date('Y-m-d');
    $stmt = $db->prepare("UPDATE dishes SET stock_used_today = 0, stock_updated_date = ? WHERE store_id = ? AND (stock_updated_date IS NULL OR stock_updated_date < ?)");
    $stmt->execute([$today, $storeId, $today]);
    $updated = $stmt->rowCount();

    $stmt = $db->prepare("UPDATE dishes SET is_available = 1 WHERE store_id = ? AND stock_daily > 0 AND is_available = 0");
    $stmt->execute([$storeId]);

    return $updated;
}

// ============================================================
// 安全过滤
// ============================================================
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitizePhone(string $phone): string {
    return preg_replace('/[^0-9]/', '', $phone);
}
