# 🛡️ GuYi Aegis Pro - 企业级验证管理系统

<p align="center">
  <a href="https://aegis.可爱.top/">
    <img src="https://img.shields.io/badge/Documentation-官方文档-blue.svg" alt="Documentation">
  </a>
  <img src="https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg" alt="PHP Version">
  <img src="https://img.shields.io/badge/Database-SQLite3-green.svg" alt="Database">
  <img src="https://img.shields.io/badge/License-Proprietary-red.svg" alt="License">
</p>

> **GuYi Aegis Pro** 是一款轻量级、高性能、无需配置复杂数据库的 PHP 验证管理系统。支持多应用接入、设备指纹绑定、卡密自动发卡与时长管理。内置精美的 Glassmorphism (毛玻璃) 风格 UI 与强大的后台管理面板。

---

## ✨ 核心特性

*   **⚡ 极速部署**: 基于 SQLite 架构，无需安装 MySQL，上传即用，自动初始化数据库。
*   **🏢 多租户/多应用管理**: 单个后台管理多个软件/脚本的验证，每个应用拥有独立的 App Key。
*   **🔐 安全防护**:
    *   **核心安全**: CSRF 令牌防御、XSS 过滤、SQL 注入防御 (PDO预处理)。
    *   **会话安全**: HMAC-SHA256 Cookie 签名，绑定 UserAgent 防止会话劫持。
    *   **前端防护**: 内置防调试机制（禁止右键、F12、控制台），保护验证页面。
    *   **API 防护**: 基于文件的 IP 速率限制 (Rate Limiting) 防止暴力破解。
*   **📱 设备管理**: 自动计算设备指纹（Device Hash），支持单设备绑定、后台强制解绑、自动解绑过期设备。
*   **💳 强大的卡密系统**:
    *   支持时/天/周/月/季/年卡多种类型。
    *   支持批量生成、导出 (TXT)、批量删除、**批量加时**。
    *   实时追踪卡密状态（待激活、使用中、已过期、被禁用）。
*   **📊 数据洞察**: 实时仪表盘，展示库存占比、活跃设备趋势及详细的操作审计日志。
*   **🔌 标准接口**: 提供标准的 JSON API，易于对接易语言、Python、Lua、C# 等客户端。

---

## 🚀 快速开始

### 1. 环境要求
*   **PHP 版本**: 7.4 或更高 (建议 8.0+)
*   **Web 服务器**: Nginx / Apache / OpenLiteSpeed
*   **必需扩展**: 
    *   `pdo_sqlite` (数据库支持)
    *   `gd` (验证码生成)
    *   `json` (API返回)

### 2. 安装部署

1.  **上传源码**: 将所有文件上传至网站根目录或子目录。
2.  **创建目录**: 确保服务器上存在以下目录结构（如果不存在请创建），并设置权限：
    ```bash
    /Verifyfile/   (存放 api.php 和 captcha.php)
    /backend/      (存放静态资源如 logo.png)
    /data/         (程序会自动创建此目录，需确保根目录有写入权限)
    设置权限: 给予项目根目录写入权限，以便程序自动创建 data 目录和 cards.db 数据库文件。
Linux: chmod -R 777 /www/wwwroot/你的网站目录/
安全配置:
打开 config.php。
找到 define('SYS_SECRET', '...');。
务必将默认字符串修改为一段随机的长字符串（这关系到你的登录安全）。
访问后台:
浏览器访问: http://你的域名/cards.php
默认账号: admin
默认密码: admin123
(登录后请立即在后台“系统设置”中修改密码)
📂 推荐目录结构
为了确保代码正常运行，请保持以下文件结构：

<TEXT>
/
├── backend/            # [需新建] 存放 logo.png 等图片
├── data/               # [自动生成] 存放 cards.db 和 .htaccess
├── Verifyfile/         # [需新建] API 和 验证码目录
│   ├── api.php         # 将源码中的 api.php 放入此目录
│   └── captcha.php     # 将源码中的 captcha.php 放入此目录
├── auth_check.php      # 核心权限检查
├── cards.php           # 后台管理主程序
├── config.php          # 配置文件
├── database.php        # 数据库操作类
├── index.php           # 用户前台验证页面
├── verify.php          # 前端验证处理接口
└── index1.php          # [需自建] 用户验证通过后的跳转目标页
🔌 API 接口文档
用于客户端软件（如易语言、Python脚本）对接验证系统。

核心验证接口
接口地址: http://你的域名/Verifyfile/api.php
请求方式: POST (推荐) 或 GET
返回格式: JSON
1. 请求参数
参数名	类型	必填	说明
card (或 key)	String	✅	用户输入的卡密
device	String	❌	设备机器码（不填则由服务器根据IP+UA生成，建议客户端传入）
app_key	String	❌	应用接入 Key（在后台"应用接入"菜单获取，不填则验证通用卡）
2. JSON 响应示例
验证成功 (HTTP 200):

<JSON>
{
    "code": 200,
    "msg": "OK",
    "data": {
        "status": "active",
        "expire_time": "2023-12-31 23:59:59",
        "remaining_seconds": 86400,
        "device_id": "a1b2c3d4..."
    }
}
验证失败 (HTTP 403/429):

<JSON>
{
    "code": 403,
    "msg": "卡密已过期 / 无效的卡密代码",
    "data": null
}
⚠️ 安全与维护建议
隐藏后台: 建议将 cards.php 重命名为不规则文件名（例如 manage_992x.php），以防被扫描。
HTTPS: 生产环境强烈建议开启 HTTPS，防止卡密在传输中被拦截。
定期备份: 只需下载 /data/cards.db 文件即可完成全站数据备份。
防盗链: 系统会自动在 /data/ 目录下生成 .htaccess 防止数据库被直接下载（仅限 Apache/LiteSpeed），Nginx 用户请在配置中手动禁止访问 .db 文件。
🤝 社区与支持
官方文档: https://aegis.可爱.top/
交流群: 562807728
Copyright © 2026 GuYi Aegis Pro. All Rights Reserved.
