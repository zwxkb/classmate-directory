<?php
/**
 * 管理后台 - 回忆管理
 * 增删改查，支持图片上传
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();
$upload_dir = __DIR__ . '/../uploads/photos';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// 处理 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['form_action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        if (!csrf_verify()) {
            $message = '安全验证失败';
            $messageType = 'error';
        } else {
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $memory_date = trim($_POST['memory_date'] ?? '');

            if (empty($title)) {
                $message = '请填写回忆标题';
                $messageType = 'error';
            } else {
                // 处理图片上传
                $photo = '';
                if (!empty($_FILES['photo']['name'])) {
                    $result = safe_upload($_FILES['photo'], $upload_dir, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], 5242880);
                    if ($result['success']) {
                        $photo = $result['path'];
                    } else {
                        $message = '图片上传失败：' . $result['message'];
                        $messageType = 'error';
                    }
                }

                if (empty($message)) {
                    try {
                        if ($post_action === 'add') {
                            $sql = "INSERT INTO " . table('memories') . " (title, description, photo, memory_date) VALUES (:title, :desc, :photo, :date)";
                            $params = [':title' => $title, ':desc' => $description, ':photo' => $photo, ':date' => $memory_date ?: null];
                        } else {
                            $edit_id = (int)($_POST['edit_id'] ?? 0);
                            // 如果上传了新图片，删除旧图片
                            if ($photo && $edit_id > 0) {
                                $old_stmt = $db->prepare("SELECT photo FROM " . table('memories') . " WHERE id = :id");
                                $old_stmt->execute([':id' => $edit_id]);
                                $old_photo = $old_stmt->fetchColumn();
                                if ($old_photo) @unlink($upload_dir . '/' . $old_photo);
                            }
                            if ($photo) {
                                $sql = "UPDATE " . table('memories') . " SET title=:title, description=:desc, photo=:photo, memory_date=:date WHERE id=:id";
                                $params = [':title' => $title, ':desc' => $description, ':photo' => $photo, ':date' => $memory_date ?: null, ':id' => $edit_id];
                            } else {
                                $sql = "UPDATE " . table('memories') . " SET title=:title, description=:desc, memory_date=:date WHERE id=:id";
                                $params = [':title' => $title, ':desc' => $description, ':date' => $memory_date ?: null, ':id' => $edit_id];
                            }
                        }
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        $message = $post_action === 'add' ? '添加成功' : '修改成功';
                        $messageType = 'success';
                        $action = 'list';
                    } catch (Exception $e) {
                        error_log('回忆操作失败: ' . $e->getMessage());
                        $message = '操作失败，请稍后重试';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

// 删除（POST + CSRF 验证）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'delete') {
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0 && csrf_verify()) {
        try {
            $stmt = $db->prepare("SELECT photo FROM " . table('memories') . " WHERE id = :id");
            $stmt->execute([':id' => $delete_id]);
            $memory = $stmt->fetch();
            if ($memory && !empty($memory['photo'])) {
                @unlink($upload_dir . '/' . $memory['photo']);
            }
            $stmt = $db->prepare("DELETE FROM " . table('memories') . " WHERE id = :id");
            $stmt->execute([':id' => $delete_id]);
            $message = '删除成功';
            $messageType = 'success';
            $action = 'list';
        } catch (Exception $e) {
            error_log('删除回忆失败: ' . $e->getMessage());
            $message = '删除失败';
            $messageType = 'error';
        }
    }
}

// 获取编辑数据
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM " . table('memories') . " WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $edit_data = $stmt->fetch();
    if (!$edit_data) {
        $action = 'list';
        $message = '找不到该回忆';
        $messageType = 'error';
    }
}

// 获取列表
if ($action === 'list') {
    try {
        $memories = $db->query("SELECT * FROM " . table('memories') . " ORDER BY memory_date DESC, id DESC")->fetchAll();
    } catch (Exception $e) {
        $memories = [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>回忆管理 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'memories.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-header">
      <h1>📸 回忆管理</h1>
      <div class="admin-header-actions">
        <?php if ($action === 'list'): ?>
        <a href="memories.php?action=add" class="btn-admin btn-admin-primary">+ 添加回忆</a>
        <?php endif; ?>
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
    <?php if (empty($memories)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📸</div>
      <div class="empty-state-text">还没有回忆记录，点击上方按钮添加</div>
    </div>
    <?php else: ?>
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>图片</th>
            <th>标题</th>
            <th>日期</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($memories as $m): ?>
          <tr>
            <td>
              <?php if (!empty($m['photo'])): ?>
              <img class="photo-thumb" src="../uploads/<?= e($m['photo']) ?>" alt="<?= e($m['title']) ?>"
                   onerror="this.style.display='none'">
              <?php else: ?>
              <span style="color:var(--color-text-light);font-size:0.8rem;">无图片</span>
              <?php endif; ?>
            </td>
            <td><?= e($m['title']) ?></td>
            <td style="white-space:nowrap;"><?= e(format_date($m['memory_date'])) ?></td>
            <td class="actions">
              <a href="memories.php?action=edit&id=<?= $m['id'] ?>" class="btn-admin">编辑</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('确定删除这段回忆吗？')">
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                <?php csrf_field(); ?>
                <button type="submit" class="btn-admin btn-admin-danger">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <h2><?= $action === 'add' ? '添加回忆' : '编辑回忆' ?></h2>
      <?php csrf_field(); ?>
      <input type="hidden" name="form_action" value="<?= $action ?>">
      <?php if ($action === 'edit' && $edit_data): ?>
      <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label>标题 <span class="required">*</span></label>
        <input type="text" name="title" class="form-input" required
               value="<?= e($edit_data['title'] ?? '') ?>" placeholder="回忆标题">
      </div>

      <div class="form-group">
        <label>日期</label>
        <input type="date" name="memory_date" class="form-input"
               value="<?= e($edit_data['memory_date'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>图片</label>
        <?php if ($action === 'edit' && $edit_data && !empty($edit_data['photo'])): ?>
        <img src="../uploads/<?= e($edit_data['photo']) ?>" alt="当前图片"
             style="max-width:300px;border-radius:8px;border:2px solid var(--color-border);margin-bottom:8px;display:block;"
             id="photoPreview"
             onerror="this.style.display='none'">
        <?php endif; ?>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp"
               onchange="previewImage(this, 'photoPreview')">
        <p class="form-hint">支持 JPG/PNG/GIF/WebP，最大 5MB</p>
      </div>

      <div class="form-group">
        <label>描述</label>
        <textarea name="description" class="form-input" placeholder="回忆的详细描述..."><?= e($edit_data['description'] ?? '') ?></textarea>
      </div>

      <div class="form-actions">
        <a href="memories.php" class="btn-admin">取消</a>
        <button type="submit" class="btn-admin btn-admin-primary"><?= $action === 'add' ? '添加' : '保存修改' ?></button>
      </div>
    </form>

    <?php endif; ?>
  </main>
</div>

<script src="../assets/app.js"></script>
</body>
</html>
