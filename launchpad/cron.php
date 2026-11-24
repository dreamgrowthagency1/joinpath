<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

function cron_run(): void
{
    if (!lp_installed()) {
        return;
    }
    lp_migrate();
    // 1. retry joins that have not reached Meta yet
    $rows = db_all("SELECT id FROM joins WHERE sent_status='pending' AND matched=1 AND attempts<5 AND joined_at>? ORDER BY id LIMIT 100", [utc_plus(-6 * 86400)]);
    foreach ($rows as $r) {
        try {
            send_join_event((int)$r['id']);
        } catch (Throwable $e) {
            error_log('cron send: ' . $e->getMessage());
        }
    }
    // 2. hourly health run for live pages (only one page per run when many, round robin by oldest check)
    $due = db_all("SELECT p.* FROM pages p WHERE p.status='live' AND COALESCE((SELECT MIN(checked_at) FROM health_checks h WHERE h.page_id=p.id), '2000-01-01 00:00:00') < ? ORDER BY p.id LIMIT 3", [utc_plus(-3600)]);
    foreach ($due as $p) {
        try {
            health_run_all($p);
        } catch (Throwable $e) {
            error_log('cron health: ' . $e->getMessage());
        }
    }
    // 3. housekeeping once an hour
    if (rate_hit('cron:clean', 1, 3600)) {
        db_exec('DELETE FROM login_attempts WHERE created_at<?', [utc_plus(-86400)]);
        db_exec('DELETE FROM rate_limits WHERE window_start<?', [time() - 86400]);
        db_exec('DELETE FROM meta_events WHERE created_at<?', [utc_plus(-30 * 86400)]);
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    cron_run();
}
