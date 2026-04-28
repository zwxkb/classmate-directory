<?php
/**
 * 同学档案自助提交
 * 访客提交同学信息，根据审核设置决定是否需要管理员审核
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
$student_moderation = ($settings['student_moderation'] ?? '0') === '1';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>提交档案 · <?= e($site_name) ?></title>
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
      <li><a href="messages.php">留言板</a></li>
      <li><a href="submit.php" class="active">提交档案</a></li>
    </ul>
  </div>
</nav>

<section class="page-section">
  <div class="container">
    <h2 class="section-title">提交档案</h2>
    <p class="section-subtitle">留下你的信息，与同学们重逢</p>
    <div class="ornament">· ✦ · ✦ · ✦ ·</div>

    <div class="message-input-box vintage-border" style="max-width:600px;margin:0 auto;">
      <div class="form-group">
        <label>姓名 <span class="required">*</span></label>
        <input type="text" id="submitName" class="form-input" placeholder="你的姓名" maxlength="50" required>
      </div>

      <div class="form-group">
        <label>头像</label>
        <input type="file" id="submitAvatar" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="form-hint">支持 JPG/PNG/GIF/WebP，最大 2MB</p>
      </div>

      <div class="form-group">
        <label>城市</label>
        <input type="text" id="submitCity" class="form-input" placeholder="现居城市" maxlength="100">
      </div>

      <div class="form-group">
        <label>座右铭</label>
        <input type="text" id="submitMotto" class="form-input" placeholder="座右铭或个性签名" maxlength="200">
      </div>

      <div class="form-group">
        <label>手机</label>
        <input type="tel" id="submitPhone" class="form-input" placeholder="手机号码（如 138 8888 8888）" maxlength="20">
      </div>

      <div class="form-group">
        <label>邮箱</label>
        <input type="email" id="submitEmail" class="form-input" placeholder="电子邮箱" maxlength="100">
      </div>

      <div class="form-group">
        <label>微信</label>
        <input type="text" id="submitWechat" class="form-input" placeholder="微信号" maxlength="100">
      </div>

      <div class="form-group">
        <label>个人简介</label>
        <textarea id="submitBio" class="form-input" placeholder="简单介绍一下自己..." maxlength="500"></textarea>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" id="submitShowContact" checked>
          公开我的联系方式（手机、邮箱、微信）
        </label>
        <p style="margin:4px 0 0;font-size:0.8rem;color:var(--color-text-light);">关闭后，其他同学查看你的档案时将看不到联系方式</p>
      </div>

      <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
      <div class="message-input-footer">
        <button class="btn-submit" onclick="submitStudentProfile()">✦ 提交档案</button>
      </div>
      <div id="submitResult" style="margin-top:12px;display:none;"></div>
    </div>
  </div>
</section>

<!-- 页脚 -->
<footer class="site-footer">
  <span>✦</span> <?= e($site_name) ?> <span>✦</span>
</footer>

<script>
var moderationEnabled = <?= $student_moderation ? 'true' : 'false' ?>;
var submitting = false;

function submitStudentProfile() {
    if (submitting) return;

    var name = document.getElementById('submitName').value.trim();
    var phone = document.getElementById('submitPhone').value.trim();
    var email = document.getElementById('submitEmail').value.trim();
    var resultEl = document.getElementById('submitResult');

    // 校验姓名
    if (!name) {
        resultEl.style.display = 'block';
        resultEl.style.color = '#e74c3c';
        resultEl.textContent = '请填写姓名';
        return;
    }

    // 校验手机号格式（如果填写了，先去除空格）
    var phoneClean = phone.replace(/\s/g, '');
    if (phoneClean && !/^1[3-9]\d{9}$/.test(phoneClean)) {
        resultEl.style.display = 'block';
        resultEl.style.color = '#e74c3c';
        resultEl.textContent = '手机号格式不正确，请输入11位手机号码';
        return;
    }

    // 校验邮箱格式（如果填写了）
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        resultEl.style.display = 'block';
        resultEl.style.color = '#e74c3c';
        resultEl.textContent = '邮箱格式不正确';
        return;
    }

    submitting = true;
    var formData = new FormData();
    formData.append('_token', document.getElementById('csrfToken').value);
    formData.append('name', name);
    formData.append('city', document.getElementById('submitCity').value.trim());
    formData.append('motto', document.getElementById('submitMotto').value.trim());
    formData.append('phone', phone);
    formData.append('email', email);
    formData.append('wechat', document.getElementById('submitWechat').value.trim());
    formData.append('bio', document.getElementById('submitBio').value.trim());
    formData.append('show_contact', document.getElementById('submitShowContact').checked ? '1' : '');

    var avatarInput = document.getElementById('submitAvatar');
    if (avatarInput.files && avatarInput.files[0]) {
        formData.append('avatar', avatarInput.files[0]);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/submit.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            submitting = false;
            try {
                var res = JSON.parse(xhr.responseText);
                resultEl.style.display = 'block';
                if (res.success) {
                    resultEl.style.color = '#52c41a';
                    resultEl.textContent = res.message;
                    // 清空表单
                    document.getElementById('submitName').value = '';
                    document.getElementById('submitCity').value = '';
                    document.getElementById('submitMotto').value = '';
                    document.getElementById('submitPhone').value = '';
                    document.getElementById('submitEmail').value = '';
                    document.getElementById('submitWechat').value = '';
                    document.getElementById('submitBio').value = '';
                    document.getElementById('submitAvatar').value = '';
                    // 更新 CSRF token
                    document.getElementById('csrfToken').value = res.token || '';
                } else {
                    resultEl.style.color = '#e74c3c';
                    resultEl.textContent = res.message || '提交失败，请稍后重试';
                    if (res.token) {
                        document.getElementById('csrfToken').value = res.token;
                    }
                }
            } catch (e) {
                resultEl.style.display = 'block';
                resultEl.style.color = '#e74c3c';
                resultEl.textContent = '提交失败，请稍后重试';
            }
        }
    };
    xhr.send(formData);
}
</script>
<script src="assets/app.js"></script>
</body>
</html>
