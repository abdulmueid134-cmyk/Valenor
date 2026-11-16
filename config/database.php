<?php
// =============================================================================
// VALENOR B2C E-COMMERCE PLATFORM — DATABASE CONNECTION (PDO)
// =============================================================================

defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'valenor_ecommerce');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

/**
 * Returns singleton PDO database connection instance.
 *
 * @return PDO
 */
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally and show clean error page
            error_log('Database Connection Error: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:30px;background:#F8F3EB;color:#8B6342;border:1px solid #E8DED2;max-width:600px;margin:50px auto;border-radius:8px;">' .
                '<h2 style="margin-top:0;">Database Connection Notice</h2>' .
                '<p>Could not connect to the database <strong>' . htmlspecialchars(DB_NAME) . '</strong>.</p>' .
                '<p>Please ensure MySQL/MariaDB is running and run the database initialization script at <code>database/init.php</code>.</p>' .
                '<p style="font-size:12px;color:#777;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>' .
                '</div>');
        }
    }

    return $pdo;
}

