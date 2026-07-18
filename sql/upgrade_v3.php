<?php
// DeepSeek POS — Add spec & addon tables per v3.0 spec
$p = new PDO('mysql:host=localhost;dbname=abc_food_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== Creating v3.0 tables ===\n\n";

// 1. Spec dimensions (酱料, 辣度, 重量, 温度, 甜度, 饭面)
$p->exec("CREATE TABLE IF NOT EXISTS `dish_spec_dimensions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT DEFAULT 1,
  `name_zh` VARCHAR(50) NOT NULL,
  `name_en` VARCHAR(50) NOT NULL,
  `name_my` VARCHAR(50) NOT NULL,
  `input_type` VARCHAR(20) DEFAULT 'BUTTON' COMMENT 'BUTTON/SELECT',
  `is_required` BOOLEAN DEFAULT TRUE,
  `max_select` INT DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✅ dish_spec_dimensions\n";

// 2. Spec options (蘑菇酱RM0, 芝士酱+RM1, 大份, 少糖...)
$p->exec("CREATE TABLE IF NOT EXISTS `dish_spec_options` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `dimension_id` INT NOT NULL,
  `dish_id` INT NOT NULL,
  `value_zh` VARCHAR(50) NOT NULL,
  `value_en` VARCHAR(50) NOT NULL,
  `value_my` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_default` BOOLEAN DEFAULT FALSE,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`dimension_id`) REFERENCES `dish_spec_dimensions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dish_id`) REFERENCES `dishes`(`id`) ON DELETE CASCADE,
  INDEX(`dish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✅ dish_spec_options\n";

// 3. Add-ons (加料, 独立定价)
$p->exec("CREATE TABLE IF NOT EXISTS `dish_addons` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT DEFAULT 1,
  `dish_id` INT NOT NULL,
  `name_zh` VARCHAR(50) NOT NULL,
  `name_en` VARCHAR(50) NOT NULL,
  `name_my` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_available` BOOLEAN DEFAULT TRUE,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`),
  FOREIGN KEY (`dish_id`) REFERENCES `dishes`(`id`) ON DELETE CASCADE,
  INDEX(`dish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✅ dish_addons\n";

// 4. Table sticker config
$p->exec("CREATE TABLE IF NOT EXISTS `table_sticker_config` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `store_id` INT DEFAULT 1,
  `restaurant_name` VARCHAR(100),
  `address` VARCHAR(255),
  `qrcode_base_url` VARCHAR(255),
  `paper_width` VARCHAR(10) DEFAULT '58mm',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✅ table_sticker_config\n";

// 5. Default sticker config
$p->exec("INSERT IGNORE INTO `table_sticker_config` (`store_id`, `restaurant_name`, `address`, `qrcode_base_url`, `paper_width`)
VALUES (1, 'ABC FOOD', 'PSK Seri Kembangan, Selangor', '/abc-food/index.php?table=', '58mm')");
echo "✅ Default sticker config\n";

// 6. Seed spec dimensions
$p->exec("INSERT IGNORE INTO `dish_spec_dimensions` (`id`, `name_zh`, `name_en`, `name_my`, `input_type`, `max_select`, `sort_order`) VALUES
(1, '酱料', 'Sauce', 'Sos', 'SELECT', 1, 1),
(2, '饭面', 'Rice/Noodles', 'Nasi/Mee', 'BUTTON', 1, 2),
(3, '辣度', 'Spiciness', 'Pedas', 'BUTTON', 1, 3),
(4, '重量', 'Weight', 'Berat', 'BUTTON', 1, 4),
(5, '温度', 'Temperature', 'Suhu', 'BUTTON', 1, 5),
(6, '甜度', 'Sweetness', 'Manis', 'BUTTON', 1, 6)");
echo "✅ Seed dimensions\n";

echo "\n=== All done! ===\n";
