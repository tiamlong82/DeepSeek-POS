<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/functions.php';
requireLogin();

$currentUser = $_SESSION['display_name'] ?? '';
$currentRole = $_SESSION['role'] ?? '';

$pageTitle = $pageTitle ?? '控制台';
$page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABC FOOD - <?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">ABC FOOD</div>
            <div class="sidebar-user"><?= h($currentUser) ?> (<?= h($currentRole) ?>)</div>
            <nav class="sidebar-nav">
                <a href="index.php" class="<?= $page === 'index' ? 'active' : '' ?>">📊 控制台</a>
                <a href="orders.php" class="<?= $page === 'orders' ? 'active' : '' ?>">📋 订单管理</a>
                <a href="dishes.php" class="<?= $page === 'dishes' ? 'active' : '' ?>">🍽️ 菜品管理</a>
                <a href="categories.php" class="<?= $page === 'categories' ? 'active' : '' ?>">📂 分类管理</a>
                <a href="promotions.php" class="<?= $page === 'promotions' ? 'active' : '' ?>">🏷️ 促销活动</a>
                <a href="members.php" class="<?= $page === 'members' ? 'active' : '' ?>">👥 会员管理</a>
                <a href="reports.php" class="<?= $page === 'reports' ? 'active' : '' ?>">📈 数据报表</a>
                <a href="printers.php" class="<?= $page === 'printers' ? 'active' : '' ?>">🖨️ 打印设置</a>
                <a href="stock.php" class="<?= $page === 'stock' ? 'active' : '' ?>">📦 库存管理</a>
                <a href="specs.php" class="<?= $page === 'specs' ? 'active' : '' ?>">⚙️ 规格加料</a>
                <a href="logout.php" class="text-danger">🚪 退出登录</a>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-header">
                <h1><?= h($pageTitle) ?></h1>
                <div class="header-actions">
                    <a href="../index.php?table=1" target="_blank" class="btn-secondary btn-sm">顾客端预览</a>
                </div>
            </header>
            <div class="admin-content">
