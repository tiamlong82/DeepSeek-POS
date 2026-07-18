-- ABC FOOD 智慧点餐系统 - 数据库建表脚本
-- 数据库: abc_food_db
-- 字符集: utf8mb4

CREATE DATABASE IF NOT EXISTS abc_food_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE abc_food_db;

-- ============================================================
-- 门店表 (stores)
-- ============================================================
CREATE TABLE IF NOT EXISTS stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '门店名称',
    address VARCHAR(255) DEFAULT NULL COMMENT '地址',
    phone VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
    tax_reg_no VARCHAR(50) DEFAULT NULL COMMENT '税务登记号',
    sst_rate DECIMAL(5,2) DEFAULT 6.00 COMMENT 'SST税率%',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店表';

-- ============================================================
-- 管理员/员工表 (users)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'BCrypt加密',
    display_name VARCHAR(50) NOT NULL,
    role ENUM('admin','kitchen','cashier','boss') NOT NULL DEFAULT 'cashier' COMMENT '角色',
    store_id INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工表';

-- ============================================================
-- 菜品分类表 (categories)
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    name_zh VARCHAR(50) NOT NULL COMMENT '分类名称(华)',
    name_en VARCHAR(50) NOT NULL COMMENT '分类名称(英)',
    name_ms VARCHAR(50) NOT NULL COMMENT '分类名称(马来)',
    sort_order INT DEFAULT 0 COMMENT '排序',
    printer_ip VARCHAR(45) DEFAULT NULL COMMENT '对应IP打印机地址(分类打印)',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜品分类表';

-- ============================================================
-- 菜品表 (dishes)
-- ============================================================
CREATE TABLE IF NOT EXISTS dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    category_id INT NOT NULL,
    name_zh VARCHAR(100) NOT NULL COMMENT '菜品名(华)',
    name_en VARCHAR(100) NOT NULL COMMENT '菜品名(英)',
    name_ms VARCHAR(100) NOT NULL COMMENT '菜品名(马来)',
    description_zh VARCHAR(500) DEFAULT NULL,
    description_en VARCHAR(500) DEFAULT NULL,
    description_ms VARCHAR(500) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL COMMENT '售价(RM)',
    image_url VARCHAR(255) DEFAULT NULL COMMENT '图片URL',
    spicy_level TINYINT DEFAULT 0 COMMENT '辣度0-5',
    is_hot TINYINT(1) DEFAULT 0 COMMENT '热门推荐',
    stock_daily INT DEFAULT 0 COMMENT '每日库存(管理员早晨输入)',
    stock_used_today INT DEFAULT 0 COMMENT '今日已售数量',
    stock_updated_date DATE DEFAULT NULL COMMENT '最后更新库存日期',
    is_available TINYINT(1) DEFAULT 1 COMMENT '上架状态',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜品表';

-- ============================================================
-- 菜品规格表 (dish_options)
-- ============================================================
CREATE TABLE IF NOT EXISTS dish_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dish_id INT NOT NULL,
    name_zh VARCHAR(50) NOT NULL COMMENT '规格名(华)',
    name_en VARCHAR(50) NOT NULL COMMENT '规格名(英)',
    name_ms VARCHAR(50) NOT NULL COMMENT '规格名(马来)',
    price_adjust DECIMAL(10,2) DEFAULT 0.00 COMMENT '价格调整',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜品规格表';

-- ============================================================
-- 会员表 (members)
-- ============================================================
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    phone VARCHAR(20) NOT NULL COMMENT '手机号(登录凭证)',
    name VARCHAR(100) DEFAULT NULL,
    points INT DEFAULT 0 COMMENT '可用积分',
    total_points_earned INT DEFAULT 0 COMMENT '累计获得积分',
    birthday DATE DEFAULT NULL COMMENT '生日',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_phone_store (phone, store_id),
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员表';

-- ============================================================
-- 积分变动日志表 (point_logs)
-- ============================================================
CREATE TABLE IF NOT EXISTS point_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    points INT NOT NULL COMMENT '变动值(正/负)',
    type ENUM('EARN','REDEEM','EXPIRE','ADMIN') NOT NULL COMMENT '积分类型',
    reference_type VARCHAR(50) DEFAULT NULL COMMENT '关联类型(order/promotion)',
    reference_id INT DEFAULT NULL COMMENT '关联ID',
    remark VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分变动日志';

