<?php
/**
 * 管理后台 - 修改密码
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message = '安全验证失败';
        $messageType = 'error';
    } else {
        $old_password     = $_POST['old_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $message = '请填写所有字段';
            $messageType = 'error';
        } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $message = '新密码至少8位，需包含大写字母、小写字母和数字';
            $messageType = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = '两次输入的新密码不一致';
            $messageType = 'error';
        } else {
            // 验证旧密码
            $stmt = $db->prepare("SELECT password FROM " . table('users') . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['admin_id']]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($old_password, $admin['password'])) {
                $message = '当前密码不正确';
                $messageType = 'error';
            } else {
                // 更新密码
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $stmt = $db->prepare("UPDATE " . table('users') . " SET password = :password WHERE id = :id");
                    $stmt->execute([':password' => $new_hash, ':id' => $_SESSION['admin_id']]);
                    // 重新生成 session ID，使旧 session 失效
                    session_regenerate_id(true);
                    $message = '密码修改成功';
                    $messageType = 'success';
                } catch (Exception $e) {
                    error_log('密码修改失败: ' . $e->getMessage());
                    $message = '密码修改失败，请稍后重试';
                    $messageType = 'error';
                }
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
<title>修改密码 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'password.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-header">
      <h1>🔑 修改密码</h1>
      <div class="admin-header-actions">
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="POST" class="admin-form" style="max-width:480px;">
      <h2>修改登录密码</h2>
      <?php csrf_field(); ?>

      <div class="form-group">
        <label>当前密码 <span class="required">*</span></label>
        <input type="password" name="old_password" class="form-input" placeholder="请输入当前密码" required>
      </div>
      <div class="form-group">
        <label>新密码 <span class="required">*</span></label>
        <input type="password" name="new_password" class="form-input" placeholder="至少8位，包含大写、小写字母和数字" required minlength="8">
      </div>
      <div class="form-group">
        <label>确认新密码 <span class="required">*</span></label>
        <input type="password" name="confirm_password" class="form-input" placeholder="再次输入新密码" required minlength="8">
      </div>

      <div class="form-actions">
        <a href="index.php" class="btn-admin">返回仪表盘</a>
        <button type="submit" class="btn-admin btn-admin-primary">修改密码</button>
      </div>
    </form>
  </main>
</div>

<script src="../assets/app.js"></script>
</body>
</html>
