<?php
/**
 * 同学档案自助提交接口
 * 接收 AJAX POST 请求，插入同学档案到数据库
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$db = get_db();
if (!$db) {
    json_response(['success' => false, 'message' => '数据库未配置'], 500);
}

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => '非法请求'], 405);
}

// 验证 CSRF Token
if (!csrf_verify()) {
    json_response(['success' => false, 'message' => '安全验证失败，请刷新页面重试'], 403);
}

// 频率限制：基于 session 的 60 秒内只能提交一次
$rate_limit_key = 'student_last_submit';
if (isset($_SESSION[$rate_limit_key]) && (time() - $_SESSION[$rate_limit_key]) < 60) {
    $remaining = 60 - (time() - $_SESSION[$rate_limit_key]);
    json_response(['success' => false, 'message' => '操作过于频繁，请 ' . $remaining . ' 秒后再试'], 429);
}

// 频率限制：IP 级别 10 分钟内最多 3 次提交
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_rate_file = sys_get_temp_dir() . '/cd_stu_rate_' . md5($ip);
$stu_fh = @fopen($ip_rate_file, 'c+');
if ($stu_fh && flock($stu_fh, LOCK_EX)) {
    $ip_rate_data = @json_decode(stream_get_contents($stu_fh), true);
    // 1% 概率清理过期限频文件
    if (mt_rand(1, 100) === 1 && $ip_rate_data && (time() - $ip_rate_data['time']) >= 600) {
        flock($stu_fh, LOCK_UN);
        fclose($stu_fh);
        @unlink($ip_rate_file);
        $stu_fh = null;
    } else if ($ip_rate_data && (time() - $ip_rate_data['time']) < 600 && $ip_rate_data['count'] >= 3) {
        flock($stu_fh, LOCK_UN);
        fclose($stu_fh);
        json_response(['success' => false, 'message' => '提交过于频繁，请稍后再试'], 429);
    }
    // 不释放锁，提交成功后写入
}

// 获取参数
$name   = trim($_POST['name'] ?? '');
$city   = trim($_POST['city'] ?? '');
$motto  = trim($_POST['motto'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$email  = trim($_POST['email'] ?? '');
$wechat = trim($_POST['wechat'] ?? '');
$bio    = trim($_POST['bio'] ?? '');

// 参数验证
if (empty($name)) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '请填写姓名']);
}
if (mb_strlen($name) > 50) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '姓名不能超过 50 个字符']);
}
if (!empty($phone) && !preg_match('/^1[3-9]\d{9}$/', preg_replace('/\s/', '', $phone))) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '手机号格式不正确']);
}
$phone = preg_replace('/\s/', '', $phone);
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '邮箱格式不正确']);
}
if (mb_strlen($bio) > 500) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '个人简介不能超过 500 个字符']);
}

// 处理头像上传
$avatar = '';
if (!empty($_FILES['avatar']['name'])) {
    $upload_dir = __DIR__ . '/../uploads/avatars';
    $result = safe_upload($_FILES['avatar'], $upload_dir);
    if (!$result['success']) {
        if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
        json_response(['success' => false, 'message' => '头像上传失败：' . $result['message']]);
    }
    $avatar = $result['path'];
}

// 插入数据库
try {
    // 查询档案审核设置
    $moderation = false;
    try {
        $mod_stmt = $db->prepare("SELECT `value` FROM " . table('settings') . " WHERE `key` = :k LIMIT 1");
        $mod_stmt->execute([':k' => 'student_moderation']);
        $mod_row = $mod_stmt->fetch();
        if ($mod_row && $mod_row['value'] === '1') {
            $moderation = true;
        }
    } catch (Exception $e) {
        // 设置表查询失败不影响提交，默认关闭审核
    }

    $is_approved = $moderation ? 0 : 1;

    $show_contact = isset($_POST['show_contact']) ? 1 : 0;
    $stmt = $db->prepare("INSERT INTO " . table('students') . " (name, avatar, motto, city, phone, email, wechat, bio, show_contact, is_approved) VALUES (:name, :avatar, :motto, :city, :phone, :email, :wechat, :bio, :show_contact, :approved)");
    $stmt->execute([
        ':name'          => $name,
        ':avatar'        => $avatar,
        ':motto'         => $motto,
        ':city'          => $city,
        ':phone'         => $phone,
        ':email'         => $email,
        ':wechat'        => $wechat,
        ':bio'           => $bio,
        ':show_contact'  => $show_contact,
        ':approved'      => $is_approved,
    ]);

    // 记录提交时间用于频率限制
    $_SESSION[$rate_limit_key] = time();

    // 记录 IP 级频率
    if ($stu_fh) {
        if ($ip_rate_data && (time() - $ip_rate_data['time']) < 600) {
            $ip_rate_data['count']++;
        } else {
            $ip_rate_data = ['count' => 1, 'time' => time()];
        }
        ftruncate($stu_fh, 0);
        rewind($stu_fh);
        fwrite($stu_fh, json_encode($ip_rate_data));
        flock($stu_fh, LOCK_UN);
        fclose($stu_fh);
    }

    $msg = $moderation ? '提交成功，等待管理员审核' : '提交成功';
    json_response(['success' => true, 'message' => $msg, 'id' => $db->lastInsertId(), 'token' => csrf_token()]);
} catch (Exception $e) {
    if ($stu_fh) { flock($stu_fh, LOCK_UN); fclose($stu_fh); }
    json_response(['success' => false, 'message' => '提交失败，请稍后重试'], 500);
}
