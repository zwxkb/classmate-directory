<?php
/**
 * 首页
 * 从 settings 表读取站点信息，展示班级封面
 */

// 如果未安装，跳转到安装向导
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$settings = get_all_settings($db);

$site_name       = $settings['site_name'] ?? '那些年 · 同学录';
$school_name     = $settings['school_name'] ?? '';
$class_name      = $settings['class_name'] ?? '';
$graduation_year = $settings['graduation_year'] ?? '';
$cover_photo     = $settings['cover_photo'] ?? '';
$slogan          = $settings['slogan'] ?? '时光不老，我们不散。';

// 统计数据
try {
    $student_count = $db->query("SELECT COUNT(*) FROM " . table('students'))->fetchColumn();
    $memory_count  = $db->query("SELECT COUNT(*) FROM " . table('memories'))->fetchColumn();
    $message_count = $db->query("SELECT COUNT(*) FROM " . table('messages'))->fetchColumn();
} catch (Exception $e) {
    $student_count = 0;
    $memory_count  = 0;
    $message_count = 0;
}

// 封面图
$cover_url = !empty($cover_photo) && file_exists(__DIR__ . '/uploads/' . $cover_photo)
    ? 'uploads/' . $cover_photo
    : 'https://picsum.photos/seed/classphoto2010/720/420';

// 班级标注
$class_label = '';
if ($graduation_year && $class_name) {
    $class_label = "✦ {$graduation_year}届{$class_name} ✦";
} elseif ($class_name) {
    $class_label = "✦ {$class_name} ✦";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($site_name) ?></title>
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
      <li><a href="index.php" class="active">首页</a></li>
      <li><a href="students.php">同学档案</a></li>
      <li><a href="memories.php">回忆墙</a></li>
      <li><a href="messages.php">留言板</a></li>
      <li><a href="submit.php">提交档案</a></li>
    </ul>
  </div>
</nav>

<!-- 首页 Hero -->
<section class="page-section" style="display:flex;align-items:center;justify-content:center;padding-top:var(--nav-height);padding-bottom:0;min-height:100vh;overflow:hidden;">
  <div class="hero">
    <?php if ($graduation_year): ?>
    <div class="hero-badge">✦ CLASS OF <?= e($graduation_year) ?> ✦</div>
    <?php endif; ?>

    <?php if ($school_name): ?>
    <div class="hero-school"><?= e($school_name) ?></div>
    <?php endif; ?>

    <h1 class="hero-title"><?= e($site_name) ?></h1>

    <?php if ($graduation_year || $class_name): ?>
    <div class="hero-year">
      <?php if ($graduation_year): ?>—— <?= e($graduation_year) ?> 届<?php endif; ?>
      <?= e($class_name) ?> ——
    </div>
    <?php endif; ?>

    <div class="hero-photo vintage-border" data-caption="<?= e($class_label) ?>">
      <img src="<?= e($cover_url) ?>" alt="班级大合照">
    </div>

    <nav class="hero-nav">
      <a href="students.php">
        <span class="nav-icon">📖</span> 同学档案
      </a>
      <a href="memories.php">
        <span class="nav-icon">📸</span> 回忆墙
      </a>
      <a href="messages.php">
        <span class="nav-icon">💌</span> 留言板
      </a>
    </nav>

    <?php if ($slogan): ?>
    <p class="hero-tagline">"<?= e($slogan) ?>"</p>
    <?php endif; ?>
  </div>
</section>

<!-- 页脚 -->
<footer class="site-footer">
  <span>✦</span> <?= e($site_name) ?> <span>✦</span><br>
  <span style="font-size:0.8em;margin-top:4px;display:inline-block;">
    <?= e($student_count) ?> 位同学 · <?= e($memory_count) ?> 段回忆 · <?= e($message_count) ?> 条留言
  </span>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
