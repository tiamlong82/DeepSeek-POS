# ABC FOOD 智慧点餐系统

基于 PHP + MySQL (XAMPP) 开发的餐饮扫码点餐系统，支持三语切换、后厨自动分单打印、会员积分、促销管理等功能。

## 系统要求

- XAMPP (PHP 8.0+, MySQL 8.0)
- Apache mod_rewrite 模块启用
- 浏览器: Chrome / Edge (后台) , 移动端浏览器 / 微信 (顾客端)

## 安装步骤

### 1. 复制项目到 XAMPP

将 `abc-food` 文件夹复制到 XAMPP 的 `htdocs` 目录:

```
C:\xampp\htdocs\abc-food\
```

### 2. 导入数据库

1. 打开浏览器访问 `http://localhost/phpmyadmin`
2. 点击"导入" → 选择 `abc-food/sql/schema.sql` → 执行
3. 数据库 `abc_food_db` 将自动创建并包含初始数据

### 3. 配置数据库连接

编辑 `config/database.php`，根据你的 XAMPP 设置修改:

```php
define('DB_USER', 'root');   // XAMPP 默认用户名
define('DB_PASS', '');       // XAMPP 默认密码为空
```

### 4. 访问系统

| 页面 | 地址 | 说明 |
|------|------|------|
| 顾客端 | `http://localhost/abc-food/?table=1` | 扫码点餐入口 |
| 订单追踪 | `http://localhost/abc-food/track.php` | 查询订单状态 |
| 管理后台 | `http://localhost/abc-food/admin/login.php` | 后台管理 |

### 5. 默认管理员账号

| 用户名 | 密码 | 角色 |
|--------|------|------|
| admin | admin123 | 管理员 |
| boss | admin123 | 老板 |
| kitchen | admin123 | 后厨 |

## 目录结构

```
abc-food/
├── index.php          # 顾客端入口
├── track.php          # 订单追踪
├── .htaccess          # URL 重写
├── config/
│   └── database.php   # 数据库连接配置
├── sql/
│   └── schema.sql     # 数据库建表脚本
├── includes/
│   ├── functions.php  # 核心函数库
│   ├── admin_header.php
│   └── admin_footer.php
├── api/               # API 接口层
│   ├── menu.php       # 菜单接口
│   ├── order.php      # 订单接口
│   ├── promotion.php  # 促销接口
│   ├── print.php      # 打印接口
│   └── cron.php       # 定时任务
├── admin/             # 管理后台
│   ├── login.php
│   ├── index.php      # 控制台
│   ├── orders.php     # 订单管理
│   ├── dishes.php     # 菜品管理
│   ├── categories.php # 分类管理
│   ├── promotions.php # 促销活动
│   ├── members.php    # 会员管理
│   ├── reports.php    # 数据报表
│   ├── printers.php   # 打印设置
│   └── stock.php      # 库存管理
├── customer/          # 顾客端页面(备用)
├── assets/
│   ├── css/
│   │   ├── style.css  # 顾客端样式
│   │   └── admin.css  # 后台样式
│   └── js/
│       ├── app.js     # 顾客端逻辑
│       ├── cart.js    # 购物车模块
│       └── admin.js   # 后台逻辑
└── uploads/
    └── dishes/        # 菜品图片
```

## 功能清单

### 顾客端
- 扫码识别桌号
- 三语切换 (华/英/马来)
- 按分类浏览菜单
- 菜品规格选择
- 加价购推荐弹窗
- 购物车管理
- 下单 (到柜台付/电子支付)
- AA 拼单 (同桌多人分别下单)
- 订单状态追踪

### 后台管理
- 实时营业看板
- 订单状态流转 (接单→制作→取餐→完成)
- 菜品 CRUD
- 分类管理 (含 IP 打印机绑定)
- 促销活动 (组合套餐/第二份半价/加价购)
- 会员管理与积分记录
- 数据报表 (日报/时段分析/菜品排行)
- 打印机配置 (IP/USB)
- 每日库存管理 (半自动沽清)

## 定时任务设置

### 每日库存重置 (凌晨2:00)

**Windows 任务计划程序:**
1. 打开"任务计划程序"
2. 创建基本任务 → 触发器: 每日 02:00
3. 操作: 启动程序 → `C:\xampp\php\php.exe`
4. 参数: `-r "echo file_get_contents('http://localhost/abc-food/api/cron.php?job=daily_reset&secret=abc-food-cron-2026');"`

或者手动访问:
```
http://localhost/abc-food/api/cron.php?job=daily_reset&secret=abc-food-cron-2026
```

## 技术支持

- 项目: ABC FOOD 智慧点餐系统
- 架构: PHP + MySQL + Redis (可选)
- 前端: 响应式 HTML + CSS + Vanilla JS
