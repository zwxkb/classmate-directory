<?php
/**
 * 留言板
 * 展示留言列表，底部提交留言表单
 */

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

$db = get_db();
$settings = get_all_settings($db);
$site_name = $settings['site_name'] ?? '那些年 · 同学录';

// 查询留言列表
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;

try {
    $total = $db->query("SELECT COUNT(*) FROM " . table('messages') . " WHERE is_approved = 1")->fetchColumn();
    $pag = paginate($total, $page, $per_page);

    $stmt = $db->prepare("SELECT * FROM " . table('messages') . " WHERE is_approved = 1 ORDER BY created_at DESC LIMIT :offset, :per_page");
    $stmt->bindValue(':offset', $pag['offset'], PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $pag['per_page'], PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
    $pag = paginate(0, 1, $per_page);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>留言板 · <?= e($site_name) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- 导航栏 -->
<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-brand">✦ 那些年</a>
    <button class="navbar-toggle" onclick="toggleNav()" aria-label="切换菜单">
      <span></span><span></span><span></span>
    </button>
    <ul class="navbar-links" id="navLinks">
      <li><a href="index.php">首页</a></li>
      <li><a href="students.php">同学档案</a></li>
      <li><a href="memories.php">回忆墙</a></li>
      <li><a href="messages.php" class="active">留言板</a></li>
      <li><a href="submit.php">提交档案</a></li>
    </ul>
  </div>
</nav>

<section class="page-section">
  <div class="container">
    <h2 class="section-title">留言板</h2>
    <p class="section-subtitle">写下你想说的话，给未来的自己</p>
    <div class="ornament">· ✦ · ✦ · ✦ ·</div>

    <div class="messages-list" id="messagesList">
      <?php if (empty($messages)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">💌</div>
        <div class="empty-state-text">还没有留言，来写下第一条吧...</div>
      </div>
      <?php else: ?>
        <?php foreach ($messages as $i => $m): ?>
        <div class="message-card" style="animation-delay:<?= $i * 0.08 ?>s">
          <div class="message-header">
            <div class="message-avatar">
              <img src="https://ui-avatars.com/api/?name=<?= urlencode($m['student_name']) ?>&background=5D4037&color=fff&size=100"
                   alt="<?= e($m['student_name']) ?>" loading="lazy">
            </div>
            <div class="message-meta">
              <div class="message-name"><?= e($m['student_name']) ?></div>
              <div class="message-time"><?= e(format_date($m['created_at'], 'Y.m.d H:i')) ?></div>
            </div>
          </div>
          <div class="message-content"><?= nl2br(e($m['content'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?= pagination_html($pag, 'messages.php?page={page}') ?>

    <!-- 留言输入框 -->
    <div class="message-input-box vintage-border">
      <input type="text" id="messageName" placeholder="你的名字" maxlength="50">
      <textarea id="messageContent" placeholder="写下你的留言..." maxlength="1000"></textarea>
      <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
      <div class="message-input-footer">
        <button class="btn-submit" onclick="submitMessage()">✦ 留下心声</button>
      </div>
    </div>
  </div>
</section>

<!-- 页脚 -->
<footer class="site-footer">
  <span>✦</span> <?= e($site_name) ?> <span>✦</span>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
