<?php
// 打印任务处理 API
// IP打印机: 通过 TCP Socket (端口9100) 直接发送 ESC/POS 指令
// USB打印机: 通过 WebSocket 桥接程序转发

require_once __DIR__ . '/../includes/functions.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    // 获取待处理的打印任务
    if ($action === 'pending') {
        $stmt = $db->prepare("SELECT pj.*, pr.ip_address, pr.port, pr.type as printer_type FROM print_jobs pj LEFT JOIN printers pr ON pj.printer_id = pr.id WHERE pj.status = 'PENDING' ORDER BY pj.id ASC LIMIT 10");
        $stmt->execute();
        $jobs = $stmt->fetchAll();

        foreach ($jobs as &$job) {
            // 标记为打印中
            $stmt = $db->prepare("UPDATE print_jobs SET status = 'PRINTING' WHERE id = ?");
            $stmt->execute([$job['id']]);
        }

        jsonSuccess($jobs);
    }

    // 标记打印成功
    if ($action === 'success') {
        $jobId = intval($_POST['id'] ?? 0);
        if ($jobId) {
            $stmt = $db->prepare("UPDATE print_jobs SET status = 'SUCCESS' WHERE id = ?");
            $stmt->execute([$jobId]);
            jsonSuccess(null, '已标记成功');
        }
        jsonError('缺少任务ID', 400);
    }

    // 标记打印失败
    if ($action === 'failed') {
        $jobId = intval($_POST['id'] ?? 0);
        $error = $_POST['error'] ?? '';
        if ($jobId) {
            $stmt = $db->prepare("UPDATE print_jobs SET status = 'FAILED', retry_count = retry_count + 1, error_message = ? WHERE id = ?");
            $stmt->execute([$error, $jobId]);
            jsonSuccess(null, '已标记失败');
        }
        jsonError('缺少任务ID', 400);
    }

    // Socket直连打印 (IP打印机)
    if ($action === 'socket_print') {
        $ip = $_POST['ip'] ?? '';
        $port = intval($_POST['port'] ?? 9100);
        $content = $_POST['content'] ?? '';

        if (!$ip || !$content) jsonError('缺少参数', 400);

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) jsonError('无法创建Socket', 500);

        $connected = @socket_connect($socket, $ip, $port);
        if (!$connected) {
            @socket_close($socket);
            jsonError('无法连接打印机', 500);
        }

        // ESC/POS 指令: 初始化打印机 + 打印内容 + 切纸
        $escPos = chr(27) . '@'; // 初始化
        $escPos .= $content;
        $escPos .= chr(27) . 'm'; // 切纸

        @socket_write($socket, $escPos, strlen($escPos));
        @socket_close($socket);

        jsonSuccess(null, '打印指令已发送');
    }

    jsonError('未知操作', 400);

} catch (Exception $e) {
    jsonError('系统错误: ' . $e->getMessage(), 500);
}
