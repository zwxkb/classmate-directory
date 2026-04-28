<?php
/**
 * 管理后台登录页
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// 如果已登录，跳转到仪表盘
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// 登录频率限制：基于 IP 文件存储，5次失败后锁定5分钟（清 cookie 无法绕过）
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$login_lock_file = sys_get_temp_dir() . '/cd_login_' . md5($ip);
$max_attempts = 5;
$lock_duration = 300; // 5分钟

$lock_fh = @fopen($login_lock_file, 'c+');
$is_locked = false;
$attempts_count = 0;

if ($lock_fh && flock($lock_fh, LOCK_EX)) {
    $data = @json_decode(stream_get_contents($lock_fh), true) ?: ['count' => 0, 'last_time' => 0];
    $attempts_count = $data['count'];

    if ($data['count'] >= $max_attempts && (time() - $data['last_time']) < $lock_duration) {
        $is_locked = true;
        $remaining = $lock_duration - (time() - $data['last_time']);
        $error = '登录尝试次数过多，请 ' . ceil($remaining / 60) . ' 分钟后再试';
    }

    // 锁定时间已过，重置
    if ($data['count'] >= $max_attempts && (time() - $data['last_time']) >= $lock_duration) {
        $data = ['count' => 0, 'last_time' => 0];
        $attempts_count = 0;
    }

    if (!$is_locked) {
        // 非锁定状态释放锁，后续失败时再获取
        flock($lock_fh, LOCK_UN);
        fclose($lock_fh);
        $lock_fh = null;
    }
}

$error = $error ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    // 验证 CSRF
    if (!csrf_verify()) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = '请填写用户名和密码';
        } else {
            $result = admin_login($username, $password);
            if ($result['success']) {
                // 登录成功，清除失败计数
                if ($lock_fh) {
                    ftruncate($lock_fh, 0);
                    fclose($lock_fh);
                    @unlink($login_lock_file);
                }
                header('Location: index.php');
                exit;
            } else {
                // 记录失败次数
                $lock_fh = @fopen($login_lock_file, 'c+');
                if ($lock_fh && flock($lock_fh, LOCK_EX)) {
                    $d = @json_decode(stream_get_contents($lock_fh), true) ?: ['count' => 0, 'last_time' => 0];
                    $d['count']++;
                    $d['last_time'] = time();
                    ftruncate($lock_fh, 0);
                    rewind($lock_fh);
                    fwrite($lock_fh, json_encode($d));
                }
                if ($lock_fh) {
                    flock($lock_fh, LOCK_UN);
                    fclose($lock_fh);
                }
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理登录 · 那些年同学录</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="login-page">
  <div class="login-card">
    <h1>✦ 那些年</h1>
    <p class="subtitle">管理后台登录</p>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:16px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php csrf_field(); ?>
      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" class="form-input" placeholder="请输入管理员用户名" required autofocus>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" class="form-input" placeholder="请输入密码" required>
      </div>
      <button type="submit" class="btn-submit">登 录</button>
    </form>

    <p style="margin-top:20px;font-size:0.8rem;color:var(--color-text-light);">
      <a href="../index.php">← 返回首页</a>
    </p>
  </div>
</div>

</body>
</html>
