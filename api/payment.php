<?php
// DeepSeek POS — Payment Confirmation API
require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonError('POST required', 405); exit; }

try {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $orderNumber = $input['order_number'] ?? '';
    $paymentMethod = $input['payment_method'] ?? 'TNG';

    if (!$orderNumber) jsonError('Missing order number', 400);

    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    if (!$order) jsonError('Order not found', 404);
    if ($order['payment_status'] === 'PAID') jsonError('Already paid', 400);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'PAID', order_status = 'COOKING', payment_method = ? WHERE id = ?");
        $stmt->execute([$paymentMethod, $order['id']]);
        $db->commit();
        jsonSuccess(['order_number' => $orderNumber], 'Payment confirmed');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Payment failed: ' . $e->getMessage(), 500);
    }
} catch (Exception $e) {
    jsonError('Error: ' . $e->getMessage(), 500);
}
