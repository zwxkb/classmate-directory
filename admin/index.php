<?php
/**
 * 管理后台仪表盘
 * 数据统计概览
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();

// 统计数据
try {
    $student_count = $db->query("SELECT COUNT(*) FROM " . table('students'))->fetchColumn();
    $memory_count  = $db->query("SELECT COUNT(*) FROM " . table('memories'))->fetchColumn();
    $message_count = $db->query("SELECT COUNT(*) FROM " . table('messages'))->fetchColumn();

    // 最近留言
    $recent_messages = $db->query("SELECT * FROM " . table('messages') . " ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $student_count = 0;
    $memory_count  = 0;
    $message_count = 0;
    $recent_messages = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>仪表盘 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'index.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <!-- 主内容 -->
  <main class="admin-main">
    <div class="admin-header">
      <h1>📊 仪表盘</h1>
      <div class="admin-header-actions">
        <span class="admin-user-info">👤 <?= e($_SESSION['admin_username'] ?? '') ?></span>
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-icon">👥</div>
        <div class="stat-card-value"><?= (int)$student_count ?></div>
        <div class="stat-card-label">同学档案</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon">📸</div>
        <div class="stat-card-value"><?= (int)$memory_count ?></div>
        <div class="stat-card-label">回忆记录</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-icon">💌</div>
        <div class="stat-card-value"><?= (int)$message_count ?></div>
        <div class="stat-card-label">留言总数</div>
      </div>
    </div>

    <!-- 最近留言 -->
    <h2 style="font-family:var(--font-heading);font-size:1.2rem;color:var(--color-primary);margin-bottom:16px;">最近留言</h2>
    <?php if (empty($recent_messages)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">💌</div>
      <div class="empty-state-text">暂无留言</div>
    </div>
    <?php else: ?>
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>姓名</th>
            <th>内容</th>
            <th>时间</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_messages as $m): ?>
          <tr>
            <td><?= e($m['student_name']) ?></td>
            <td><?= e(truncate($m['content'], 50)) ?></td>
            <td style="white-space:nowrap;"><?= e(format_date($m['created_at'], 'Y-m-d H:i')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </main>
</div>

<script src="../assets/app.js"></script>
</body>
</html>
