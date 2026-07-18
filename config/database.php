<?php
// ============================================================
// 数据库配置
// ============================================================
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'abc_food_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// 应用配置
// ============================================================
define('APP_NAME', 'ABC FOOD 智慧点餐系统');
define('APP_URL', '/abc-food');
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('DISH_IMAGE_PATH', 'uploads/dishes/');
define('TIMEZONE', 'Asia/Kuala_Lumpur');
define('SST_RATE', 6.00);
define('POINTS_PER_RM', 1);
define('POINTS_REDEEM_RATE', 100);
define('DEFAULT_WAIT_MINUTES', 15);

date_default_timezone_set(TIMEZONE);

// ============================================================
// 数据库连接 (PDO)
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    return $pdo;
}
