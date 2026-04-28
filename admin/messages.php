<?php
/**
 * 管理后台 - 留言管理
 * 查看、审核和删除留言
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();
$message = '';
$messageType = '';

// 审核操作（通过 JS 动态创建 form 提交，与 deleteSingle 模式一致）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'approve') {
    $approve_id = (int)($_POST['approve_id'] ?? 0);
    $approve_val = (int)($_POST['approve_val'] ?? 0);
    if ($approve_id > 0 && csrf_verify()) {
        try {
            $stmt = $db->prepare("UPDATE " . table('messages') . " SET is_approved = :val WHERE id = :id");
            $stmt->execute([':val' => $approve_val, ':id' => $approve_id]);
            $message = $approve_val === 1 ? '已通过审核' : '已撤回审核';
            $messageType = 'success';
        } catch (Exception $e) {
            error_log('审核留言失败: ' . $e->getMessage());
            $message = '审核操作失败';
            $messageType = 'error';
        }
    }
}

// 删除操作（POST + CSRF 验证）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'delete') {
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0 && csrf_verify()) {
        try {
            $stmt = $db->prepare("DELETE FROM " . table('messages') . " WHERE id = :id");
            $stmt->execute([':id' => $delete_id]);
            $message = '删除成功';
            $messageType = 'success';
        } catch (Exception $e) {
            error_log('删除留言失败: ' . $e->getMessage());
            $message = '删除失败';
            $messageType = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_delete'])) {
    // 批量删除（与单条删除互斥，防止同时执行）
    if (!csrf_verify()) {
        $message = '安全验证失败';
        $messageType = 'error';
    } else {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            try {
                $stmt = $db->prepare("DELETE FROM " . table('messages') . " WHERE id IN ($placeholders)");
                $stmt->execute(array_map('intval', $ids));
                $message = '批量删除成功';
                $messageType = 'success';
            } catch (Exception $e) {
                error_log('批量删除留言失败: ' . $e->getMessage());
                $message = '批量删除失败';
                $messageType = 'error';
            }
        }
    }
}

// 筛选参数
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if (!in_array($filter, ['all', 'pending', 'approved'])) {
    $filter = 'all';
}

// 获取留言列表
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;

try {
    $where = '';
    $params = [];
    if ($filter === 'pending') {
        $where = ' WHERE is_approved = 0';
    } elseif ($filter === 'approved') {
        $where = ' WHERE is_approved = 1';
    }

    $total = $db->query("SELECT COUNT(*) FROM " . table('messages') . $where)->fetchColumn();
    $pag = paginate($total, $page, $per_page);
    $stmt = $db->prepare("SELECT * FROM " . table('messages') . $where . " ORDER BY created_at DESC LIMIT :offset, :per_page");
    $stmt->bindValue(':offset', $pag['offset'], PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $pag['per_page'], PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
    $pag = paginate(0, 1, $per_page);
}

// 构建筛选链接的基础 URL
$filter_base_url = 'messages.php';
if ($filter !== 'all') {
    $filter_base_url .= '?filter=' . urlencode($filter);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>留言管理 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
<style>
.filter-tabs { display:flex; gap:8px; margin-bottom:16px; }
.filter-tab {
    padding:6px 16px; border-radius:6px; border:2px solid var(--color-border, #E8D5C4);
    background:transparent; cursor:pointer; font-size:.9rem; color:var(--color-text-secondary, #6D4C41);
    text-decoration:none; transition:all .2s;
}
.filter-tab:hover { border-color:var(--color-secondary, #E8913A); color:var(--color-secondary, #E8913A); }
.filter-tab.active { background:var(--color-secondary, #E8913A); border-color:var(--color-secondary, #E8913A); color:#fff; }
.status-approved { color:#7CB342; font-weight:500; }
.status-pending { color:#E8913A; font-weight:500; }
.btn-approve { background:#7CB342; color:#fff; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-size:.8rem; }
.btn-approve:hover { background:#689F38; }
.btn-revoke { background:#FF9800; color:#fff; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-size:.8rem; }
.btn-revoke:hover { background:#F57C00; }
</style>
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'messages.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-header">
      <h1>💌 留言管理</h1>
      <div class="admin-header-actions">
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <!-- 筛选标签 -->
    <div class="filter-tabs">
      <a href="messages.php" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">全部</a>
      <a href="messages.php?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">⏳ 待审核</a>
      <a href="messages.php?filter=approved" class="filter-tab <?= $filter === 'approved' ? 'active' : '' ?>">✓ 已通过</a>
    </div>

    <?php if (empty($messages)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">💌</div>
      <div class="empty-state-text">暂无留言</div>
    </div>
    <?php else: ?>
    <form method="POST" id="batchForm">
      <?php csrf_field(); ?>
      <div class="data-table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
              <th>姓名</th>
              <th>内容</th>
              <th>时间</th>
              <th>状态</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $m): ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?= $m['id'] ?>"></td>
              <td><?= e($m['student_name']) ?></td>
              <td><?= e(truncate($m['content'], 60)) ?></td>
              <td style="white-space:nowrap;"><?= e(format_date($m['created_at'], 'Y-m-d H:i')) ?></td>
              <td>
                <?php if (!empty($m['is_approved'])): ?>
                  <span class="status-approved">✓ 已通过</span>
                <?php else: ?>
                  <span class="status-pending">⏳ 待审核</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <?php if (empty($m['is_approved'])): ?>
                  <button type="button" class="btn-approve" onclick="approveSingle(<?= $m['id'] ?>, 1)">通过</button>
                <?php else: ?>
                  <button type="button" class="btn-revoke" onclick="approveSingle(<?= $m['id'] ?>, 0)">撤回</button>
                <?php endif; ?>
                <button type="button" class="btn-admin btn-admin-danger"
                        onclick="deleteSingle(<?= $m['id'] ?>)">删除</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px;display:flex;gap:12px;align-items:center;">
        <button type="submit" name="batch_delete" value="1" class="btn-admin btn-admin-danger"
                onclick="return confirm('确定删除选中的留言吗？')">批量删除</button>
      </div>
    </form>

    <?= pagination_html($pag, $filter_base_url . '&page={page}') ?>
    <?php endif; ?>
  </main>
</div>

<script src="../assets/app.js"></script>
<script>
// 审核操作：通过 JS 动态创建隐藏表单提交 POST（与 deleteSingle 模式一致）
function approveSingle(id, val) {
    var label = val === 1 ? '通过审核' : '撤回审核';
    if (!confirm('确定' + label + '这条留言吗？')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'messages.php';
    var a1 = document.createElement('input'); a1.type='hidden'; a1.name='form_action'; a1.value='approve'; f.appendChild(a1);
    var a2 = document.createElement('input'); a2.type='hidden'; a2.name='approve_id'; a2.value=id; f.appendChild(a2);
    var a3 = document.createElement('input'); a3.type='hidden'; a3.name='approve_val'; a3.value=val; f.appendChild(a3);
    var a4 = document.createElement('input'); a4.type='hidden'; a4.name='_token'; a4.value=document.querySelector('#batchForm input[name="_token"]').value; f.appendChild(a4);
    document.body.appendChild(f);
    f.submit();
}

// 单条删除：通过 JS 动态创建隐藏表单提交 POST，避免嵌套 <form>
function deleteSingle(id) {
    if (!confirm('确定删除这条留言吗？')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'messages.php';
    var a1 = document.createElement('input'); a1.type='hidden'; a1.name='form_action'; a1.value='delete'; f.appendChild(a1);
    var a2 = document.createElement('input'); a2.type='hidden'; a2.name='delete_id'; a2.value=id; f.appendChild(a2);
    var a3 = document.createElement('input'); a3.type='hidden'; a3.name='_token'; a3.value=document.querySelector('#batchForm input[name="_token"]').value; f.appendChild(a3);
    document.body.appendChild(f);
    f.submit();
}
</script>
</body>
</html>
