<?php
/**
 * 管理后台侧边栏公共模板
 * 各管理页面通过 require_once 引入
 * $active_page 变量控制当前页面高亮（默认从 basename(__FILE__) 判断）
 */
if (!isset($active_page)) {
    $active_page = basename(__FILE__);
}

$nav_items = [
    ['href' => 'index.php',    'icon' => '📊', 'label' => '仪表盘',   'page' => 'index.php'],
    ['href' => 'students.php', 'icon' => '👥', 'label' => '同学管理', 'page' => 'students.php'],
    ['href' => 'memories.php', 'icon' => '📸', 'label' => '回忆管理', 'page' => 'memories.php'],
    ['href' => 'messages.php', 'icon' => '💌', 'label' => '留言管理', 'page' => 'messages.php'],
    ['href' => 'settings.php', 'icon' => '⚙️', 'label' => '站点设置', 'page' => 'settings.php'],
    ['href' => 'password.php', 'icon' => '🔑', 'label' => '修改密码', 'page' => 'password.php'],
];
?>
<aside class="admin-sidebar">
  <div class="admin-sidebar-brand">
    <a href="index.php">✦ 那些年</a>
  </div>
  <ul class="admin-nav">
    <?php foreach ($nav_items as $item): ?>
    <li><a href="<?= $item['href'] ?>"<?= ($active_page === $item['page']) ? ' class="active"' : '' ?>><span class="nav-icon"><?= $item['icon'] ?></span> <?= $item['label'] ?></a></li>
    <?php endforeach; ?>
    <li><a href="../index.php" target="_blank"><span class="nav-icon">🏠</span> 查看首页</a></li>
  </ul>
</aside>
