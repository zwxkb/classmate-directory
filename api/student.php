<?php
/**
 * AJAX 获取同学详情接口
 * 仅返回公开信息 + 联系方式（按需获取，防止批量泄露）
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// 频率限制：session 级别 2 秒内只能请求 1 次
$rate_key = 'student_detail_last';
if (isset($_SESSION[$rate_key]) && (time() - $_SESSION[$rate_key]) < 2) {
    json_response(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
}

// 频率限制：IP 级别 10 秒内只能请求 5 次
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_rate_file = sys_get_temp_dir() . '/cd_rate_' . md5($ip);
$fh = @fopen($ip_rate_file, 'c+');
$ip_rate_data = null;
if ($fh && flock($fh, LOCK_EX)) {
    $ip_rate_data = @json_decode(stream_get_contents($fh), true);
    // 1% 概率清理过期限频文件
    if (mt_rand(1, 100) === 1 && $ip_rate_data && (time() - $ip_rate_data['time']) >= 10) {
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($ip_rate_file);
        $fh = null;
        $ip_rate_data = null;
    } else if ($ip_rate_data && (time() - $ip_rate_data['time']) < 10 && $ip_rate_data['count'] >= 5) {
        flock($fh, LOCK_UN);
        fclose($fh);
        json_response(['success' => false, 'message' => '请求过于频繁，请稍后再试'], 429);
    }
    // 不释放锁，后续成功时写入后释放
}

$db = get_db();
if (!$db) {
    json_response(['success' => false, 'message' => '数据库未配置'], 500);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'message' => '参数错误'], 400);
}

try {
    $stmt = $db->prepare("SELECT * FROM " . table('students') . " WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $s = $stmt->fetch();

    if (!$s || empty($s['is_approved'])) {
        json_response(['success' => false, 'message' => '同学不存在'], 404);
    }

    // 记录频率限制
    $_SESSION[$rate_key] = time();
    if ($fh) {
        if ($ip_rate_data && (time() - $ip_rate_data['time']) < 10) {
            $ip_rate_data['count']++;
        } else {
            $ip_rate_data = ['count' => 1, 'time' => time()];
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($ip_rate_data));
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    // 根据管理员的设置决定是否返回联系方式
    $show_contact = !empty($s['show_contact']);
    $student = [
        'id'           => (int)$s['id'],
        'name'         => htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'),
        'avatar_url'   => get_avatar_url($s['avatar']),
        'city'         => htmlspecialchars($s['city'], ENT_QUOTES, 'UTF-8'),
        'motto'        => htmlspecialchars($s['motto'], ENT_QUOTES, 'UTF-8'),
        'bio'          => htmlspecialchars($s['bio'], ENT_QUOTES, 'UTF-8'),
        'show_contact' => $show_contact,
    ];
    if ($show_contact) {
        $student['phone']  = htmlspecialchars($s['phone'], ENT_QUOTES, 'UTF-8');
        $student['email']  = htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8');
        $student['wechat'] = htmlspecialchars($s['wechat'], ENT_QUOTES, 'UTF-8');
    }

    json_response([
        'success' => true,
        'student' => $student,
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => '获取失败'], 500);
}
