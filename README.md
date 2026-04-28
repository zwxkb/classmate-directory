<div align="center">

# ✦ 那些年 · 同学录

**一个温暖的同学录系统，为毕业后的班级联络而生**

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

[在线演示](#) · [安装指南](#安装) · [功能特性](#功能特性) · [截图](#截图)

</div>

---

## ✨ 功能特性

### 📖 同学档案
- 同学个人信息管理（头像、格言、简介、联系方式）
- 按姓名、城市实时搜索
- 联系方式隐私控制（仅同学可见）
- 管理员审核机制

### 📸 回忆墙
- 班级照片时间线展示
- 日期筛选
- 支持批量上传管理

### 💌 留言板
- 轻量化留言互动
- 后台审核过滤
- 时间排序展示

### 🔐 安全特性
| 特性 | 说明 |
|------|------|
| CSRF 防护 | Token 轮转机制，防止跨站请求伪造 |
| SQL 注入防护 | PDO 参数化查询 + 标识符转义 |
| XSS 防护 | 全局输出转义 + CSP 安全策略 |
| 会话安全 | HttpOnly / Secure / SameSite / 会话固定攻击防护 |
| 登录保护 | IP 频率限制（5 次失败锁定 5 分钟） |
| 空闲超时 | 1 小时无操作自动登出 |
| 安装安全 | 文件锁互斥防并发 + 安装完自动删除脚本 |
| 响应头 | X-Content-Type-Options / X-Frame-Options / Referrer-Policy |

### 🛠 管理后台
- 仪表盘数据统计
- 同学档案 CRUD + 审核排序
- 回忆照片管理
- 留言审核
- 站点设置（校名/班级/届/封面/口号）

---

## 📦 安装

### 环境要求

- PHP 7.4+（推荐 8.0+）
- MySQL 5.7+ / MariaDB 10.3+
- PDO + PDO_mysql 扩展
- GD 图形库（图片上传需要）
- Web 服务器（Apache / Nginx）

### 安装步骤

**1. 下载项目**

```bash
git clone https://github.com/你的用户名/classmate-directory.git
cd classmate-directory
```

**2. 配置 Web 服务器**

将项目目录指向 Web 根目录，确保 `Apache` 启用了 `mod_rewrite`。

Nginx 参考配置：
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/classmate-directory;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git) {
        deny all;
    }
}
```

**3. 设置目录权限**

```bash
chmod 755 .
chmod 755 uploads/ uploads/photos/ uploads/avatars/
```

**4. 运行安装向导**

浏览器访问 `http://your-domain.com/install.php`，按步骤完成：

1. ✅ **环境检测** — 自动检查 PHP 版本和扩展
2. 🗄️ **数据库配置** — 填写 MySQL 连接信息
3. 👤 **管理员账号** — 设置后台登录密码（至少 8 位，含大小写字母和数字）
4. 🚀 **确认安装** — 自动建表和写入配置

**5. 安装完成**

- 访问首页：`http://your-domain.com/`
- 管理后台：`http://your-domain.com/admin/`
- ⚠️ 安装完成后请删除 `install.php`

---

## 📁 项目结构

```
classmate-directory/
├── index.php              # 首页
├── students.php           # 同学档案页
├── memories.php           # 回忆墙
├── messages.php           # 留言板
├── submit.php             # 提交档案
├── install.php            # 安装向导（安装后删除）
├── config.example.php     # 配置示例
├── .htaccess              # Apache 安全配置
├── .gitignore
├── README.md
├── assets/
│   ├── style.css          # 样式表（复古暖色调）
│   └── app.js             # 前端交互
├── includes/
│   ├── db.php             # 数据库连接（PDO 单例 + 自动迁移）
│   ├── auth.php           # 认证函数
│   ├── csrf.php           # CSRF 防护
│   └── functions.php      # 公共函数
├── api/
│   ├── search.php         # 同学搜索 API
│   ├── student.php        # 同学详情 API
│   ├── submit.php         # 档案提交 API
│   └── message.php        # 留言 API
├── admin/
│   ├── index.php          # 管理仪表盘
│   ├── login.php          # 管理登录
│   ├── logout.php         # 登出
│   ├── password.php       # 修改密码
│   ├── sidebar.php        # 侧边栏组件
│   ├── students.php       # 同学管理
│   ├── memories.php       # 回忆管理
│   ├── messages.php       # 留言管理
│   └── settings.php       # 站点设置
└── uploads/
    ├── photos/            # 回忆照片
    └── avatars/           # 同学头像
```

---

## 🎨 设计风格

采用**复古暖色调**设计语言：

- 🎨 主色调：棕色系（`#5D4037` / `#E8913A`）
- 📝 字体：思源宋体 + 马善政毛笔体 + 思源黑体
- 🖼️ 复古相框效果 + 胶片风格
- 📱 完全响应式，适配手机 / 平板 / 桌面
- ✨ 温暖怀旧氛围，承载班级记忆

---

## 🔧 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 7.4+ (原生，无框架) |
| 数据库 | MySQL 5.7+ (InnoDB, utf8mb4) |
| 前端 | 原生 HTML5 / CSS3 / JavaScript |
| 字体 | Google Fonts (Noto Serif SC / Noto Sans SC / Ma Shan Zheng) |
| 安全 | CSRF Token / PDO Prepared Statements / CSP Headers |

---

## 📝 更新日志

### v1.0.0
- 🎉 首次发布
- ✅ 同学档案管理（搜索 / 详情 / 隐私控制）
- ✅ 回忆墙（时间线展示 / 日期筛选）
- ✅ 留言板（发布 / 审核）
- ✅ 管理后台（仪表盘 / CRUD / 设置）
- ✅ 安装向导（环境检测 / 自动建表）
- ✅ 完整安全防护

---

## 📄 开源协议

[MIT License](LICENSE)

---

<div align="center">

**时光不老，我们不散。** ✦

</div>
