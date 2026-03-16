<div align="center">

# 🛡️ GuYi Aegis (企业版架构)
**极致详细的开源软件卡密授权与验证基建引擎**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![Security](https://img.shields.io/badge/Security-AES--256--GCM-success.svg)]()
[![Languages](https://img.shields.io/badge/Support-30%2B_Languages-ff69b4.svg)]()
[![Chat](https://img.shields.io/badge/QQ群-1077643184-0088ff.svg)](https://qm.qq.com/q/X3suYdjWAA)

无论您使用 **C++** 编写原生外挂，使用 **Flutter** 开发多端应用，还是用 **易语言** 编写小工具。<br>
GuYi Aegis 都为您准备了极其详尽的开箱即用对接源码。

<br>

🌐 **[访问官方网站与 API 文档](https://guyiovo.github.io/GuYi-Access-wed/)**

</div>

---

## 📋 目录

- [✨ 核心特性](#-核心特性)
- [🚀 快速开始](#-快速开始)
- [💻 支持的生态矩阵](#-支持的生态矩阵)
- [💬 社区与支持](#-社区与支持)
- [📄 开源协议](#-开源协议)

## ✨ 核心特性

- 🔒 **军事级通信加密**：全栈标配 `AES-256-GCM` 认证加密，彻底杜绝中间人抓包篡改（如伪造到期时间）。
- 🌍 **全语言制霸**：提供 C/C++、Go、Rust、C#、Java、Python、Node.js、易语言、PHP、Flutter、Vue 等 30+ 主流与冷门语言的现成对接代码。
- ⚡ **极简高效 API**：单端点（Single Endpoint）设计，一个 POST 请求搞定验证、激活、绑定与数据拉取。
- 🛡️ **企业级高可用**：内置请求并发限制（防 CC/高频防刷）、云端心跳保活、硬件特征（设备码）绑定与离线容错机制。

## 🚀 快速开始

### 1. 部署与后台

请访问我们的 [官方网站](https://guyiovo.github.io/GuYi-Access-wed/) 进入 `后台登录` 模块，获取您的应用专属 `64位 AppKey`。

> ⚠️ **安全警告**：AppKey 不仅用于应用识别，更是 AES-256-GCM 的解密密钥，请务必在您的客户端代码中进行混淆或 VMP 加壳保护！

### 2. 客户端对接

我们为所有常用语言提供了即插即用的加密通信代码。请前往 [官网 API 文档区](https://guyiovo.github.io/GuYi-Access-wed/#docs) 右侧的**代码演示面板**，选择您正在使用的编程语言，一键复制核心验证逻辑。

#### 接口概览

```http
POST /Verifyfile/api.php
Content-Type: application/json

{
  "app_key": "您的 64位十六进制密钥",
  "card_code": "用户输入的卡密",
  "device_hash": "可选的硬件机器码"
}
```

**响应示例：**

```json
{
  "status": "success",
  "message": "验证成功",
  "data": {
    "expiry": "2026-12-31",
    "features": ["premium", "unlimited"]
  }
}
```

## 💻 支持的生态矩阵

GuYi Aegis 的设计初衷是为了打破语言壁垒。目前文档已涵盖（但不限于）以下开发环境：

| 系统/原生层 | 后端/微服务 | 前端/移动端 | 脚本/辅助 |
|-------------|-------------|-------------|-----------|
| C / C++    | Go         | Flutter / Dart | 易语言   |
| Rust       | Node.js    | Vue.js / React | Python   |
| C# / .NET  | Java (Spring) | Swift (iOS) | Lua     |
| VB.NET     | PHP        | Kotlin (Android) | Shell   |

## 💬 社区与支持

遇到对接问题？需要定制化功能？或者想获取最新版本的更新推送？欢迎加入我们的技术生态交流群：

- **官方技术交流群**：1077643184
- **一键加群链接**：[点击这里加入 QQ 群](https://qm.qq.com/q/X3suYdjWAA)

## 📄 开源协议

本项目采用 MIT License 开源协议。

您可以自由地将 GuYi Aegis 用于个人或商业项目中。在使用过程中，保留原作者版权信息是对开源精神最大的支持。

---

**Made with ♥ for Developers by GuYi.**