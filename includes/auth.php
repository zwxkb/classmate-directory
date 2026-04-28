<?php
/**
 * 认证函数
 * 管理后台登录验证
 */

require_once __DIR__ . '/db.php';

/**
 * 检查管理员是否已登录
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * 要求登录（未登录则跳转到登录页）
 * 包含 1 小时空闲超时检测
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    $timeout = 3600; // 1 小时空闲超时
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['admin_last_activity'] = time(); // 活动续期
}

/**
 * 管理员登录
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function admin_login($username, $password) {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => '数据库连接失败'];
    }

    $stmt = $db->prepare("SELECT * FROM " . table('users') . " WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => '用户名或密码错误'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => '用户名或密码错误'];
    }

    // 登录成功，重新生成 Session ID 防止会话固定攻击
    session_regenerate_id(true);

    // 设置 session
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_last_activity'] = time();

    return ['success' => true, 'message' => '登录成功'];
}

/**
 * 管理员登出
 */
function admin_logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
