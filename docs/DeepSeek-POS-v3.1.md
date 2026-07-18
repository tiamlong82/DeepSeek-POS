# DeepSeek POS 智慧点餐系统

> 完整技术方案书（v3.1 终版 · 含全规格+桌贴+加料）
> 项目代号：DeepSeek POS
> 项目类型：餐饮扫码点餐系统（全定制开发）
> 编制日期：2026年7月18日
> 目标上线周期：10周

---

## 第一章：项目概述

### 1.1 项目背景

DeepSeek POS（原 ABC FOOD）位于 PSK Seri Kembangan，主营西式简餐。目前采用人工点餐模式，存在高峰期人力不足、后厨错单漏单、顾客复购率低、老板无法远程掌握经营数据等痛点。

### 1.2 系统目标

- 顾客扫码自助点餐 + 多支付方式，减少服务员介入
- 后厨自动分单打印，杜绝错单漏单
- 会员积分 + 多维度营销工具，提升复购率
- 老板实时营业大屏，远程掌握经营状况
- 全面支持菜品多规格配置（大中小/重量/酱料/辣度/温度/加料等）
- 热敏纸桌贴自助打印，低成本部署
- 多门店架构预留，支持未来扩张

### 1.3 系统边界

| 包含 | 不包含 |
|------|--------|
| 扫码点餐（堂食/自取） | e-Invoice 电子发票 |
| 后厨 IP 打印 + 前台 USB 打印 | 储值卡/预充值 |
| 热敏纸桌贴生成与打印 | 限时秒杀 |
| 会员积分 + 生日送券 | 全自动库存预警（半自动沽清）|
| 组合套餐/第二份半价/分享领券/加价购 | 震动叫号器（屏幕叫号）|
| 同桌 AA 拼单 | WiFi 信息（桌贴不含）|
| 三语切换（华/英/马来） | 外卖平台对接（可后期扩展）|
| 多规格配置（方案B固定价） | |
| Add On 加料模块（独立定价，多选不限） | |
| 多门店预留架构 | |

---

## 第二章：系统架构

### 2.1 整体架构

| 层级 | 组件 | 说明 |
|------|------|------|
| 客户端层 | H5/小程序/平板/PC | 多端覆盖 |
| 网关层 | Nginx + SSL + 限流 | 反向代理、安全防护 |
| 应用层 | Spring Boot | 订单/会员/库存/促销/打印/支付/规格/桌贴/加料 |
| 数据层 | MySQL + Redis + OSS | 持久化 + 缓存 + 存储 |
| 硬件层 | IP 打印机 + USB 打印机 | 分单打印 + 收据打印 + 桌贴打印 |

### 2.2 技术选型

| 技术 | 用途 |
|------|------|
| Java Spring Boot 3.2.x | 后端框架 |
| MySQL 8.0 | 数据库 |
| Redis 7.0 | 缓存 |
| H5 响应式 | 顾客端 |
| Vue 3 + Element Plus | 管理后台 |
| ESC/POS | 打印协议 |
| WebSocket | 实时推送 |

### 2.3 数据流转

```
顾客扫码 → 浏览菜单(Redis缓存) → 选规格 → 选加料 → 购物车
→ 提交订单 → 校验库存 → 计算价格 → 快照保存 → 扣库存
→ 生成取餐号 → 异步打印(IP分单+USB总单) → 支付 → 出餐
```

---

## 第三章：数据库设计

### 核心表

| 表名 | 说明 |
|------|------|
| stores | 门店（多门店预留） |
| categories | 菜品分类（三语） |
| dishes | 菜品（含库存/定价模式） |
| dish_spec_dimensions | 规格维度（酱料/辣度/重量/温度/甜度） |
| dish_spec_options | 规格选项（方案B固定价） |
| dish_addons | 加料/Add On（独立定价） |
| orders | 订单主表 |
| order_items | 订单明细（含规格+加料快照） |
| members | 会员 |
| point_logs | 积分变动日志 |
| promotions | 促销活动 |
| printers | 打印机配置 |
| users | 员工账号 |
| table_sticker_config | 桌贴配置 |

---

## 第四章：核心业务逻辑

### 价格计算

```
单品最终价 = dish_spec_options.price 
           + SUM(dish_spec_options.extra_price) 
           + SUM(dish_addons.price)
```

### 下单流程

