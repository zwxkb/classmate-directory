<?php
/**
 * 数据库连接
 * 基于 PDO 的 MySQL 连接，防 SQL 注入
 */

require_once __DIR__ . '/csrf.php';

/**
 * 获取数据库连接实例（单例模式）
 * @return PDO
 */
function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // 读取配置文件
    $config_file = __DIR__ . '/../config.php';
    if (!file_exists($config_file)) {
        // 安装向导模式下，返回 null
        return null;
    }

    $config = require $config_file;

    try {
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
    } catch (PDOException $e) {
        // 不向用户暴露错误详情，记录到日志
        error_log('数据库连接失败: ' . $e->getMessage());
        die('数据库连接失败，请联系管理员');
    }

    // 自动迁移：确保 students 表有 show_contact 字段（仅执行一次，flock 互斥）
    $migrate_flag = sys_get_temp_dir() . '/cd_migrated_' . md5(__DIR__ . '/../config.php' . ($config['db_name'] ?? ''));
    $mfh = @fopen($migrate_flag, 'c+');
    if ($mfh && flock($mfh, LOCK_EX)) {
        $already = (int)stream_get_contents($mfh);
        if (!$already) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM " . table('students') . " LIKE 'show_contact'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE " . table('students') . " ADD COLUMN `show_contact` TINYINT(1) NOT NULL DEFAULT 0 AFTER `bio`");
                }
                ftruncate($mfh, 0);
                rewind($mfh);
                fwrite($mfh, '1');
            } catch (Exception $e) {
                error_log('自动迁移失败: ' . $e->getMessage());
            }
        }
        flock($mfh, LOCK_UN);
        fclose($mfh);
    }

    // 自动迁移：确保 messages 表有 is_approved 字段（仅执行一次，flock 互斥）
    $migrate_flag_approved = sys_get_temp_dir() . '/cd_migrated_approved_' . md5(__DIR__ . '/../config.php' . ($config['db_name'] ?? ''));
    $mfh2 = @fopen($migrate_flag_approved, 'c+');
    if ($mfh2 && flock($mfh2, LOCK_EX)) {
        $already2 = (int)stream_get_contents($mfh2);
        if (!$already2) {
            try {
                $cols2 = $pdo->query("SHOW COLUMNS FROM " . table('messages') . " LIKE 'is_approved'")->fetchAll();
                if (empty($cols2)) {
                    $pdo->exec("ALTER TABLE " . table('messages') . " ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 1 AFTER `content`");
                }
                ftruncate($mfh2, 0);
                rewind($mfh2);
                fwrite($mfh2, '1');
            } catch (Exception $e) {
                error_log('自动迁移失败(is_approved): ' . $e->getMessage());
            }
        }
        flock($mfh2, LOCK_UN);
        fclose($mfh2);
    }

    // 自动迁移：确保 students 表有 is_approved 字段（仅执行一次，flock 互斥）
    $migrate_flag_stu_approved = sys_get_temp_dir() . '/cd_migrated_stu_approved_' . md5(__DIR__ . '/../config.php' . ($config['db_name'] ?? ''));
    $mfh3 = @fopen($migrate_flag_stu_approved, 'c+');
    if ($mfh3 && flock($mfh3, LOCK_EX)) {
        $already3 = (int)stream_get_contents($mfh3);
        if (!$already3) {
            try {
                $cols3 = $pdo->query("SHOW COLUMNS FROM " . table('students') . " LIKE 'is_approved'")->fetchAll();
                if (empty($cols3)) {
                    $pdo->exec("ALTER TABLE " . table('students') . " ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_contact`");
                }
                ftruncate($mfh3, 0);
                rewind($mfh3);
                fwrite($mfh3, '1');
            } catch (Exception $e) {
                error_log('自动迁移失败(students is_approved): ' . $e->getMessage());
            }
        }
        flock($mfh3, LOCK_UN);
        fclose($mfh3);
    }

    return $pdo;
}

/**
 * 获取表名（加上前缀）
 * @param string $table 原始表名
 * @return string 带前缀的表名
 */
function table($table) {
    static $prefix = null;
    if ($prefix === null) {
        $config_file = __DIR__ . '/../config.php';
        if (!file_exists($config_file)) {
            return $table;
        }
        $config = require $config_file;
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $config['db_prefix']);
    }
    return $prefix . $table;
}
