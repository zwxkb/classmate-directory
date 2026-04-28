<?php
/**
 * AJAX 搜索同学接口
 * 支持按姓名/座右铭搜索、按城市筛选
 */

header('Content-Type: application/json; charset=utf-8');
// 前台和 API 同域，无需跨域支持

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

// 频率限制：session 级别 1 秒内只能请求 1 次
$search_rate_key = 'search_last';
if (isset($_SESSION[$search_rate_key]) && (time() - $_SESSION[$search_rate_key]) < 1) {
    json_response(['success' => false, 'message' => '请求过于频繁'], 429);
}

// 频率限制：IP 级别 10 秒内最多 10 次搜索
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_rate_file = sys_get_temp_dir() . '/cd_search_rate_' . md5($ip);
$fh = @fopen($ip_rate_file, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $ip_rate_data = @json_decode(stream_get_contents($fh), true);
    if ($ip_rate_data && (time() - $ip_rate_data['time']) < 10 && $ip_rate_data['count'] >= 10) {
        flock($fh, LOCK_UN);
        fclose($fh);
        json_response(['success' => false, 'message' => '搜索过于频繁，请稍后再试'], 429);
    }
    // 1% 概率清理该用户的过期限频文件
    if (mt_rand(1, 100) === 1 && $ip_rate_data && (time() - $ip_rate_data['time']) >= 10) {
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($ip_rate_file);
        $fh = null;
    }
}

$db = get_db();
if (!$db) {
    json_response(['success' => false, 'message' => '数据库未配置'], 500);
}

// 获取参数
$query = $_GET['q'] ?? '';
$city  = $_GET['city'] ?? '';

try {
    $where = "is_approved = 1";
    $params = [];

    if (!empty($query)) {
        $where .= " AND (name LIKE :q OR motto LIKE :q)";
        $params[':q'] = '%' . $query . '%';
    }
    if (!empty($city) && $city !== '全部') {
        $where .= " AND city = :city";
        $params[':city'] = $city;
    }

    // 限制返回数量
    $limit = 50;
    $sql = "SELECT * FROM " . table('students') . " WHERE {$where} ORDER BY sort_order ASC, id ASC LIMIT :limit";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // 格式化数据（转义输出防止 XSS，不暴露隐私信息）
    $result = [];
    foreach ($students as $s) {
        $result[] = [
            'id'         => (int)$s['id'],
            'name'       => htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'),
            'avatar_url' => get_avatar_url($s['avatar']),
            'city'       => htmlspecialchars($s['city'], ENT_QUOTES, 'UTF-8'),
            'motto'      => htmlspecialchars($s['motto'], ENT_QUOTES, 'UTF-8'),
        ];
    }

    // 记录频率限制（在响应之前）
    $_SESSION[$search_rate_key] = time();
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

    json_response(['success' => true, 'students' => $result]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => '搜索失败'], 500);
}
