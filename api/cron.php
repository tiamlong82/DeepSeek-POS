<?php
// 定时任务入口
// 建议在服务器 crontab 中添加: 0 2 * * * curl http://localhost/abc-food/api/cron.php?job=daily_reset
// XAMPP 下可设置为 Windows 任务计划程序

require_once __DIR__ . '/../includes/functions.php';

$job = $_GET['job'] ?? '';
$secret = $_GET['secret'] ?? '';

// 简单鉴权，防止恶意触发
$cronSecret = 'abc-food-cron-2026';
if ($secret !== $cronSecret) {
    die('Forbidden');
}

try {
    $db = getDB();
    $startTime = microtime(true);

    switch ($job) {
        case 'daily_reset':
            $count = resetDailyStock($db);
            $elapsed = round(microtime(true) - $startTime, 4);
            echo "[OK] 库存重置完成 | 重置菜品: {$count} | 耗时: {$elapsed}s\n";
            break;

        case 'cleanup_print_jobs':
            $stmt = $db->prepare("DELETE FROM print_jobs WHERE status IN ('SUCCESS','FAILED') AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            echo "[OK] 清理打印任务: {$stmt->rowCount()} 条\n";
            break;

        default:
            echo "未知任务: {$job}\n";
            echo "可用任务: daily_reset, cleanup_print_jobs\n";
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
