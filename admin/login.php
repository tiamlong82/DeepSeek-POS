<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];

            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    } else {
        $error = '请输入用户名和密码';
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC FOOD - 管理登录</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>ABC FOOD</h1>
            <h2>智慧点餐管理系统</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-primary btn-block">登录</button>
            </form>
            <div class="login-hint">默认账号: admin / admin123</div>
        </div>
    </div>
</body>
</html>
