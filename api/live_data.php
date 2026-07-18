<?php
// DeepSeek POS — Live Dashboard Data API (AJAX refresh)
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
$db = getDB();
$today = date('Y-m-d');

// Today stats
$stmt = $db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(net_amount),0) AS revenue FROM orders WHERE DATE(created_at) = ? AND order_status NOT IN ('CANCELLED')");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// Pending orders
$pending = intval($db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('NEW','COOKING')")->fetchColumn());

// Popular dishes today
$popular = $db->query("
    SELECT oi.dish_name_zh AS name, SUM(oi.quantity) AS qty 
    FROM order_items oi 
    JOIN orders o ON o.id = oi.order_id 
    WHERE DATE(o.created_at) = CURDATE() AND o.order_status NOT IN ('CANCELLED')
    GROUP BY oi.dish_name_zh ORDER BY qty DESC LIMIT 5
")->fetchAll();

// Hourly breakdown
$hourly = $db->query("
    SELECT HOUR(created_at) AS h, COUNT(*) AS cnt, COALESCE(SUM(net_amount),0) AS rev 
    FROM orders WHERE DATE(created_at) = CURDATE() AND order_status NOT IN ('CANCELLED')
    GROUP BY HOUR(created_at) ORDER BY h
")->fetchAll();

// Recent orders
$recent = $db->query("SELECT order_number, table_no, net_amount, order_status, created_at FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 10")->fetchAll();

echo json_encode([
    'orders' => intval($todayStats['orders']),
    'revenue' => floatval($todayStats['revenue']),
    'pending' => $pending,
    'popular' => $popular,
    'hourly' => $hourly,
    'recent' => $recent,
], JSON_UNESCAPED_UNICODE);