-- ============================================================
-- 订单表 (orders)
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    order_number VARCHAR(20) NOT NULL COMMENT '取餐号(A001格式)',
    table_no VARCHAR(10) DEFAULT NULL COMMENT '桌号',
    member_id INT DEFAULT NULL COMMENT '会员ID(可空)',
    member_phone VARCHAR(20) DEFAULT NULL COMMENT '会员手机号',
    is_takeaway TINYINT(1) DEFAULT 0 COMMENT '自取标识',
    pickup_time DATETIME DEFAULT NULL COMMENT '预约自取时间',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '小计',
    discount_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '折扣金额',
    tax_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'SST税额',
    rounding_adjust DECIMAL(10,2) DEFAULT 0.00 COMMENT '四舍五入调整',
    net_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '实付金额',
    order_status ENUM('NEW','COOKING','READY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'NEW' COMMENT '订单状态',
    payment_method ENUM('CASH','TNG','GRABPAY','DUITNOW','QR') DEFAULT NULL COMMENT '支付方式',
    payment_status ENUM('PENDING','PAID','FAILED','REFUNDED') DEFAULT 'PENDING' COMMENT '支付状态',
    payment_transaction_id VARCHAR(100) DEFAULT NULL COMMENT '支付网关交易ID',
    remark VARCHAR(500) DEFAULT NULL COMMENT '备注',
    estimated_wait_minutes INT DEFAULT 15 COMMENT '预估等待分钟',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_store_status (store_id, order_status),
    INDEX idx_table_no (table_no),
    INDEX idx_order_number (order_number),
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';

-- ============================================================
-- 订单明细表 (order_items)
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    dish_id INT NOT NULL,
    dish_name_zh VARCHAR(100) NOT NULL COMMENT '下单时菜品名(冗余)',
    dish_name_en VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL COMMENT '单价',
    options_text VARCHAR(255) DEFAULT NULL COMMENT '所选规格(如:芝士酱)',
    subtotal DECIMAL(10,2) NOT NULL COMMENT '小计',
    remark VARCHAR(255) DEFAULT NULL COMMENT '备注(少糖少冰)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (dish_id) REFERENCES dishes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单明细表';

-- ============================================================
-- 促销活动表 (promotions)
-- ============================================================
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    name_zh VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    name_ms VARCHAR(100) NOT NULL,
    type ENUM('COMBO','SECOND_HALF','SHARE_COUPON','BIRTHDAY','UPSELL') NOT NULL COMMENT '促销类型',
    rule_json JSON NOT NULL COMMENT '规则数据(JSON)',
    start_date DATETIME NOT NULL COMMENT '开始时间',
    end_date DATETIME NOT NULL COMMENT '结束时间',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='促销活动表';

-- ============================================================
-- 优惠券表 (coupons)
-- ============================================================
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    member_id INT DEFAULT NULL COMMENT '领券会员',
    promotion_id INT DEFAULT NULL COMMENT '来源活动',
    code VARCHAR(50) DEFAULT NULL COMMENT '券码',
    type ENUM('FIXED_DISCOUNT','PERCENT_DISCOUNT') NOT NULL DEFAULT 'FIXED_DISCOUNT',
    value DECIMAL(10,2) NOT NULL COMMENT '减免金额/折扣比例',
    min_order DECIMAL(10,2) DEFAULT 0.00 COMMENT '最低消费',
    is_used TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    used_order_id INT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL COMMENT '过期时间',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (member_id) REFERENCES members(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';

-- ============================================================
-- 打印机配置表 (printers)
-- ============================================================
CREATE TABLE IF NOT EXISTS printers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    name VARCHAR(50) NOT NULL COMMENT '打印机名称',
    type ENUM('IP','USB') NOT NULL COMMENT '类型',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址(IP类型)',
    port INT DEFAULT 9100 COMMENT '端口',
    category_id INT DEFAULT NULL COMMENT '关联分类(NULL表示前台总单)',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打印机配置表';

-- ============================================================
-- 打印任务队列表 (print_jobs)
-- ============================================================
CREATE TABLE IF NOT EXISTS print_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    printer_id INT DEFAULT NULL,
    content TEXT COMMENT '打印内容',
    status ENUM('PENDING','PRINTING','SUCCESS','FAILED') DEFAULT 'PENDING',
    retry_count INT DEFAULT 0,
    error_message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='打印任务队列';

-- ============================================================
-- 系统配置表 (system_configs)
-- ============================================================
CREATE TABLE IF NOT EXISTS system_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1,
    config_key VARCHAR(50) NOT NULL,
    config_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config (store_id, config_key),
    FOREIGN KEY (store_id) REFERENCES stores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- ============================================================
-- 初始化数据
-- ============================================================

-- 默认门店
INSERT INTO stores (id, name, address, phone, tax_reg_no, sst_rate) VALUES
(1, 'ABC FOOD', 'PSK Seri Kembangan, Selangor', '012-3456789', 'ABC-2026-001', 6.00);

-- 默认管理员 (密码: admin123)
INSERT INTO users (username, password, display_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 'admin'),
('boss', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '老板', 'boss'),
('kitchen', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '后厨', 'kitchen');

-- 默认分类
INSERT INTO categories (store_id, name_zh, name_en, name_ms, sort_order) VALUES
(1, '特色推荐', 'Signature', 'Signature', 1),
(1, '西餐扒类', 'Western Grill', 'Grill Barat', 2),
(1, '小吃', 'Snacks', 'Makanan Ringan', 3),
(1, '饮品', 'Beverages', 'Minuman', 4),
(1, '甜品', 'Desserts', 'Pencuci Mulut', 5);

-- 默认系统配置
INSERT INTO system_configs (store_id, config_key, config_value) VALUES
(1, 'store_name', 'ABC FOOD'),
(1, 'store_address', 'PSK Seri Kembangan, Selangor'),
(1, 'store_phone', '012-3456789'),
(1, 'points_rate', '1.00'),
(1, 'points_redeem_rate', '100.00'),
(1, 'sst_rate', '6.00'),
(1, 'default_wait_minutes', '15'),
(1, 'business_hours_start', '10:00'),
(1, 'business_hours_end', '22:00');
