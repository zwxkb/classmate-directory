<?php
/**
 * CSRF 防护
 * 生成和验证 CSRF Token
 */

// Session 安全配置（仅在 session 未启动时设置）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // HTTPS 环境下启用 secure 标志
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)$_SERVER['SERVER_PORT'] === 443) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * 生成 CSRF Token 并存入 session
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 输出隐藏的 CSRF input 字段
 */
function csrf_field() {
    echo '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * 验证 CSRF Token
 * @return bool
 */
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    // 验证成功后轮转 Token，防止 Token 泄露后被重放
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}
