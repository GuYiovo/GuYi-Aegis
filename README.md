<div align="center">

# 🛡️ Aegis Auth System

**企业级 PHP 卡密验证与授权系统**

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Documentation](https://img.shields.io/badge/Docs-Online-orange.svg)](http://aegis.可爱.top/)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)]()

[功能特性](#-功能特性) • [快速部署](#-快速部署) • [API文档](#-api-接口) • [在线文档](http://aegis.可爱.top/)

</div>

---

## 📖 项目简介

**Aegis** (意为“神盾”) 是一款轻量级但功能强大的软件授权验证系统。它专为中小型软件分发、会员订阅及卡密充值场景设计。系统采用原生 PHP 开发，零依赖架构，确保了极高的执行效率与便捷的部署体验。

👉 **完整开发文档与使用手册： [http://aegis.可爱.top/](http://aegis.可爱.top/)**

## ✨ 功能特性

- ⚡ **高性能架构**：基于原生 PHP 开发，无臃肿框架负担，毫秒级响应。
- 🔒 **金融级安全**：核心配置与业务逻辑分离，敏感数据加密存储，防 SQL 注入。
- 🔌 **RESTful API**：提供标准的 JSON 接口，支持 C#、Python、Lua、易语言等全语言接入。
- 📦 **开箱即用**：无需复杂的数据库安装（支持 SQLite/MySQL），上传即运行。
- 📝 **详细审计**：完整的卡密生成、激活、使用日志记录。

## 📂 目录结构

```text
Aegis-Auth/
├── api/                # 核心 API 接口目录
├── data/               # 数据库文件存储（已忽略）
├── config.sample.php   # 配置文件模板
├── config.php          # 实际配置文件（由用户创建，已忽略）
├── index.php           # 系统入口
├── LICENSE             # 授权协议
└── README.md           # 说明文档
