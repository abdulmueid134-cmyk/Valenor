<?php
// =============================================================================
// VALENOR B2C E-COMMERCE PLATFORM — DATABASE INITIALIZATION SCRIPT
// Run via CLI: php database/init.php
// Or via Browser: http://localhost:8000/database/init.php
// =============================================================================

require_once __DIR__ . '/../config/config.php';

$isCli = (php_sapi_name() === 'cli');

function out($msg, $color = 'black') {
    global $isCli;
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo "<div style='font-family:monospace;padding:3px 0;color:{$color};'>" . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>Database Initialization — VALENOR</title>' .
         '<style>body{font-family:system-ui,sans-serif;padding:30px;background:#F8F3EB;color:#252525;line-height:1.5;}' .
         '.card{background:#fff;padding:25px;border-radius:8px;border:1px solid #E8DED2;max-width:800px;margin:0 auto;box-shadow:0 4px 12px rgba(0,0,0,0.05);}' .
         'h1{color:#8B6342;margin-top:0;}hr{border:0;border-top:1px solid #E8DED2;margin:20px 0;}' .
         'a.btn{display:inline-block;padding:10px 20px;background:#B8895B;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;margin-top:15px;}' .
         '</style></head><body><div class="card"><h1>VALENOR — Database Initialization</h1><hr>';
}

out("Starting database initialization for VALENOR E-Commerce...", "#8B6342");

try {
    // 1. Connect without database name first to create database if missing
    $serverDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $serverPdo = new PDO($serverDsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("✔ Database `" . DB_NAME . "` verified / created successfully.", "green");

    // 2. Connect to the database
    $pdo = getDB();

    // 3. Execute schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at {$schemaFile}");
    }
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    out("✔ Schema imported successfully (all 22 relational tables created).", "green");

    // 4. Execute seed.sql
    $seedFile = __DIR__ . '/seed.sql';
    if (!file_exists($seedFile)) {
        throw new Exception("seed.sql not found at {$seedFile}");
    }
    $seedSql = file_get_contents($seedFile);
    $pdo->exec($seedSql);
    out("✔ Seed data imported successfully (categories, 10 products, images, variants, coupons, banners, settings).", "green");

    // 5. Query and list all tables with row counts
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    out("\n------------------------------------------------------------");
    out(sprintf("%-30s | %s", "Table Name", "Record Count"));
    out("------------------------------------------------------------");

    $totalRows = 0;
    foreach ($tables as $table) {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $count = (int)$countStmt->fetchColumn();
        $totalRows += $count;
        out(sprintf("%-30s | %d rows", $table, $count), "#555");
    }
    out("------------------------------------------------------------");
    out(sprintf("Total Tables: %d | Total Initial Records: %d", count($tables), $totalRows), "#8B6342");
    out("✔ Database Initialization COMPLETED WITHOUT ERRORS!", "green");

    if (!$isCli) {
        echo '<hr><p>Default Super Admin: <strong>admin@valenor.com</strong> / Password: <strong>Admin@12345</strong></p>';
        echo '<p>Default Customer: <strong>customer@valenor.com</strong> / Password: <strong>Admin@12345</strong></p>';
        echo '<a class="btn" href="../index.php">Go to Customer Storefront &rarr;</a> ';
        echo '<a class="btn" style="background:#8B6342;" href="../admin/login.php">Go to Admin Login &rarr;</a>';
        echo '</div></body></html>';
    }

} catch (Exception $e) {
    out("✖ ERROR: " . $e->getMessage(), "red");
    if (!$isCli) {
        echo '</div></body></html>';
    }
    exit(1);
}

