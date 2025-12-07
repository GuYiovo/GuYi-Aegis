# 🛠️ GuYi Aegis Pro (Ent) - 开发技术文档

> **版本**: V6.0 | **架构**: Native PHP + SQLite | **类型**: 企业级授权管理系统

本文档旨在帮助开发者、维护者理解系统的底层架构、核心逻辑流、数据库设计及安全机制。

---

## 1. 技术架构概览

本系统采用 **无框架 (Framework-less)** 设计，基于原生 PHP 开发，以确保最小的资源占用和最简单的部署要求（Copy & Run）。

- **设计模式**: 简化的 MVC (Model-View-Controller)
- **核心语言**: PHP 7.4+
- **数据存储**: SQLite 3 (文件型数据库，零配置)
- **前端技术**: HTML5, CSS3 (Glassmorphism 风格), 原生 JavaScript (Fetch API)
- **安全机制**: PDO 预处理, HMAC Cookie 签名, CSRF Token, IP 限流

---

## 2. 核心文件职责

| 文件路径 | 架构角色 | 职责描述 |
| :--- | :--- | :--- |
| `database.php` | **Model** | **核心逻辑层**。封装所有 SQL 操作，处理数据库初始化、表迁移、验证逻辑、激活逻辑及日志记录。 |
| `cards.php` | **Controller/View** | **管理后台**。处理管理员登录（Cookie签名验证）、路由分发、业务操作（增删改查）及 HTML 渲染。 |
| `api.php` | **API Interface** | **对外接口**。处理客户端请求，包含 IP 限流逻辑，返回标准 JSON 数据。 |
| `config.php` | **Config** | **配置层**。定义系统常量（密钥、路径、卡类型时长）及安全响应头。 |
| `verify.php` | **Ajax Handler** | **前端控制器**。处理网页版 `index.php` 的验证请求，逻辑与 API 类似但服务于 Session 环境。 |
| `auth_check.php` | **Middleware** | **中间件**。用于受保护页面，检查 Session 有效性、设备一致性及卡密过期状态。 |

---

## 3. 数据库设计 (Schema)

系统启动时，`Database::__construct()` 会自动检查并创建/更新以下表结构。

### 3.1. `applications` (租户表)
用于多应用隔离支持。
- `id`: INTEGER PK
- `app_name`: VARCHAR (唯一，应用名称)
- `app_key`: VARCHAR (接口通讯密钥，自动生成)
- `status`: INTEGER (1=正常, 0=禁用)

### 3.2. `cards` (卡密核心表)
- `id`: INTEGER PK
- `card_code`: VARCHAR (唯一卡密)
- `app_id`: INTEGER (关联 applications.id, **0 代表通用卡**)
- `card_type`: VARCHAR (hour/day/week/month...)
- `status`: INTEGER (0=未激活, 1=已激活)
- `device_hash`: VARCHAR (绑定的机器码/指纹)
- `expire_time`: DATETIME (过期时间)
- `create_time`: DATETIME

### 3.3. `active_devices` (在线设备表)
用于缓存和快速鉴权，减少主表查询压力。
- `device_hash`: VARCHAR (机器码)
- `card_code`: VARCHAR
- `app_id`: INTEGER
- `expire_time`: DATETIME

### 3.4. `usage_logs` (审计日志)
- 记录请求时间、IP、来源应用、卡密、机器码及验证结果。

---

## 4. 核心业务逻辑流程

### 4.1. 卡密验证与激活 (`verifyCard` 方法)
位于 `database.php`，是系统的灵魂逻辑：

1.  **应用鉴权**: 若请求包含 `app_key`，校验应用是否存在且状态为开启。
2.  **在线缓存检查**: 查询 `active_devices` 表。若设备存在且未过期，直接返回成功（Success）。
3.  **卡库查询**:
    - **不存在**: 返回错误。
    - **已激活 (Status=1)**:
        - 校验是否过期。
        - 校验 `device_hash` 是否匹配当前请求。
        - 若匹配且未过期 -> 验证通过，刷新在线表。
    - **未激活 (Status=0)**:
        - 根据 `config.php` 中的 `CARD_TYPES` 计算过期时间。
        - **开启事务 (Transaction)**。
        - 更新卡密：状态设为1，写入机器码，写入过期时间。
        - 写入在线表。
        - **提交事务** -> 激活成功。

### 4.2. 后台安全登录
位于 `cards.php`：

1.  **Cookie 强签名**: 登录成功后，生成包含 `过期时间|UA哈希` 的 Payload，并使用 `SYS_SECRET` 进行 HMAC-SHA256 签名写入 Cookie。
2.  **验证码机制**: 未通过受信验证的设备（无有效Cookie）强制输入验证码 (`captcha.php`)。
3.  **CSRF 防护**: 所有写操作（POST）均校验 Session 中的 `csrf_token`。

---

## 5. API 内部开发规范

若需修改 `Verifyfile/api.php`，请遵循：

### 5.1. 输入处理
兼容多种输入方式，优先级如下：
```php
$data = json_decode(file_get_contents('php://input'), true); // JSON Raw
$param = $data['key'] ?? $_POST['key'] ?? $_GET['key'];
