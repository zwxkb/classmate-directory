<?php
/**
 * 管理员登出
 */
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// 未登录时直接跳转登录页，防止被诱导访问 logout URL 强制登出
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);
session_unset();
session_destroy();
header('Location: login.php');
exit;
