<?php
/**
 * 提交留言接口
 * 接收 AJAX POST 请求，插入留言到数据库
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

// 频率限制：基于 session 的 30 秒内只能提交一次留言
$rate_limit_key = 'message_last_submit';
if (isset($_SESSION[$rate_limit_key]) && (time() - $_SESSION[$rate_limit_key]) < 30) {
    $remaining = 30 - (time() - $_SESSION[$rate_limit_key]);
    json_response(['success' => false, 'message' => '操作过于频繁，请 ' . $remaining . ' 秒后再试'], 429);
}

// 频率限制：IP 级别 60 秒内最多 5 条留言
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_rate_file = sys_get_temp_dir() . '/cd_msg_rate_' . md5($ip);
$msg_fh = @fopen($ip_rate_file, 'c+');
if ($msg_fh && flock($msg_fh, LOCK_EX)) {
    $ip_rate_data = @json_decode(stream_get_contents($msg_fh), true);
    // 1% 概率清理过期限频文件
    if (mt_rand(1, 100) === 1 && $ip_rate_data && (time() - $ip_rate_data['time']) >= 60) {
        flock($msg_fh, LOCK_UN);
        fclose($msg_fh);
        @unlink($ip_rate_file);
        $msg_fh = null;
    } else if ($ip_rate_data && (time() - $ip_rate_data['time']) < 60 && $ip_rate_data['count'] >= 5) {
        flock($msg_fh, LOCK_UN);
        fclose($msg_fh);
        json_response(['success' => false, 'message' => '留言过于频繁，请稍后再试'], 429);
    }
    // 不释放锁，留言成功后写入
}

// 获取参数
$name    = trim($_POST['student_name'] ?? '');
$content = trim($_POST['content'] ?? '');

// 参数验证
if (empty($name)) {
    json_response(['success' => false, 'message' => '请填写你的名字']);
}
if (mb_strlen($name) > 50) {
    json_response(['success' => false, 'message' => '名字不能超过 50 个字符']);
}
if (empty($content)) {
    json_response(['success' => false, 'message' => '请写下你的留言']);
}
if (mb_strlen($content) > 1000) {
    json_response(['success' => false, 'message' => '留言不能超过 1000 个字符']);
}

// 插入数据库
try {
    // 查询留言审核设置
    $moderation = false;
    try {
        $mod_stmt = $db->prepare("SELECT `value` FROM " . table('settings') . " WHERE `key` = :k LIMIT 1");
        $mod_stmt->execute([':k' => 'message_moderation']);
        $mod_row = $mod_stmt->fetch();
        if ($mod_row && $mod_row['value'] === '1') {
            $moderation = true;
        }
    } catch (Exception $e) {
        // 设置表查询失败不影响留言提交，默认关闭审核
    }

    $is_approved = $moderation ? 0 : 1;

    $stmt = $db->prepare("INSERT INTO " . table('messages') . " (student_name, content, is_approved) VALUES (:name, :content, :approved)");
    $stmt->execute([
        ':name'     => $name,
        ':content'  => $content,
        ':approved' => $is_approved,
    ]);

    // 记录提交时间用于频率限制
    $_SESSION['message_last_submit'] = time();

    // 记录 IP 级频率
    if ($msg_fh) {
        if ($ip_rate_data && (time() - $ip_rate_data['time']) < 60) {
            $ip_rate_data['count']++;
        } else {
            $ip_rate_data = ['count' => 1, 'time' => time()];
        }
        ftruncate($msg_fh, 0);
        rewind($msg_fh);
        fwrite($msg_fh, json_encode($ip_rate_data));
        flock($msg_fh, LOCK_UN);
        fclose($msg_fh);
    }

    $msg = $moderation ? '留言已提交，等待管理员审核' : '留言成功';
    json_response(['success' => true, 'message' => $msg, 'id' => $db->lastInsertId()]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => '留言失败，请稍后重试'], 500);
}
