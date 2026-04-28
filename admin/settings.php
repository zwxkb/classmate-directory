<?php
/**
 * 管理后台 - 站点设置
 * 修改学校名、班级名、标语、封面图等
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_login();

$db = get_db();
$upload_dir = __DIR__ . '/../uploads';
$message = '';
$messageType = '';

// 获取当前设置
$settings = get_all_settings($db);

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message = '安全验证失败';
        $messageType = 'error';
    } else {
        try {
            // 处理封面图上传
            $cover_photo = $settings['cover_photo'] ?? '';
            if (!empty($_FILES['cover_photo']['name'])) {
                $result = safe_upload($_FILES['cover_photo'], $upload_dir, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], 5242880);
                if ($result['success']) {
                    $cover_photo = $result['path'];
                } else {
                    $message = '封面上传失败：' . $result['message'];
                    $messageType = 'error';
                }
            }

            if (empty($message)) {
                // 校验毕业年份格式
                $graduation_year = trim($_POST['graduation_year'] ?? '');
                if (!empty($graduation_year) && !preg_match('/^(19|20)\d{2}$/', $graduation_year)) {
                    $message = '毕业年份格式不正确，请填写 4 位数字（如 2010）';
                    $messageType = 'error';
                }

                if (empty($message)) {
                $fields = [
                    'site_name'           => trim($_POST['site_name'] ?? ''),
                    'school_name'         => trim($_POST['school_name'] ?? ''),
                    'class_name'          => trim($_POST['class_name'] ?? ''),
                    'graduation_year'     => trim($_POST['graduation_year'] ?? ''),
                    'cover_photo'         => $cover_photo,
                    'slogan'              => trim($_POST['slogan'] ?? ''),
                    'message_moderation'  => isset($_POST['message_moderation']) ? '1' : '0',
                    'student_moderation'  => isset($_POST['student_moderation']) ? '1' : '0',
                ];

                foreach ($fields as $key => $value) {
                    update_setting($db, $key, $value);
                }

                // 更新本地变量
                $settings = array_merge($settings, $fields);
                $message = '设置已保存';
                $messageType = 'success';
                } // end graduation_year validation
            }
        } catch (Exception $e) {
            error_log('设置保存失败: ' . $e->getMessage());
            $message = '保存失败，请稍后重试';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>站点设置 · 管理后台</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700;900&family=Noto+Sans+SC:wght@300;400;500;700&family=Ma+Shan+Zheng&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">
  <?php $active_page = 'settings.php'; require_once __DIR__ . '/sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-header">
      <h1>⚙️ 站点设置</h1>
      <div class="admin-header-actions">
        <a href="logout.php" class="btn-admin">退出登录</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <h2>基本信息</h2>
      <?php csrf_field(); ?>

      <div class="form-group">
        <label>站点名称</label>
        <input type="text" name="site_name" class="form-input"
               value="<?= e($settings['site_name'] ?? '那些年 · 同学录') ?>" placeholder="站点名称">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>学校名称</label>
          <input type="text" name="school_name" class="form-input"
                 value="<?= e($settings['school_name'] ?? '') ?>" placeholder="如：春晖中学">
        </div>
        <div class="form-group">
          <label>班级名称</label>
          <input type="text" name="class_name" class="form-input"
                 value="<?= e($settings['class_name'] ?? '') ?>" placeholder="如：高三（2）班">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>毕业年份</label>
          <input type="text" name="graduation_year" class="form-input"
                 value="<?= e($settings['graduation_year'] ?? '') ?>" placeholder="如：2010">
        </div>
        <div class="form-group">
          <label>站点标语</label>
          <input type="text" name="slogan" class="form-input"
                 value="<?= e($settings['slogan'] ?? '') ?>" placeholder="如：时光不老，我们不散。">
        </div>
      </div>

      <div class="form-group">
        <label>封面大合照</label>
        <?php if (!empty($settings['cover_photo'])): ?>
        <img src="uploads/<?= e($settings['cover_photo']) ?>" alt="当前封面"
             style="max-width:400px;border-radius:12px;border:2px solid var(--color-border);margin-bottom:8px;display:block;"
             id="coverPreview"
             onerror="this.style.display='none'">
        <?php endif; ?>
        <input type="file" name="cover_photo" accept="image/jpeg,image/png,image/gif,image/webp"
               onchange="previewImage(this, 'coverPreview')">
        <p class="form-hint">建议尺寸 720×420，支持 JPG/PNG/GIF/WebP，最大 5MB</p>
      </div>

      <h2 style="margin-top:32px;">互动设置</h2>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:400;">
          <input type="checkbox" name="message_moderation" value="1"
                 <?= ($settings['message_moderation'] ?? '0') === '1' ? 'checked' : '' ?>
                 style="width:18px;height:18px;">
          开启留言审核（新留言需管理员审核后才会公开显示）
        </label>
        <p class="form-hint">关闭时，访客留言会立即公开展示。开启后，新留言需要管理员在后台审核通过后才会显示。</p>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:400;">
          <input type="checkbox" name="student_moderation" value="1"
                 <?= ($settings['student_moderation'] ?? '0') === '1' ? 'checked' : '' ?>
                 style="width:18px;height:18px;">
          开启档案自助提交审核（访客提交的档案需管理员审核后才会公开显示）
        </label>
        <p class="form-hint">关闭时，访客提交的档案会立即公开展示。开启后，新提交的档案需要管理员在后台审核通过后才会显示。</p>
      </div>

      <div class="form-actions">
        <a href="index.php" class="btn-admin">返回仪表盘</a>
        <button type="submit" class="btn-admin btn-admin-primary">保存设置</button>
      </div>
    </form>
  </main>
</div>

<script src="../assets/app.js"></script>
</body>
</html>
