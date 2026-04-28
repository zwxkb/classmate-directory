<?php
/**
 * 安装向导
 * 首次访问自动检测，引导用户完成系统安装
 * 分步流程：环境检测 → 数据库配置 → 管理员账号 → 执行安装
 */

// ── 安装锁检查（原子操作）──
$lockFile = __DIR__ . '/install.lock';
if (file_exists(__DIR__ . '/config.php')) {
    header('Location: index.php');
    exit;
}
$lockHandle = @fopen($lockFile, 'c+');
if ($lockHandle !== false) {
    // 尝试获取非阻塞排他锁
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        // 已有安装在进行中或已完成
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        // config.php 不存在但锁被占用，可能是安装中断或 config.php 被误删
        if (!file_exists(__DIR__ . '/config.php')) {
            die('系统配置文件丢失，请联系管理员恢复 config.php');
        }
        header('Location: index.php');
        exit;
    }
    // 获取到锁，检查文件是否有内容（判断是否已安装完成）
    $lockContent = stream_get_contents($lockHandle);
    if (!empty(trim($lockContent))) {
        // 锁文件有内容，说明已安装完成
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        // config.php 不存在但 lock 有内容，config.php 被误删
        if (!file_exists(__DIR__ . '/config.php')) {
            die('系统配置文件丢失，请联系管理员恢复 config.php');
        }
        header('Location: index.php');
        exit;
    }
    // 锁文件为空，说明是安装过程中创建的占位，继续安装流程
} else {
    // 无法打开锁文件，检查是否已安装
    if (file_exists($lockFile) && filesize($lockFile) > 0) {
        if (!file_exists(__DIR__ . '/config.php')) {
            die('系统配置文件丢失，请联系管理员恢复 config.php');
        }
        header('Location: index.php');
        exit;
    }
    $lockHandle = null;
}

// Session 安全配置（与 includes/csrf.php 保持一致）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)$_SERVER['SERVER_PORT'] === 443) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// HTTP 安全头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// 安装向导为一次性脚本，允许内联脚本和事件处理器
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'");

// 生成安装向导 CSRF Token（独立 session）
if (empty($_SESSION['install_csrf_token'])) {
    $_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * 转义 SQL 标识符（反引号），防止 DDL 注入
 */
function escape_identifier($identifier) {
    return str_replace('`', '``', $identifier);
}

/**
 * 测试数据库连接
 */
function test_db_connection($host, $port, $user, $pass) {
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return ['success' => true, 'message' => '连接成功'];
    } catch (PDOException $e) {
        error_log('安装向导数据库连接失败: ' . $e->getMessage());
        return ['success' => false, 'message' => '连接失败，请检查数据库配置'];
    }
}

/**
 * 检查环境
 */
function check_environment() {
    $checks = [];
    $php_version = phpversion();
    $checks['php_version'] = [
        'label' => 'PHP 版本', 'value' => $php_version,
        'pass'  => version_compare($php_version, '7.4.0', '>='),
    ];
    $checks['pdo'] = [
        'label' => 'PDO 扩展',
        'value' => extension_loaded('pdo') ? '已安装' : '未安装',
        'pass'  => extension_loaded('pdo'),
    ];
    $checks['pdo_mysql'] = [
        'label' => 'PDO MySQL 扩展',
        'value' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
        'pass'  => extension_loaded('pdo_mysql'),
    ];
    $checks['mbstring'] = [
        'label' => 'MBString 扩展',
        'value' => extension_loaded('mbstring') ? '已安装' : '未安装',
        'pass'  => extension_loaded('mbstring'),
    ];
    $checks['gd'] = [
        'label' => 'GD 图形库',
        'value' => extension_loaded('gd') ? '已安装' : '未安装（图片上传需要）',
        'pass'  => extension_loaded('gd'),
        'required' => false,
    ];
    $checks['fileinfo'] = [
        'label' => 'Fileinfo 扩展',
        'value' => extension_loaded('fileinfo') ? '已安装' : '未安装（必须，文件上传安全依赖）',
        'pass'  => extension_loaded('fileinfo'),
        'required' => true,
    ];
    $upload_dir = __DIR__ . '/uploads';
    $writable = is_writable(__DIR__);
    if (!is_dir($upload_dir)) {
        $writable = @mkdir($upload_dir, 0755, true) || is_writable(__DIR__);
    }
    $checks['writable'] = [
        'label' => '目录写入权限',
        'value' => $writable ? '可写' : '不可写',
        'pass'  => $writable,
    ];
    $checks['config'] = [
        'label' => 'config.php',
        'value' => !file_exists(__DIR__ . '/config.php') ? '不存在（正常）' : '已存在（请先删除）',
        'pass'  => !file_exists(__DIR__ . '/config.php'),
    ];
    return $checks;
}

