<?php
// DeepSeek POS — Seed: add sample specs + addons to dishes
$p = new PDO('mysql:host=localhost;dbname=abc_food_db;charset=utf8mb4', 'root', '');

// Get dishes
$dishes = $p->query("SELECT id, name_zh FROM dishes")->fetchAll();

// Dimensions: 1=酱料, 2=饭面, 3=辣度, 4=重量, 5=温度, 6=甜度

foreach ($dishes as $d) {
    $did = $d['id'];
    $name = $d['name_zh'];

    // Chicken/Burgers → sauce + size
    if (strpos($name, '扒') !== false || strpos($name, '扒') !== false) {
        // Sauce options (dim 1)
        $p->exec("INSERT IGNORE INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES
            (1, $did, '蘑菇酱', 'Mushroom', 'Cendawan', 0, 1, 1),
            (1, $did, '黑椒酱', 'Black Pepper', 'Lada Hitam', 0, 0, 2),
            (1, $did, '芝士酱', 'Cheesy', 'Keju', 1.00, 0, 3),
            (1, $did, '番茄酱', 'Tomato', 'Tomato', 1.00, 0, 4)");
        // Rice/Noodles option (dim 2)
        $p->exec("INSERT IGNORE INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES
            (2, $did, '饭', 'Rice', 'Nasi', 0, 1, 1),
            (2, $did, '意面', 'Spaghetti', 'Spaghetti', 0, 0, 2)");
        echo "  ✅ $name → sauce + rice\n";
    }

    // Drinks → temperature + sweetness
    if (strpos($name, '汁') !== false || strpos($name, '茶') !== false || strpos($name, '饮') !== false) {
        $p->exec("INSERT IGNORE INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES
            (5, $did, '少冰', 'Less Ice', 'Kurang Ais', 0, 0, 1),
            (5, $did, '正常冰', 'Normal Ice', 'Ais Biasa', 0, 1, 2),
            (5, $did, '热', 'Hot', 'Panas', 0, 0, 3)");
        $p->exec("INSERT IGNORE INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES
            (6, $did, '无糖', 'No Sugar', 'Tanpa Gula', 0, 0, 1),
            (6, $did, '少糖', 'Less Sugar', 'Kurang Manis', 0, 0, 2),
            (6, $did, '正常糖', 'Normal', 'Biasa', 0, 1, 3)");
        echo "  ✅ $name → temp + sweetness\n";
    }

    // Desserts → addons
    if (strpos($name, '布丁') !== false || strpos($name, '甜品') !== false) {
        $p->exec("INSERT IGNORE INTO dish_addons (dish_id, name_zh, name_en, name_my, price, sort_order) VALUES
            ($did, '加冰淇淋', 'Add Ice Cream', 'Tambah Ais Krim', 3.50, 1),
            ($did, '加奶油', 'Add Cream', 'Tambah Krim', 2.00, 2)");
        echo "  ✅ $name → addons\n";
    }

    // All dishes get spicy option (dim 3)
    $p->exec("INSERT IGNORE INTO dish_spec_options (dimension_id, dish_id, value_zh, value_en, value_my, price, is_default, sort_order) VALUES
        (3, $did, '不辣', 'No Spicy', 'Tidak Pedas', 0, 1, 1),
        (3, $did, '微辣', 'Mild', 'Sedikit Pedas', 0, 0, 2),
        (3, $did, '中辣', 'Medium', 'Sederhana Pedas', 0, 0, 3)");
}

echo "\n=== Done seeding! ===\n";
