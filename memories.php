<?php
/**
 * 回忆墙
 * 时间线布局展示回忆，照片灯箱
 */

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$settings = get_all_settings($db);
$site_name = $settings['site_name'] ?? '那些年 · 同学录';

// 查询回忆列表
try {
    $stmt = $db->query("SELECT * FROM " . table('memories') . " ORDER BY memory_date DESC, created_at DESC");
    $memories = $stmt->fetchAll();
} catch (Exception $e) {
    $memories = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>回忆墙 · <?= e($site_name) ?></title>
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
      <li><a href="memories.php" class="active">回忆墙</a></li>
      <li><a href="messages.php">留言板</a></li>
      <li><a href="submit.php">提交档案</a></li>
    </ul>
  </div>
</nav>

<section class="page-section">
  <div class="container">
    <h2 class="section-title">回忆墙</h2>
    <p class="section-subtitle">时间带不走那些闪闪发光的日子</p>
    <div class="ornament">· ✦ · ✦ · ✦ ·</div>

    <?php if (empty($memories)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📸</div>
      <div class="empty-state-text">还没有回忆，等待管理员添加...</div>
    </div>
    <?php else: ?>
    <div class="timeline">
      <?php foreach ($memories as $i => $m): ?>
      <div class="timeline-item" style="animation-delay:<?= $i * 0.1 ?>s">
        <div class="timeline-dot"></div>
        <div class="timeline-card">
          <div class="timeline-date"><?= e(format_date($m['memory_date'])) ?></div>
          <div class="timeline-title"><?= e($m['title']) ?></div>
          <?php if ($m['description']): ?>
          <div class="timeline-desc"><?= e($m['description']) ?></div>
          <?php endif; ?>

          <?php if (!empty($m['photo'])): ?>
          <div class="timeline-photo" onclick="openLightbox('uploads/<?= e($m['photo']) ?>')">
            <img src="uploads/<?= e($m['photo']) ?>" alt="<?= e($m['title']) ?>" loading="lazy"
                 onerror="this.closest('.timeline-photo').style.display='none'">
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- 灯箱 -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close">✕</button>
  <img id="lightboxImg" src="" alt="大图">
</div>

<!-- 页脚 -->
<footer class="site-footer">
  <span>✦</span> <?= e($site_name) ?> <span>✦</span>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