/**
 * 执行安装
 */
function do_install() {
    $db_config = $_SESSION['install_db'] ?? null;
    $admin_config = $_SESSION['install_admin'] ?? null;

    if (!$db_config || !$admin_config) {
        return ['success' => false, 'message' => '安装信息丢失，请重新开始安装。'];
    }

    // 安全转义标识符
    $db_name_safe = escape_identifier($db_config['name']);
    $prefix_safe  = escape_identifier($db_config['prefix']);

    // 连接数据库
    try {
        $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        error_log('安装-数据库连接失败: ' . $e->getMessage());
        return ['success' => false, 'message' => '数据库连接失败，请检查数据库配置'];
    }

    // 创建数据库（使用转义后的标识符）
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name_safe}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db_name_safe}`");
    } catch (PDOException $e) {
        error_log('安装-创建数据库失败: ' . $e->getMessage());
        return ['success' => false, 'message' => '创建数据库失败，请检查数据库名称和权限'];
    }

    // 建表 SQL（使用转义后的前缀）
    $sqls = [
        "CREATE TABLE IF NOT EXISTS `{$prefix_safe}users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `{$prefix_safe}students` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL,
            `avatar` VARCHAR(255) DEFAULT '',
            `motto` TEXT,
            `city` VARCHAR(100) DEFAULT '',
            `phone` VARCHAR(50) DEFAULT '',
            `email` VARCHAR(100) DEFAULT '',
            `wechat` VARCHAR(100) DEFAULT '',
            `bio` TEXT,
            `show_contact` TINYINT(1) NOT NULL DEFAULT 0,
            `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `{$prefix_safe}memories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `photo` VARCHAR(255) DEFAULT '',
            `memory_date` DATE,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `{$prefix_safe}messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_name` VARCHAR(50) NOT NULL,
            `content` TEXT NOT NULL,
            `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS `{$prefix_safe}settings` (
            `key` VARCHAR(100) PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($sqls as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('安装-建表失败: ' . $e->getMessage());
            return ['success' => false, 'message' => '建表失败，请检查数据库权限'];
        }
    }

    // 创建管理员（密码已在 Step 3 哈希）
    $hashed = $admin_config['password_hashed'];
    try {
        $stmt = $pdo->prepare("INSERT INTO `{$prefix_safe}users` (`username`, `password`) VALUES (:u, :p)");
        $stmt->execute([':u' => $admin_config['username'], ':p' => $hashed]);
    } catch (PDOException $e) {
        error_log('安装-创建管理员失败: ' . $e->getMessage());
        return ['success' => false, 'message' => '创建管理员失败，请重试'];
    }

    // 默认站点设置
    $defaults = [
        'site_name'       => '那些年 · 同学录',
        'school_name'     => '春晖中学',
        'class_name'      => '高三（2）班',
        'graduation_year' => '2010',
        'cover_photo'     => '',
        'slogan'          => '时光不老，我们不散。',
    ];
    $stmt = $pdo->prepare("INSERT INTO `{$prefix_safe}settings` (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    foreach ($defaults as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => $v]);
    }

    // 生成 config.php（使用 var_export 避免特殊字符问题）
    $config_content = "<?php\n/**\n * 同学录配置文件\n * 由安装向导自动生成\n */\n\nreturn [\n";
    $config_content .= "    'db_host'   => " . var_export($db_config['host'], true) . ",\n";
    $config_content .= "    'db_port'   => " . var_export($db_config['port'], true) . ",\n";
    $config_content .= "    'db_user'   => " . var_export($db_config['user'], true) . ",\n";
    $config_content .= "    'db_pass'   => " . var_export($db_config['pass'], true) . ",\n";
    $config_content .= "    'db_name'   => " . var_export($db_config['name'], true) . ",\n";
    $config_content .= "    'db_prefix' => " . var_export($db_config['prefix'], true) . ",\n";
    $config_content .= "];\n";

    if (file_put_contents(__DIR__ . '/config.php', $config_content) === false) {
        return ['success' => false, 'message' => '无法写入 config.php，请检查目录权限。'];
    }
    chmod(__DIR__ . '/config.php', 0644);

    // 写入安装锁文件内容并释放文件锁
    global $lockHandle;
    if ($lockHandle !== null) {
        ftruncate($lockHandle, 0);
        rewind($lockHandle);
        fwrite($lockHandle, date('Y-m-d H:i:s') . "\n");
        fflush($lockHandle);
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        $lockHandle = null;
    } else {
        file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s') . "\n");
        chmod(__DIR__ . '/install.lock', 0644);
    }

    // 创建上传目录
    foreach ([__DIR__ . '/uploads', __DIR__ . '/uploads/avatars', __DIR__ . '/uploads/photos'] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    // 清除安装 session 并销毁
    unset($_SESSION['install_db'], $_SESSION['install_admin'], $_SESSION['install_csrf_token']);
    session_destroy();

    // 尝试自动删除安装脚本（静默失败，权限不足时跳过）
    @unlink(__FILE__);

    return ['success' => true];
}

// ── 处理 AJAX 请求（数据库连接测试）──
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    // 验证 CSRF Token
    $token = $_POST['install_csrf_token'] ?? '';
    if (empty($_SESSION['install_csrf_token']) || !hash_equals($_SESSION['install_csrf_token'], $token)) {
        echo json_encode(['success' => false, 'message' => '安全验证失败，请刷新页面重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // 验证请求来源
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $parsed = parse_url($origin, PHP_URL_HOST);
    if ($parsed && $parsed !== $_SERVER['HTTP_HOST']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '非法请求来源'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $host = $_POST['host'] ?? 'localhost';
    $port = (int)($_POST['port'] ?? 3306);
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    echo json_encode(test_db_connection($host, $port, $user, $pass), JSON_UNESCAPED_UNICODE);
    exit;
}

// 当前步骤
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step));
$error = '';
$success = false;

// ── Step 2 POST 处理：保存数据库配置到 session，跳转到 step 3 ──
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['install_csrf_token'] ?? '';
    if (empty($_SESSION['install_csrf_token']) || !hash_equals($_SESSION['install_csrf_token'], $token)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $db_name   = trim($_POST['name'] ?? '');
        $db_prefix = trim($_POST['prefix'] ?? 'cd_');
        $host      = $_POST['host'] ?? 'localhost';
        $port      = (int)($_POST['port'] ?? 3306);
        $user      = $_POST['user'] ?? '';
        $pass      = $_POST['pass'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
            $error = '数据库名只能包含字母、数字和下划线';
        } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db_prefix)) {
            $error = '表前缀只能包含字母、数字和下划线，且必须以字母或下划线开头';
        } else {
            // 先测试数据库连接是否真的通
            $testResult = test_db_connection($host, $port, $user, $pass);
            if (!$testResult['success']) {
                $error = '数据库连接失败，请检查配置后重试';
            } else {
                $_SESSION['install_db'] = [
                    'host'   => $host,
                    'port'   => $port,
                    'user'   => $user,
                    'pass'   => $pass,
                    'name'   => $db_name,
                    'prefix' => $db_prefix,
                ];
                header('Location: ?step=3');
                exit;
            }
        }
    }
}

// ── Step 3 处理：接收管理员账号 ──
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['install_csrf_token'] ?? '';
    if (empty($_SESSION['install_csrf_token']) || !hash_equals($_SESSION['install_csrf_token'], $token)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (strlen($username) < 3) {
            $error = '用户名至少 3 个字符';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = '密码至少8位，需包含大写字母、小写字母和数字';
        } elseif ($password !== $confirm) {
            $error = '两次输入的密码不一致';
        } else {
            // 立即哈希密码，不在 session 中存储明文
            $_SESSION['install_admin'] = [
                'username'       => $username,
                'password_hashed' => password_hash($password, PASSWORD_DEFAULT),
            ];
            header('Location: ?step=4');
            exit;
        }
    }
}

// ── Step 4 处理：执行安装 ──
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['install_csrf_token'] ?? '';
    if (empty($_SESSION['install_csrf_token']) || !hash_equals($_SESSION['install_csrf_token'], $token)) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $result = do_install();
        if ($result['success']) {
            $step = 5;
        } else {
            $error = $result['message'];
        }
    }
}

// ── 步骤前置条件检查 ──
// Step 2：需确认是从 Step 1 过来的（可选，环境检测不强制阻断）
// Step 3 GET：必须有数据库配置
if ($step === 3 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($_SESSION['install_db'])) {
        header('Location: ?step=2');
        exit;
    }
}
// Step 4 GET：必须有数据库配置 + 管理员信息
if ($step === 4 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (empty($_SESSION['install_db']) || empty($_SESSION['install_admin'])) {
        header('Location: ?step=1');
        exit;
    }
}

// 释放安装锁（安装未完成，仅 GET 请求到达页面渲染阶段时释放）
// POST 请求中如果安装成功会在 do_install() 中释放，失败则保持锁
if ($lockHandle !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 写入空标记表示安装正在进行中（防止并发）
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    $lockHandle = null;
}

// 环境检测结果
$checks = ($step === 1) ? check_environment() : [];
$all_pass = true;
foreach ($checks as $check) {
    if (isset($check['required']) && !$check['required']) continue;
    if (!$check['pass']) $all_pass = false;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 · 那些年同学录</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<style>
:root {
  --color-bg:#FFF8F0;--color-bg-warm:#FFF1E0;--color-bg-card:#FFFBF5;
  --color-primary:#5D4037;--color-primary-light:#795548;
  --color-secondary:#E8913A;--color-secondary-light:#F0A85C;
  --color-accent:#C4A265;--color-text:#3E2723;--color-text-secondary:#6D4C41;
  --color-text-muted:#8D6E63;--color-text-light:#A1887F;
  --color-border:#E8D5C4;--color-border-light:#F0E6DA;
  --color-success:#7CB342;--color-error:#E53935;
  --color-tag-bg:#F5E6D3;
  --font-display:'Ma Shan Zheng','Noto Serif SC',serif;
  --font-heading:'Noto Serif SC','Georgia',serif;
  --font-body:'Noto Sans SC','Helvetica Neue',sans-serif;
  --radius-sm:6px;--radius-md:12px;--radius-lg:16px;--radius-xl:24px;
  --shadow-sm:0 1px 3px rgba(93,64,55,0.08);
  --shadow-md:0 4px 12px rgba(93,64,55,0.1);
  --shadow-card:0 2px 8px rgba(93,64,55,0.08);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font-body);color:var(--color-text);background:var(--color-bg);background-image:url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E"),linear-gradient(180deg,var(--color-bg),var(--color-bg-warm));background-attachment:fixed;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.install-container{width:100%;max-width:560px;animation:fadeInUp .6s ease-out}
.install-header{text-align:center;margin-bottom:32px}
.install-logo{font-family:var(--font-display);font-size:2rem;color:var(--color-primary);letter-spacing:3px;margin-bottom:8px}
.install-subtitle{font-size:.85rem;color:var(--color-text-muted);letter-spacing:1px}
.steps-indicator{display:flex;align-items:center;justify-content:center;margin-bottom:32px}
.step-dot{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:500;border:2px solid var(--color-border);background:var(--color-bg-card);color:var(--color-text-muted);transition:all .3s;position:relative;z-index:1}
.step-dot.active{border-color:var(--color-secondary);background:var(--color-secondary);color:#fff}
.step-dot.done{border-color:var(--color-success);background:var(--color-success);color:#fff}
.step-line{width:60px;height:2px;background:var(--color-border);transition:background .3s}
.step-line.done{background:var(--color-success)}
.install-card{background:var(--color-bg-card);border:2px solid var(--color-border);border-radius:var(--radius-lg);padding:32px;box-shadow:var(--shadow-md)}
.install-card h2{font-family:var(--font-heading);font-size:1.3rem;color:var(--color-primary);margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid var(--color-border-light)}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:.85rem;font-weight:500;color:var(--color-text-secondary);margin-bottom:6px}
.form-group label .required{color:var(--color-error)}
.form-row{display:grid;grid-template-columns:2fr 1fr;gap:12px}
.form-input{width:100%;padding:10px 14px;border:2px solid var(--color-border);border-radius:var(--radius-sm);background:var(--color-bg);font-family:var(--font-body);font-size:.95rem;color:var(--color-text);transition:border-color .2s;box-shadow:inset 0 2px 6px rgba(93,64,55,.06)}
.form-input:focus{outline:none;border-color:var(--color-secondary)}
.form-input::placeholder{color:var(--color-text-light)}
.check-list{list-style:none}
.check-item{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border-light);font-size:.9rem}
.check-item:last-child{border-bottom:none}
.check-label{font-weight:500;color:var(--color-text-secondary)}
.check-value{font-size:.8rem;color:var(--color-text-muted)}
.check-status{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;margin-left:12px}
.check-status.pass{background:#E8F5E9;color:var(--color-success)}
.check-status.fail{background:#FFEBEE;color:var(--color-error)}
.btn-row{display:flex;gap:12px;margin-top:24px}
.btn{flex:1;padding:12px 20px;border:none;border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.95rem;font-weight:500;cursor:pointer;transition:all .2s;text-align:center}
.btn-primary{background:var(--color-secondary);color:#fff;box-shadow:var(--shadow-sm)}
.btn-primary:hover{background:var(--color-secondary-light);transform:translateY(-1px);box-shadow:var(--shadow-md)}
.btn-secondary{background:var(--color-tag-bg);color:var(--color-text-secondary)}
.btn-secondary:hover{background:var(--color-border)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important}
.alert{padding:12px 16px;border-radius:var(--radius-sm);font-size:.85rem;margin-bottom:20px;line-height:1.6}
.alert-error{background:#FFEBEE;color:#C62828;border:1px solid #FFCDD2}
.alert-success{background:#E8F5E9;color:#2E7D32;border:1px solid #C8E6C9}
.alert-info{background:#FFF3E0;color:#E65100;border:1px solid #FFE0B2}
.success-icon{text-align:center;font-size:3rem;margin-bottom:16px}
.success-title{text-align:center;font-family:var(--font-display);font-size:1.8rem;color:var(--color-primary);margin-bottom:16px}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:480px){.form-row{grid-template-columns:1fr}.install-card{padding:20px}.step-line{width:40px}}
</style>
</head>
<body>

<div class="install-container">
  <div class="install-header">
    <div class="install-logo">✦ 那些年</div>
    <div class="install-subtitle">同学录安装向导</div>
  </div>

  <!-- 步骤指示器 -->
  <div class="steps-indicator">
    <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">1</div>
    <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
    <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">2</div>
    <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
    <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>">3</div>
    <div class="step-line <?= $step > 3 ? 'done' : '' ?>"></div>
    <div class="step-dot <?= $step >= 4 ? ($step > 4 ? 'done' : 'active') : '' ?>">4</div>
  </div>

  <div class="install-card">

    <?php if ($step === 5): ?>
    <!-- 安装成功 -->
    <div class="success-icon">🎉</div>
    <div class="success-title">安装完成！</div>
    <div class="alert alert-success">
      恭喜！同学录系统已成功安装。<br>
      你可以使用刚才设置的管理员账号登录后台管理。
    </div>
    <div class="alert alert-info">
      ⚠️ <strong>安全提示：</strong>请删除 install.php 文件，以防止他人重新安装系统。<br>
      删除命令：<code>rm install.php</code>
    </div>
    <div class="btn-row">
      <a href="index.php" class="btn btn-primary">进入首页</a>
      <a href="admin/login.php" class="btn btn-secondary">进入后台</a>
    </div>

    <?php elseif ($step === 1): ?>
    <!-- Step 1: 环境检测 -->
    <h2>🔍 环境检测</h2>
    <?php if (!$all_pass): ?>
    <div class="alert alert-error">部分环境检测未通过，请先解决以下问题后再继续安装。</div>
    <?php endif; ?>
    <ul class="check-list">
      <?php foreach ($checks as $check): ?>
      <li class="check-item">
        <span class="check-label"><?= htmlspecialchars($check['label']) ?></span>
        <span class="check-value"><?= htmlspecialchars($check['value']) ?></span>
        <span class="check-status <?= $check['pass'] ? 'pass' : 'fail' ?>"><?= $check['pass'] ? '✓' : '✗' ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="btn-row">
      <button class="btn btn-primary" <?= $all_pass ? 'onclick="location.href=\'?step=2\'"' : 'disabled' ?>>
        下一步：数据库配置
      </button>
    </div>

    <?php elseif ($step === 2): ?>
    <!-- Step 2: 数据库配置 -->
    <h2>🗄️ 数据库配置</h2>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php $db_saved = $_SESSION['install_db'] ?? []; ?>

    <form method="POST" id="dbForm">
      <input type="hidden" name="install_csrf_token" value="<?= htmlspecialchars($_SESSION['install_csrf_token']) ?>">
      <div class="form-group">
        <label>数据库主机</label>
        <input type="text" name="host" class="form-input" value="<?= htmlspecialchars($db_saved['host'] ?? 'localhost') ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>端口</label>
          <input type="number" name="port" class="form-input" value="<?= htmlspecialchars($db_saved['port'] ?? 3306) ?>" required>
        </div>
        <div class="form-group">
          <label>表前缀</label>
          <input type="text" name="prefix" class="form-input" value="<?= htmlspecialchars($db_saved['prefix'] ?? 'cd_') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label>用户名 <span class="required">*</span></label>
        <input type="text" name="user" class="form-input" placeholder="数据库用户名" value="<?= htmlspecialchars($db_saved['user'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="pass" class="form-input" placeholder="数据库密码">
      </div>
      <div class="form-group">
        <label>数据库名 <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" placeholder="classmate_directory" value="<?= htmlspecialchars($db_saved['name'] ?? '') ?>" required>
      </div>
      <div id="dbTestResult"></div>
      <div class="btn-row">
        <button type="button" class="btn btn-secondary" onclick="location.href='?step=1'">上一步</button>
        <button type="button" class="btn btn-secondary" onclick="testDB()">测试连接</button>
        <button type="submit" class="btn btn-primary">下一步：管理员账号</button>
      </div>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- Step 3: 管理员账号 -->
    <h2>👤 管理员账号</h2>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="install_csrf_token" value="<?= htmlspecialchars($_SESSION['install_csrf_token']) ?>">
      <div class="form-group">
        <label>管理员用户名 <span class="required">*</span></label>
        <input type="text" name="username" class="form-input" placeholder="设置管理员用户名" required minlength="3" maxlength="50">
      </div>
      <div class="form-group">
        <label>管理员密码 <span class="required">*</span></label>
        <input type="password" name="password" class="form-input" placeholder="至少8位，需包含大写、小写字母和数字" required minlength="8">
      </div>
      <div class="form-group">
        <label>确认密码 <span class="required">*</span></label>
        <input type="password" name="password_confirm" class="form-input" placeholder="再次输入密码" required minlength="8">
      </div>
      <div class="btn-row">
        <button type="button" class="btn btn-secondary" onclick="location.href='?step=2'">上一步</button>
        <button type="submit" class="btn btn-primary" onclick="return checkPasswords()">下一步：确认安装</button>
      </div>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- Step 4: 确认安装 -->
    <h2>🚀 确认安装</h2>
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
    <div class="alert alert-info">
      即将执行以下操作：<br>
      ✦ 创建数据库和表结构<br>
      ✦ 创建管理员账号<br>
      ✦ 写入站点默认配置<br>
      ✦ 生成 config.php 配置文件
    </div>
    <div class="btn-row">
      <a href="?step=3" class="btn btn-secondary">上一步</a>
      <button class="btn btn-primary" onclick="document.getElementById('installForm').submit()">确认安装</button>
    </div>
    <form method="POST" id="installForm" style="display:none">
      <input type="hidden" name="install_csrf_token" value="<?= htmlspecialchars($_SESSION['install_csrf_token']) ?>">
    </form>
    <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<script>
function testDB() {
    const form = document.getElementById('dbForm');
    const result = document.getElementById('dbTestResult');
    const formData = new FormData(form);
    result.innerHTML = '<div class="alert alert-info">正在测试连接...</div>';
    fetch('install.php?ajax=1', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        result.innerHTML = data.success
          ? '<div class="alert alert-success">✓ ' + data.message + '</div>'
          : '<div class="alert alert-error">✗ ' + data.message + '</div>';
      })
      .catch(() => { result.innerHTML = '<div class="alert alert-error">请求失败</div>'; });
}

function checkPasswords() {
    const pwd = document.querySelector('input[name="password"]').value;
    const cfm = document.querySelector('input[name="password_confirm"]').value;
    if (pwd.length < 8 || !/[A-Z]/.test(pwd) || !/[a-z]/.test(pwd) || !/[0-9]/.test(pwd)) {
        alert('密码至少8位，需包含大写字母、小写字母和数字');
        return false;
    }
    if (pwd !== cfm) { alert('两次输入的密码不一致'); return false; }
    return true;
}
</script>
</body>
</html>
