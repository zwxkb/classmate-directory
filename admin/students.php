<?php
/**
 * 管理后台 - 同学管理
 * 增删改查，支持头像上传、审核管理
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();
$upload_dir = __DIR__ . '/../uploads/avatars';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$message = '';
$messageType = '';

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['form_action'] ?? '';

    if ($post_action === 'add' || $post_action === 'edit') {
        // 验证 CSRF
        if (!csrf_verify()) {
            $message = '安全验证失败';
            $messageType = 'error';
        } else {
            $name      = trim($_POST['name'] ?? '');
            $motto     = trim($_POST['motto'] ?? '');
            $city      = trim($_POST['city'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $wechat    = trim($_POST['wechat'] ?? '');
            $bio       = trim($_POST['bio'] ?? '');
            $show_contact = isset($_POST['show_contact']) ? 1 : 0;
            $sort_order = (int)($_POST['sort_order'] ?? 0);

            if (empty($name)) {
                $message = '请填写同学姓名';
                $messageType = 'error';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = '邮箱格式不正确';
                $messageType = 'error';
            } elseif (!empty($phone) && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
                $message = '手机号格式不正确';
                $messageType = 'error';
            } else {
                // 处理头像上传
                $avatar = '';
                if (!empty($_FILES['avatar']['name'])) {
                    $result = safe_upload($_FILES['avatar'], $upload_dir);
                    if ($result['success']) {
                        $avatar = $result['path'];
                    } else {
                        $message = '头像上传失败：' . $result['message'];
                        $messageType = 'error';
                    }
                }

                if (empty($message)) {
                    try {
                        if ($post_action === 'add') {
                            $sql = "INSERT INTO " . table('students') . " (name, avatar, motto, city, phone, email, wechat, bio, show_contact, sort_order, is_approved) VALUES (:name, :avatar, :motto, :city, :phone, :email, :wechat, :bio, :show_contact, :sort, 1)";
                            $params = [
                                ':name' => $name, ':avatar' => $avatar,
                                ':motto' => $motto, ':city' => $city,
                                ':phone' => $phone, ':email' => $email,
                                ':wechat' => $wechat, ':bio' => $bio,
                                ':show_contact' => $show_contact,
                                ':sort' => $sort_order,
                            ];
                        } else {
                            $edit_id = (int)($_POST['edit_id'] ?? 0);
                            // 如果上传了新头像，删除旧头像
                            if ($avatar && $edit_id > 0) {
                                $old_stmt = $db->prepare("SELECT avatar FROM " . table('students') . " WHERE id = :id");
                                $old_stmt->execute([':id' => $edit_id]);
                                $old_avatar = $old_stmt->fetchColumn();
                                if ($old_avatar) @unlink($upload_dir . '/' . $old_avatar);
                            }
                            if ($avatar) {
                                $sql = "UPDATE " . table('students') . " SET name=:name, avatar=:avatar, motto=:motto, city=:city, phone=:phone, email=:email, wechat=:wechat, bio=:bio, show_contact=:show_contact, sort_order=:sort WHERE id=:id";
                                $params = [
                                    ':name' => $name, ':avatar' => $avatar,
                                    ':motto' => $motto, ':city' => $city,
                                    ':phone' => $phone, ':email' => $email,
                                    ':wechat' => $wechat, ':bio' => $bio,
                                    ':show_contact' => $show_contact,
                                    ':sort' => $sort_order, ':id' => $edit_id,
                                ];
                            } else {
                                $sql = "UPDATE " . table('students') . " SET name=:name, motto=:motto, city=:city, phone=:phone, email=:email, wechat=:wechat, bio=:bio, show_contact=:show_contact, sort_order=:sort WHERE id=:id";
                                $params = [
                                    ':name' => $name,
                                    ':motto' => $motto, ':city' => $city,
                                    ':phone' => $phone, ':email' => $email,
                                    ':wechat' => $wechat, ':bio' => $bio,
                                    ':show_contact' => $show_contact,
                                    ':sort' => $sort_order, ':id' => $edit_id,
                                ];
                            }
                        }
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        $message = $post_action === 'add' ? '添加成功' : '修改成功';
                        $messageType = 'success';
                        $action = 'list';
                    } catch (Exception $e) {
                        error_log('同学操作失败: ' . $e->getMessage());
                        $message = '操作失败，请稍后重试';
                        $messageType = 'error';
                    }
                }
            }
        }
    }

    // 审核操作：通过/撤回
    elseif ($post_action === 'approve' || $post_action === 'revoke') {
        $approve_id = (int)($_POST['approve_id'] ?? 0);
        if ($approve_id > 0 && csrf_verify()) {
            try {
                $new_status = ($post_action === 'approve') ? 1 : 0;
                $stmt = $db->prepare("UPDATE " . table('students') . " SET is_approved = :status WHERE id = :id");
                $stmt->execute([':status' => $new_status, ':id' => $approve_id]);
                $message = $post_action === 'approve' ? '已通过审核' : '已撤回审核';
                $messageType = 'success';
                $action = 'list';
            } catch (Exception $e) {
                error_log('审核操作失败: ' . $e->getMessage());
                $message = '操作失败';
                $messageType = 'error';
            }
        }
    }

    // 删除操作（POST + CSRF 验证）
    elseif ($post_action === 'delete') {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id > 0 && csrf_verify()) {
            try {
                // 删除头像文件
                $stmt = $db->prepare("SELECT avatar FROM " . table('students') . " WHERE id = :id");
                $stmt->execute([':id' => $delete_id]);
                $student = $stmt->fetch();
                if ($student && !empty($student['avatar'])) {
                    @unlink($upload_dir . '/' . $student['avatar']);
                }
                $stmt = $db->prepare("DELETE FROM " . table('students') . " WHERE id = :id");
                $stmt->execute([':id' => $delete_id]);
                $message = '删除成功';
                $messageType = 'success';
                $action = 'list';
            } catch (Exception $e) {
                error_log('删除同学失败: ' . $e->getMessage());
                $message = '删除失败';
                $messageType = 'error';
            }
        }
    }
}

// 获取编辑数据
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM " . table('students') . " WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $edit_data = $stmt->fetch();
    if (!$edit_data) {
        $action = 'list';
        $message = '找不到该同学';
        $messageType = 'error';
    }
}

// 获取列表（分页 + 筛选）
$per_page = 20;
$stu_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// 校验 filter 参数
if (!in_array($filter, ['all', 'pending', 'approved'])) {
    $filter = 'all';
}

if ($action === 'list') {
    try {
        // 构建筛选条件
        $filter_where = '';
        $filter_params = [];
        if ($filter === 'pending') {
            $filter_where = " WHERE is_approved = 0";
        } elseif ($filter === 'approved') {
            $filter_where = " WHERE is_approved = 1";
        }

        $total = $db->query("SELECT COUNT(*) FROM " . table('students') . $filter_where)->fetchColumn();
        $stu_pag = paginate($total, $stu_page, $per_page);
        $stmt = $db->prepare("SELECT * FROM " . table('students') . $filter_where . " ORDER BY sort_order ASC, id ASC LIMIT :offset, :per_page");
        $stmt->bindValue(':offset', $stu_pag['offset'], PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $stu_pag['per_page'], PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll();

        // 待审核数量
        $pending_count = $db->query("SELECT COUNT(*) FROM " . table('students') . " WHERE is_approved = 0")->fetchColumn();
    } catch (Exception $e) {
        $students = [];
        $stu_pag = paginate(0, 1, $per_page);
        $pending_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>同学管理 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'students.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <!-- 主内容 -->
  <main class="admin-main">
    <div class="admin-header">
      <h1>👥 同学管理</h1>
      <div class="admin-header-actions">
        <?php if ($action === 'list'): ?>
        <a href="students.php?action=add" class="btn-admin btn-admin-primary">+ 添加同学</a>
        <?php endif; ?>
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
    <!-- 筛选标签 -->
    <?php if (isset($pending_count) && $pending_count > 0): ?>
    <div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <a href="students.php?filter=all" class="btn-admin <?= $filter === 'all' ? 'btn-admin-primary' : '' ?>">全部</a>
      <a href="students.php?filter=pending" class="btn-admin <?= $filter === 'pending' ? 'btn-admin-primary' : '' ?>">⏳ 待审核 (<?= (int)$pending_count ?>)</a>
      <a href="students.php?filter=approved" class="btn-admin <?= $filter === 'approved' ? 'btn-admin-primary' : '' ?>">✓ 已通过</a>
    </div>
    <?php endif; ?>

    <!-- 列表视图 -->
    <?php if (empty($students)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">👥</div>
      <div class="empty-state-text">还没有同学档案，点击上方按钮添加</div>
    </div>
    <?php else: ?>
    <div class="data-table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>头像</th>
            <th>姓名</th>
            <th>城市</th>
            <th>座右铭</th>
            <th>审核状态</th>
            <th>联系方式</th>
            <th>排序</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
          <tr>
            <td>
              <img class="thumb" src="<?= e(get_avatar_url($s['avatar'])) ?>"
                   alt="<?= e($s['name']) ?>"
                   data-name="<?= e($s['name']) ?>">
            </td>
            <td><?= e($s['name']) ?></td>
            <td><?= e($s['city']) ?></td>
            <td><?= e(truncate($s['motto'], 30)) ?></td>
            <td>
              <?php if (!empty($s['is_approved'])): ?>
                <span style="color:#52c41a">✓ 已通过</span>
              <?php else: ?>
                <span style="color:#fa8c16">⏳ 待审核</span>
              <?php endif; ?>
            </td>
            <td><?= !empty($s['show_contact']) ? '<span style="color:#52c41a">✓ 公开</span>' : '<span style="color:#999">✗ 隐藏</span>' ?></td>
            <td><?= (int)$s['sort_order'] ?></td>
            <td class="actions">
              <a href="students.php?action=edit&id=<?= $s['id'] ?>" class="btn-admin">编辑</a>
              <?php if (empty($s['is_approved'])): ?>
              <button type="button" class="btn-admin" style="color:#52c41a;border-color:#52c41a"
                      onclick="approveStudent(<?= $s['id'] ?>)">通过</button>
              <?php else: ?>
              <button type="button" class="btn-admin" style="color:#fa8c16;border-color:#fa8c16"
                      onclick="revokeStudent(<?= $s['id'] ?>)">撤回</button>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('确定删除 ' + this.dataset.name + ' 吗？')" data-name="<?= e($s['name']) ?>">
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                <?php csrf_field(); ?>
                <button type="submit" class="btn-admin btn-admin-danger">删除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination_html($stu_pag, 'students.php?filter=' . urlencode($filter) . '&page={page}') ?>
    <?php endif; ?>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- 添加/编辑表单 -->
    <form method="POST" enctype="multipart/form-data" class="admin-form" id="batchForm">
      <h2><?= $action === 'add' ? '添加同学' : '编辑同学' ?></h2>
      <?php csrf_field(); ?>
      <input type="hidden" name="form_action" value="<?= $action ?>">
      <?php if ($action === 'edit' && $edit_data): ?>
      <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label>姓名 <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" required
               value="<?= e($edit_data['name'] ?? '') ?>" placeholder="同学姓名">
      </div>

      <div class="form-group">
        <label>头像</label>
        <?php if ($action === 'edit' && $edit_data && !empty($edit_data['avatar'])): ?>
        <img src="<?= e(get_avatar_url($edit_data['avatar'])) ?>" alt="当前头像"
             style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--color-border);margin-bottom:8px;display:block;"
             id="avatarPreview">
        <?php endif; ?>
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
               onchange="previewImage(this, 'avatarPreview')">
        <p class="form-hint">支持 JPG/PNG/GIF/WebP，最大 2MB</p>
      </div>

      <div class="form-group">
        <label>座右铭</label>
        <input type="text" name="motto" class="form-input"
               value="<?= e($edit_data['motto'] ?? '') ?>" placeholder="座右铭或个性签名">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>城市</label>
          <input type="text" name="city" class="form-input"
                 value="<?= e($edit_data['city'] ?? '') ?>" placeholder="现居城市">
        </div>
        <div class="form-group">
          <label>排序</label>
          <input type="number" name="sort_order" class="form-input"
                 value="<?= e($edit_data['sort_order'] ?? '0') ?>" placeholder="0">
          <p class="form-hint">数字越小越靠前</p>
        </div>
      </div>

      <div class="form-group">
        <label>手机</label>
        <input type="text" name="phone" class="form-input"
               value="<?= e($edit_data['phone'] ?? '') ?>" placeholder="手机号码">
      </div>

      <div class="form-group">
        <label>邮箱</label>
        <input type="email" name="email" class="form-input"
               value="<?= e($edit_data['email'] ?? '') ?>" placeholder="电子邮箱">
      </div>

      <div class="form-group">
        <label>微信</label>
        <input type="text" name="wechat" class="form-input"
               value="<?= e($edit_data['wechat'] ?? '') ?>" placeholder="微信号">
      </div>

      <div class="form-group">
        <label>个人简介</label>
        <textarea name="bio" class="form-input" placeholder="个人简介..."><?= e($edit_data['bio'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" name="show_contact" value="1" <?= (!isset($edit_data) || !empty($edit_data['show_contact'])) ? 'checked' : '' ?>>
          公开联系方式（手机、邮箱、微信）
        </label>
        <p class="form-hint">关闭后，前台弹窗将不显示该同学的联系方式</p>
      </div>

      <div class="form-actions">
        <a href="students.php" class="btn-admin">取消</a>
        <button type="submit" class="btn-admin btn-admin-primary"><?= $action === 'add' ? '添加' : '保存修改' ?></button>
      </div>
    </form>

    <?php endif; ?>
  </main>
</div>

<script>
function approveStudent(id) {
    var batchForm = document.getElementById('batchForm');
    if (!batchForm) return;
    var token = batchForm.querySelector('input[name="_token"]').value;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="_token" value="' + token + '">' +
                     '<input type="hidden" name="form_action" value="approve">' +
                     '<input type="hidden" name="approve_id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}

function revokeStudent(id) {
    var batchForm = document.getElementById('batchForm');
    if (!batchForm) return;
    var token = batchForm.querySelector('input[name="_token"]').value;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="_token" value="' + token + '">' +
                     '<input type="hidden" name="form_action" value="revoke">' +
                     '<input type="hidden" name="approve_id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
<script src="../assets/app.js"></script>
</body>
</html>
