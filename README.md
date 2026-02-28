# 🛡️ GuYi Network Verification System — V4 Remastered

> **📚 官方文档**: [**https://guyiovo.github.io/GuYi-Access-wed/**](https://guyiovo.github.io/GuYi-Access-wed/)
> *(提示：V4 Remastered 架构已全面升级，建议优先查阅官方文档获取最新对接示例)*

<p align="left">
  <a href="https://guyiovo.github.io/GuYi-Access-wed/">
    <img src="https://img.shields.io/badge/Version-V4_Remastered-6366f1.svg?style=flat-square&logo=github&logoColor=white" alt="Version">
  </a>
  <img src="https://img.shields.io/badge/Database-MySQL_InnoDB-007AFF.svg?style=flat-square&logo=mysql&logoColor=white" alt="Database">
  <img src="https://img.shields.io/badge/Architecture-API_Driven-34C759.svg?style=flat-square&logo=serverless&logoColor=white" alt="Architecture">
  <img src="https://img.shields.io/badge/PHP-7.4+-777BB4.svg?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-Proprietary-FF3B30.svg?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/QQ群-1077643184-12B7F5.svg?style=flat-square&logo=tencentqq&logoColor=white" alt="QQ Group">
</p>

---

## 📖 产品概述

**GuYi Network Verification System V4 Remastered** 是一套专为独立开发者与中小微企业打造的
**高并发、低延迟** 软件授权分发解决方案。

V4 Remastered 在保留完整后台管理界面的同时，全面采用 **MySQL InnoDB** 高性能数据库内核，
配合 **App Key 多租户隔离**、**云变量引擎**、**Award-Grade 后台控制台（Glass UI）**，
为桌面软件、APP、插件等客户端提供毫秒级鉴权服务。

---

## 💎 核心特性 (Core Features)

### 🔐 1. 金融级安全防护体系

构建了从网络层到应用层的多维防御矩阵，确保业务数据零泄露。

- **全局安全响应头**  
  `config.php` 自动注入 `X-Frame-Options`（防点击劫持）、`X-XSS-Protection`、
  `X-Content-Type-Options`（防 MIME 嗅探）等HTTP 安全基线头。

- **CSRF 全链路防护**  
  后台所有 POST 操作（`cards.php`）均启用 `hash_equals` Token 校验，
  配合 PDO 预处理语句彻底阻断 SQL 注入与越权操作。

- **IP绑定 Session锁定**  
  管理员 Session绑定客户端 IP，切换网络后强制重新登录，防止 Session 劫持。

- **HMAC-SHA256 信任设备Cookie**  
  `login.php` 实现基于 `HMAC-SHA256` 签名的 `admin_trust` Cookie自动登录机制。
  Cookie 同时绑定 `User-Agent` 指纹与管理员密码哈希，密码修改后旧 Cookie 立即作废。

- **图形验证码 + 登录延迟防爆破**  
  非信任设备登录需通过 `Verifyfile/captcha.php` 图形验证码校验，
  密码错误后触发 `usleep(500000)` 延迟，有效抵御暴力破解。

- **API 双维度速率限制**  
  `Verifyfile/api.php` 对 **IP 维度**（60次/分）与 **AppKey 维度**（180次/分）
  分别实施速率限制，使用文件锁（`LOCK_EX`）保证计数原子性，防止竞态绕过。

- **脏读防护**  
  `verifyCard` 在命中 `active_devices` 缓存后，回查 `cards` 表验证卡密真实状态，
  防止卡密被封禁/删除后仍能通过缓存绕过验证的安全漏洞。

---

###🏢 2. 多租户 SaaS 隔离架构

一套系统即可支撑庞大软件矩阵，实现集中化管理与数据强隔离。

- **App Key 租户隔离**  
  每个应用拥有独立的 64 字节随机 `App Key`（`bin2hex(random_bytes(32))`），
  验证核心 `verifyCard` 强制绑定 `App Key`，卡密数据、设备绑定、云变量各应用间物理隔离。

- **应用状态管控**  
  后台支持一键启用/禁用应用，禁用状态下API 请求将被拒绝，实现细粒度管控。

- **实时封禁控制台**  
  支持毫秒级卡密封禁（`ban_card`）与解封（`unban_card`），
  封禁操作同步清理 `active_devices`，强制将已登录设备踢下线。

- **纯 API 鉴权模式**  
  `Verifyfile/api.php` 是客户端交互的唯一入口，支持：
  - **场景 A**：仅传`app_key` →获取应用公开变量
  - **场景 B**：传 `card_code + app_key + device_hash` → 完整卡密验证

---

### ☁️ 3. 云变量引擎 2.0

无需更新客户端软件，动态下发配置与资源。

- **公开变量 (Public)**  
  客户端无需卡密，仅凭 `App Key` 即可获取（适用于公告、版本号、更新链接等）。

- **私有变量 (Private)**  
  卡密验证通过后随有效期信息一并下发（适用于 VIP 资源链接、专用配置等敏感内容）。

- **后台可视化管理**  
  `cards.php` →应用管理 → 变量管理，支持变量的增删改查与公开/私有权限切换。

- **统一响应结构**
  ```json
  {
    "code": 200,
    "msg": "OK",
    "data": {
      "expire_time": "2025-12-31 23:59:59",
      "variables": {
        "download_url": "https://example.com/vip.zip",
        "notice": "欢迎使用"
      }
    }
  }
  ```

---

### ⚡ 4. 高并发 MySQL InnoDB 内核

基于 MySQL InnoDB 的深度优化，保证数据操作的原子性与高可用性。

- **全表InnoDB 引擎**  
  所有数据表均使用 `ENGINE=InnoDB CHARSET=utf8mb4`，
  支持行级锁与高并发事务，彻底告别 SQLite 的写锁瓶颈。

- **事务一致性保障**  
  批量删除、批量解绑、批量加时等操作均使用数据库事务（`beginTransaction / commit / rollBack`），
  保证高并发场景下 `cards` 与 `active_devices` 两表数据的强一致性。

- **密码学安全随机卡密**  
  卡密生成使用 `random_int()` 代替 `mt_rand()`，
  字符集为去歧义字母数字集（排除 `0/O/1/I/L` 等易混淆字符）。

- **智能过期设备清理**  
  每次验证请求触发 `cleanupExpiredDevices()`，
  自动将`active_devices` 中过期记录标记为失效，保持数据库整洁。

- **索引优化**  
  `cards` 表建立 `idx_card_app(app_id)` 与 `idx_card_hash(device_hash)` 联合索引，
  `active_devices` 建立 `idx_dev_expire(expire_time)` 索引，确保高并发查询性能。

---

### 🎨 5. Award-Grade 后台控制台

全新Glass Morphism UI，兼顾颜值与功能。

- **Glass UI Design System**  
  基于 CSS 变量构建的完整设计系统，支持毛玻璃效果、背景虚化（可配置）、
  动态粒子、侧边栏折叠/展开（含 `Ctrl+B` 快捷键）。

- **响应式双端适配**  
  PC 端侧边栏可折叠，移动端自动切换抽屉式导航，
  背景图支持 PC/移动端独立配置。

- **仪表盘实时监控**  
  统计卡总量、活跃设备数、接入应用数、待售库存，
  配合 Chart.js 应用卡密类型分布饼图与库存占比进度条。

- **卡密库存管理**  
  支持多维筛选（应用/状态/关键词搜索）、分页浏览、
  批量导出TXT、批量解绑、批量加时、批量删除。

- **全局配置面板**  
  网站标题、管理员用户名、密码、Favicon、后台头像、
  PC/移动端背景图、背景模糊开关均可在线修改，支持图片上传。

---

###👨‍💻 6. 极致开发者体验 (DX)

- **RESTful JSON API**  
  `Verifyfile/api.php` 提供标准 `code / msg / data` 三段式响应结构，
  支持 POST / JSON Body / GET三种传参方式（POST 优先级最高）。

- **多语言调用示例**  
  官方文档（[guyiovo.github.io/GuYi-Access-wed](https://guyiovo.github.io/GuYi-Access-wed/)）提供
  Python、Java、C#、Go、Node.js、EPL等 10+ 语言的 Copy-Paste Ready 示例代码。

- **一键安装向导**  
  `install.php` 三步完成部署：环境检测 → 数据库配置 → 自动生成 `config.php`，
  安装后自动删除安装文件，消除安全隐患。

- **标准生产环境支持**  
  Nginx / Apache + **PHP 7.4+** + **MySQL 5.7 / 8.0**，开箱即用。

---

## 📂 目录结构

```text
/ (Web Root)
├── Verifyfile/
│   ├── api.php           # [核心] 客户端鉴权 API（唯一对外接口）
│   └── captcha.php       # 后台登录图形验证码生成
├── backend/
│   └── logo.png          # 系统默认 Logo
├── assets/
│   ├── css/
│   │   └── all.min.css   # Font Awesome 图标库
│   └── js/
│       └── chart.js      # Chart.js 图表库
├── uploads/              # 用户上传的图片资源（自动创建）
├── cards.php             # 后台管理控制台（管理员入口）
├── login.php             # 管理员登录页
├── config.php            # 核心配置文件（安装后自动生成）
├── database.php          # 数据库操作核心类（PDO MySQL封装）
├── index.php             # 根目录访问伪 404 保护
└── install.php           # 系统安装向导（安装后请立即删除）
```

---

## 🗄️ 数据表结构

| 表名 | 用途 |
|---|---|
| `applications` | 应用注册表（含App Key） |
| `app_variables` | 云端变量键值存储 |
| `cards` | 卡密主表（状态/设备/有效期） |
| `active_devices` | 活跃设备缓存（加速验证） |
| `usage_logs` | 鉴权审计日志 |
| `admin` | 管理员账号（单账户） |
| `system_settings` | 全局系统配置 KV 表 |

---

## 🚀 快速部署

```bash
# 1. 上传所有文件到 Web根目录

# 2. 确保以下目录/文件可写
chmod 755 /path/to/webroot
chmod 644 config.php   # 或直接给目录写权限

# 3.浏览器访问安装向导
https://your-domain.com/install.php

# 4. 按向导完成三步安装后，访问后台
https://your-domain.com/cards.php

# 默认管理员账号: GuYi
# 默认管理员密码: admin123（首次登录后请立即修改）
```

---

## 🔌 API 快速对接

**接口地址：**
```
POST https://your-domain.com/Verifyfile/api.php
```

**卡密验证请求：**
```json
{
  "app_key":"您的 AppKey（64位十六进制）",
  "card_code":   "用户输入的卡密",
  "device_hash": "设备唯一标识（建议取硬件信息哈希）"
}
```

**验证成功响应：**
```json
{
  "code": 200,
  "msg":  "OK",
  "data": {
    "expire_time": "2025-12-31 23:59:59",
    "variables": {
      "notice":"欢迎使用 VIP 版本",
      "download_url": "https://example.com/vip_resource.zip"
    }
  }
}
```

**验证失败响应：**
```json
{
  "code": 403,
  "msg":  "卡密已绑定其他设备",
  "data": null
}
```

**错误码说明：**

| code | 含义 |
|---|---|
| `200` | 验证通过 |
| `400` | 参数缺失或格式非法 |
| `403` | 鉴权失败（卡密无效/已封禁/设备不符等） |
| `429` | 请求过于频繁（触发速率限制） |
| `500` | 服务器内部错误 |

---

## ⚠️ 安全注意事项

1. **安装完成后立即删除 `install.php`**，否则任何人可重置数据库配置
2. **及时修改默认密码** `admin123`，首次登录后立即前往「全局配置」更新
3. **妥善保管 `config.php`**，该文件包含数据库凭据，禁止公开访问
4. **建议启用 HTTPS**，防止API 通信过程中的中间人攻击
5. **`App Key` 请勿明文硬编码**在客户端可反编译的位置，建议做混淆处理

---

##📋 环境要求

| 组件 | 最低要求 | 推荐版本 |
|---|---|---|
| PHP | 7.4+ | 8.1+ |
| MySQL | 5.7+ | 8.0+ |
| PDO_MySQL | 必须启用 | — |
| Web Server | Nginx / Apache | Nginx 1.20+ |
| 目录权限 | 根目录可写 | 755 |

---

## 📞 联系与支持

- **官方文档**: [https://guyiovo.github.io/GuYi-Access-wed/](https://guyiovo.github.io/GuYi-Access-wed/)
- **交流 QQ 群**: `1077643184`

---

<p align="center">
  Copyright © 2025 GuYi Network Verification System V4 Remastered. All Rights Reserved.
</p>