1. 接收请求（桌号、菜品含规格ID+加料ID）
2. 计算单价（FIXED/SPEC/加料）
3. 校验库存
4. 计算总价（含SST 6%）
5. 扣减库存
6. 会员积分
7. 生成取餐号
8. 保存订单+快照
9. 异步打印

---

## 第五章：API 设计

| Method | Endpoint | 说明 |
|--------|----------|------|
| GET | /api/menu | 全菜单（含规格+加料） |
| GET | /api/dishes/{id} | 菜品详情 |
| GET | /api/dishes/{id}/specs | 菜品规格 |
| GET | /api/dishes/{id}/addons | 菜品加料 |
| POST | /api/orders | 创建订单 |
| GET | /api/orders/{orderNumber} | 订单查询 |
| POST | /api/admin/stickers/generate | 批量生成桌贴 |
| POST | /api/members/register | 会员注册 |
| GET | /api/promotions/active | 当前促销 |
| GET | /api/reports/daily | 日报表 |

---

## 第六章：打印系统

### 架构

```
订单 → 打印分发器 → 西餐档(IP) / 饮料档(IP) / 前台总单(USB)
```

### 打印内容模板

- 订单号、桌号、时间
- 菜品 + 规格标注（酱料: 芝士酱 +RM1）
- 加料标注（芒果雪花冰 +RM6.90）
- 金额明细（小计/折扣/SST/实付）
- 取餐号、预计等待时间

### 桌贴（热敏纸）

- 58mm / 80mm 两种规格
- 餐厅名 + 地址 + ASCII QR 码 + 桌号 + 扫码提示
- 裁切线分隔，批量打印

---

## 第七章：功能模块

### 顾客端

扫码登录 → 三语切换 → 菜单浏览 → 规格选择 → 加料选择 → 购物车 → 下单支付 → 预约自取 → 排队叫号 → AA拼单 → 订单追踪

### 商家后台

订单管理 → 多档口分单 → 自动打印 → 餐桌状态 → 每日库存 → 成本统计

### 营销与会员

消费积分 → 生日送券 → 组合套餐 → 分享领券 → 第二份半价

### 数据报表

实时营业大屏 → 时段销售分析 → 顾客画像 → 多门店预留

---

## 第八章：部署

### 服务器

- ECS: 2 vCPU / 4GB / 40GB SSD
- RDS: MySQL 8.0 / 2 vCPU / 4GB / 50GB
- Redis: 1GB
- 带宽: 5Mbps

### 网络拓扑

```
互联网 → 阿里云马来西亚区(Nginx → Spring Boot → MySQL/Redis/OSS) → 门店局域网(IP打印机 × N + USB打印机)
```

---

## 第九章：实施计划

| 阶段 | 时间 | 交付物 |
|------|:----:|--------|
| 需求与设计 | W1-W2 | UI 原型、ER 图、API 文档 |
| 核心开发 | W3-W6 | 点餐全流程 + 规格选择 + IP 打印 |
| 高阶集成 | W7-W9 | USB 打印 + 营销 + 桌贴 + 报表 |
| 部署与培训 | W10 | 生产部署 + 培训 |

---

## 附录：API 示例

### 菜品详情（含规格+加料）

```json
{
  "dishId": 101,
  "nameZh": "芝士鸡扒焗饭",
  "pricingMode": "SPEC",
  "basePrice": 14.90,
  "specDimensions": [
    {
      "nameZh": "酱料", "maxSelect": 1,
      "options": [
        {"valueZh": "蘑菇酱", "price": 0.00, "isDefault": true},
        {"valueZh": "芝士酱", "price": 0.00, "extraPrice": 1.00}
      ]
    },
    {
      "nameZh": "饭面", "maxSelect": 1,
      "options": [
        {"valueZh": "饭", "price": 0.00, "isDefault": true},
        {"valueZh": "意大利面", "price": 0.00}
      ]
    }
  ],
  "addons": [
    {"nameZh": "红豆雪花冰", "price": 7.90},
    {"nameZh": "芒果雪花冰", "price": 6.90}
  ]
}
```

### 创建订单

```json
{
  "tableNo": "9",
  "items": [{
    "dishId": 101, "quantity": 1,
    "optionIds": [3, 7],
    "addonIds": [2],
    "remark": "少辣"
  }],
  "paymentMethod": "TNG"
}
```

---

*本方案书仅供 DeepSeek POS 项目使用*
