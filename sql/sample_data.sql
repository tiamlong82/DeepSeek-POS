USE abc_food_db;

INSERT INTO dishes (store_id, category_id, name_zh, name_en, name_ms, price, spicy_level, is_hot, stock_daily, stock_used_today, stock_updated_date, is_available, sort_order) VALUES
(1, 2, '海南鸡扒', 'Hainan Chicken Chop', 'Chicken Chop Hainan', 13.90, 0, 1, 20, 0, CURDATE(), 1, 1),
(1, 2, '黑椒牛扒', 'Black Pepper Beef Steak', 'Steak Lada Hitam', 25.90, 2, 1, 15, 0, CURDATE(), 1, 2),
(1, 3, '芝士薯条', 'Cheese Fries', 'Kentang Goreng Keju', 8.90, 0, 0, 30, 0, CURDATE(), 1, 3),
(1, 4, '特大苹果汁', 'Large Apple Juice', 'Jus Epal Besar', 9.90, 0, 1, 25, 0, CURDATE(), 1, 4),
(1, 4, '冰柠檬茶', 'Iced Lemon Tea', 'Teh Lemon Ais', 5.90, 0, 0, 30, 0, CURDATE(), 1, 5),
(1, 5, '芒果布丁', 'Mango Pudding', 'Puding Mangga', 6.90, 0, 0, 20, 0, CURDATE(), 1, 6),
(1, 1, '招牌拼盘', 'Signature Platter', 'Platter Signature', 18.90, 1, 1, 10, 0, CURDATE(), 1, 7),
(1, 3, '蛋饭', 'Egg Rice', 'Nasi Telur', 3.80, 0, 0, 50, 0, CURDATE(), 1, 8);
