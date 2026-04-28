<?php
/**
 * 通用函数
 * XSS 转义、文件上传、分页等工具函数
 */

/**
 * HTML 实体转义，防止 XSS
 * @param string $str
 * @return string
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 获取站点设置
 * @param PDO $db
 * @param string $key 设置项键名
 * @param string $default 默认值
 * @return string
 */
function get_setting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT `value` FROM " . table('settings') . " WHERE `key` = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * 获取所有站点设置
 * @param PDO $db
 * @return array
 */
function get_all_settings($db) {
    $settings = [];
    try {
        $stmt = $db->prepare("SELECT `key`, `value` FROM " . table('settings'));
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
    } catch (Exception $e) {
        // 返回空数组
    }
    return $settings;
}

/**
 * 更新站点设置
 * @param PDO $db
 * @param string $key
 * @param string $value
 * @return bool
 */
function update_setting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO " . table('settings') . " (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value2");
    return $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
}

/**
 * 安全上传文件
 * @param array $file $_FILES 中的文件项
 * @param string $upload_dir 上传目录
 * @param array $allowed_types 允许的 MIME 类型
 * @param int $max_size 最大文件大小（字节）
 * @return array ['success' => bool, 'path' => string, 'message' => string]
 */
function safe_upload($file, $upload_dir, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $max_size = 2097152) {
    // 检查上传错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE  => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL    => '文件上传不完整',
            UPLOAD_ERR_NO_FILE    => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录不存在',
            UPLOAD_ERR_CANT_WRITE => '服务器写入失败',
        ];
        $msg = $errors[$file['error']] ?? '未知上传错误';
        return ['success' => false, 'path' => '', 'message' => $msg];
    }

    // 检查文件大小
    if ($file['size'] > $max_size) {
        $max_mb = round($max_size / 1048576, 1);
        return ['success' => false, 'path' => '', 'message' => "文件大小不能超过 {$max_mb}MB"];
    }

    // 检查 MIME 类型
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'path' => '', 'message' => '不支持的文件类型'];
    }

    // 验证图片内容（防止 polyglot 文件伪装）
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['success' => false, 'path' => '', 'message' => '文件不是有效的图片'];
    }
    $mime_map = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
    if (!isset($mime_map[$image_info[2]])) {
        return ['success' => false, 'path' => '', 'message' => '不支持的图片格式'];
    }

    // 生成唯一文件名
    $ext_map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $ext_map[$mime] ?? 'jpg';
    $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $filepath = rtrim($upload_dir, '/') . '/' . $filename;

    // 移动文件
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'path' => '', 'message' => '文件保存失败'];
    }

    return ['success' => true, 'path' => $filename, 'message' => '上传成功'];
}

/**
 * 格式化日期
 * @param string $date 日期字符串
 * @param string $format 输出格式
 * @return string
 */
function format_date($date, $format = 'Y.m.d') {
    if (empty($date)) return '';
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : $date;
}

/**
 * 截取文本
 * @param string $text
 * @param int $length
 * @return string
 */
function truncate($text, $length = 100) {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * 分页工具
 * @param int $total 总记录数
 * @param int $page 当前页码
 * @param int $per_page 每页数量
 * @return array ['total' => int, 'pages' => int, 'offset' => int, 'page' => int]
 */
function paginate($total, $page = 1, $per_page = 12) {
    $page = max(1, (int)$page);
    $pages = max(1, (int)ceil($total / $per_page));
    $offset = ($page - 1) * $per_page;
    return [
        'total'    => (int)$total,
        'pages'    => $pages,
        'offset'   => $offset,
        'page'     => $page,
        'per_page' => $per_page,
    ];
}

/**
 * 输出分页 HTML
 * @param array $pagination 分页数据
 * @param string $url_pattern URL 模式，用 {page} 表示页码占位符
 * @return string
 */
function pagination_html($pagination, $url_pattern = '?page={page}') {
    if ($pagination['pages'] <= 1) return '';

    $html = '<div class="pagination">';
    $html .= '<span class="pagination-info">共 ' . $pagination['total'] . ' 条</span>';
    $html .= '<div class="pagination-links">';

    // 上一页
    if ($pagination['page'] > 1) {
        $prev_url = str_replace('{page}', $pagination['page'] - 1, $url_pattern);
        $html .= '<a href="' . e($prev_url) . '" class="page-link">« 上一页</a>';
    }

    // 页码
    $start = max(1, $pagination['page'] - 2);
    $end = min($pagination['pages'], $pagination['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $url = str_replace('{page}', $i, $url_pattern);
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<a href="' . e($url) . '" class="page-link' . $active . '">' . $i . '</a>';
    }

    // 下一页
    if ($pagination['page'] < $pagination['pages']) {
        $next_url = str_replace('{page}', $pagination['page'] + 1, $url_pattern);
        $html .= '<a href="' . e($next_url) . '" class="page-link">下一页 »</a>';
    }

    $html .= '</div></div>';
    return $html;
}

/**
 * 输出 JSON 响应
 * @param array $data
 * @param int $code HTTP 状态码
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取默认头像 URL
 * @param string $avatar 头像文件名（可能为空）
 * @return string
 */
function get_avatar_url($avatar) {
    if (!empty($avatar)) {
        return 'uploads/' . $avatar;
    }
    // 使用首字母生成默认头像
    return 'https://ui-avatars.com/api/?name=' . urlencode('同学') . '&background=5D4037&color=fff&size=200';
}

/**
 * 获取默认图片 URL
 * @param string $photo 图片文件名
 * @return string
 */
function get_photo_url($photo) {
    if (!empty($photo)) {
        return 'uploads/' . $photo;
    }
    return 'https://picsum.photos/seed/default/600/375';
}
