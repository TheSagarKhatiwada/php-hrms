<?php
/**
 * Script to set default work_start_time and work_end_time in settings tables.
 * Run from project root: php scripts/set_default_work_times.php
 */
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

if (!isset($pdo) || !$pdo) {
    fwrite(STDERR, "No DB connection available\n");
    exit(1);
}

$defaults = [ 'work_start_time' => '09:30', 'work_end_time' => '18:00' ];

// Helper to upsert into a given table with columns (setting_key, value)
function upsert_setting($pdo, $table, $key, $value) {
    try {
        // Check if table exists
        $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]);
        if (!$st->fetch()) return false;

        $chk = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE setting_key = ? OR setting_key = ? LIMIT 1");
        $chk->execute([$key, $key]);
        $exists = $chk->fetchColumn() > 0;
        if ($exists) {
            $u = $pdo->prepare("UPDATE $table SET value = ?, setting_value = ? WHERE setting_key = ?");
            // Some tables use 'value' other use 'setting_value'
            $u->execute([$value, $value, $key]);
        } else {
            // Try to insert into common column names
            try {
                $i = $pdo->prepare("INSERT INTO $table (setting_key, value, created_at) VALUES (?, ?, NOW())");
                $i->execute([$key, $value]);
            } catch (Exception $e) {
                // fallback to setting_key/setting_value
                $i2 = $pdo->prepare("INSERT INTO $table (setting_key, setting_value, created_at) VALUES (?, ?, NOW())");
                $i2->execute([$key, $value]);
            }
        }
        return true;
    } catch (Exception $e) {
        // ignore
        return false;
    }
}

foreach ($defaults as $k => $v) {
    $res1 = upsert_setting($pdo, 'settings', $k, $v);
    $res2 = upsert_setting($pdo, 'system_settings', $k, $v);
    echo "Set $k => $v: settings_table=" . ($res1? 'ok':'skipped') . ", system_settings_table=" . ($res2? 'ok':'skipped') . "\n";
}

// Print current values (if available)
try {
    $q = $pdo->query("SELECT setting_key, COALESCE(value, setting_value) AS val FROM settings WHERE setting_key IN ('work_start_time','work_end_time') UNION SELECT setting_key, COALESCE(value, setting_value) AS val FROM system_settings WHERE setting_key IN ('work_start_time','work_end_time')");
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        echo "Current settings:\n";
        foreach ($rows as $r) {
            echo "  {$r['setting_key']} = {$r['val']}\n";
        }
    }
} catch (Exception $e) {
    // ignore
}

echo "Done.\n";
