<?php
/**
 * 同学档案
 * 卡片网格展示，支持搜索和城市筛选
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

// 获取所有城市（用于筛选标签）
$cities = [];
try {
    $stmt = $db->query("SELECT DISTINCT city FROM " . table('students') . " WHERE is_approved = 1 AND city != '' ORDER BY city");
    while ($row = $stmt->fetch()) {
        $cities[] = $row['city'];
    }
} catch (Exception $e) {}

// 获取同学列表
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;

try {
    // 搜索条件
    $where = "is_approved = 1";
    $params = [];

    if (!empty($_GET['q'])) {
        $where .= " AND (name LIKE :q OR motto LIKE :q)";
        $params[':q'] = '%' . $_GET['q'] . '%';
    }
    if (!empty($_GET['city'])) {
        $where .= " AND city = :city";
        $params[':city'] = $_GET['city'];
    }

    // 总数
    $count_sql = "SELECT COUNT(*) FROM " . table('students') . " WHERE {$where}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // 分页
    $pag = paginate($total, $page, $per_page);

    // 查询
    $sql = "SELECT * FROM " . table('students') . " WHERE {$where} ORDER BY sort_order ASC, id ASC LIMIT :offset, :per_page";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':offset', $pag['offset'], PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $pag['per_page'], PDO::PARAM_INT);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
    $pag = paginate(0, 1, $per_page);
}

// 准备前端数据
$students_json = [];
foreach ($students as $s) {
    $students_json[] = [
        'id'        => (int)$s['id'],
        'name'      => $s['name'],
        'avatar_url'=> get_avatar_url($s['avatar']),
        'city'      => $s['city'],
        'motto'     => $s['motto'],
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>同学档案 · <?= e($site_name) ?></title>
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
      <li><a href="students.php" class="active">同学档案</a></li>
      <li><a href="memories.php">回忆墙</a></li>
      <li><a href="messages.php">留言板</a></li>
      <li><a href="submit.php">提交档案</a></li>
    </ul>
  </div>
</nav>

<section class="page-section">
  <div class="container">
    <h2 class="section-title">同学档案</h2>
    <p class="section-subtitle">每一个名字，都是一段青春的记忆</p>
    <div class="ornament">· ✦ · ✦ · ✦ ·</div>

    <!-- 筛选栏 -->
    <div class="classmates-filters">
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="搜索姓名、座右铭..."
               value="<?= e($_GET['q'] ?? '') ?>"
               oninput="onSearchInput()">
      </div>
      <?php if (!empty($cities)): ?>
      <div class="city-filter">
        <button class="city-tag <?= empty($_GET['city']) ? 'active' : '' ?>"
                onclick="filterByCity(this, '全部')">全部</button>
        <?php foreach ($cities as $city): ?>
        <button class="city-tag <?= (($_GET['city'] ?? '') === $city) ? 'active' : '' ?>"
                onclick="filterByCity(this, '<?= e($city) ?>')"><?= e($city) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 同学卡片网格 -->
    <div class="classmates-grid" id="classmatesGrid">
      <?php if (empty($students)): ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div class="empty-state-icon">📚</div>
        <div class="empty-state-text">还没有同学档案，等待管理员添加...</div>
      </div>
      <?php else: ?>
        <?php foreach ($students as $i => $s): ?>
        <div class="classmate-card" onclick="openModal(<?= $s['id'] ?>)" style="animation-delay:<?= $i * 0.06 ?>s">
          <div class="card-avatar">
            <img src="<?= e(get_avatar_url($s['avatar'])) ?>"
                 alt="<?= e($s['name']) ?>"
                 loading="lazy"
                 data-name="<?= e($s['name']) ?>">
          </div>
          <div class="card-name"><?= e($s['name']) ?></div>
          <div class="card-city">📍 <?= e($s['city']) ?: '未知' ?></div>
          <div class="card-motto">"<?= e($s['motto']) ?>"</div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- 分页 -->
    <div id="pagination">
      <?= pagination_html($pag, 'students.php?page={page}&q=' . urlencode($_GET['q'] ?? '') . '&city=' . urlencode($_GET['city'] ?? '')) ?>
    </div>
  </div>
</section>

<!-- 同学详情弹窗 -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal-card" onclick="event.stopPropagation()">
    <div class="modal-header">
      <button class="modal-close" onclick="closeModal()">✕</button>
      <div class="modal-avatar">
        <img id="modalAvatar" src="" alt="">
      </div>
      <div class="modal-name" id="modalName"></div>
      <div class="modal-city" id="modalCity"></div>
    </div>
    <div class="modal-body">
      <div class="modal-section">
        <div class="modal-section-title">📝 座右铭</div>
        <div class="modal-motto" id="modalMotto"></div>
      </div>
      <div class="modal-section">
        <div class="modal-section-title">📞 联系方式</div>
        <div class="modal-info-row">
          <span class="modal-info-label">手机</span>
          <span class="modal-info-value" id="modalPhone"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">邮箱</span>
          <span class="modal-info-value" id="modalEmail"></span>
        </div>
        <div class="modal-info-row">
          <span class="modal-info-label">微信</span>
          <span class="modal-info-value" id="modalWechat"></span>
        </div>
      </div>
      <div class="modal-section">
        <div class="modal-section-title">✏️ 个人简介</div>
        <div class="modal-bio" id="modalBio"></div>
      </div>
    </div>
  </div>
</div>

<!-- 页脚 -->
<footer class="site-footer">
  <span>✦</span> <?= e($site_name) ?> <span>✦</span>
</footer>

<!-- 传递同学数据给 JS -->
<script>
window.__studentsData = <?= str_replace('<\/', '<\\/', json_encode($students_json, JSON_UNESCAPED_UNICODE)) ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
