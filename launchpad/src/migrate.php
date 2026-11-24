<?php
declare(strict_types=1);

/** Small, safe schema migrations. Runs once per request at most; cheap when already up to date. */
function lp_migrate(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $sqlite = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $pk = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        $v = (int)db_val("SELECT v FROM settings WHERE k='schema_v'");
    } catch (Throwable $e) {
        db_exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(60) NOT NULL PRIMARY KEY, v VARCHAR(255) NOT NULL)' . $tail);
        $v = 0;
    }
    $steps = [
        1 => [
            'ALTER TABLE accounts ADD COLUMN paid_until DATE NULL',
            'ALTER TABLE accounts ADD COLUMN plan_note VARCHAR(200) NOT NULL DEFAULT \'\'',
            'ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL',
            'ALTER TABLE users ADD COLUMN terms_version VARCHAR(20) NULL',
            'ALTER TABLE users ADD COLUMN terms_ip VARCHAR(64) NULL',
            'ALTER TABLE pages ADD COLUMN flag_note VARCHAR(300) NOT NULL DEFAULT \'\'',
            'CREATE TABLE IF NOT EXISTS abuse_reports (id ' . $pk . ', page_id INT NULL, slug VARCHAR(80) NOT NULL DEFAULT \'\', reason VARCHAR(30) NOT NULL, '
                . 'details TEXT NOT NULL, reporter_email VARCHAR(190) NOT NULL DEFAULT \'\', ip VARCHAR(64) NOT NULL DEFAULT \'\', status VARCHAR(16) NOT NULL DEFAULT \'open\', '
                . 'admin_note VARCHAR(300) NOT NULL DEFAULT \'\', created_at DATETIME NOT NULL, resolved_at DATETIME NULL)' . $tail,
            'CREATE INDEX ix_abuse_status ON abuse_reports (status, id)',
        ],
        2 => [
            'CREATE TABLE IF NOT EXISTS signup_requests (id ' . $pk . ', name VARCHAR(110) NOT NULL DEFAULT \'\', email VARCHAR(190) NOT NULL, business VARCHAR(150) NOT NULL DEFAULT \'\', '
                . 'pay_ref VARCHAR(190) NOT NULL DEFAULT \'\', ip VARCHAR(64) NOT NULL DEFAULT \'\', status VARCHAR(16) NOT NULL DEFAULT \'pending\', '
                . 'account_id INT NULL, created_at DATETIME NOT NULL, handled_at DATETIME NULL)' . $tail,
            'CREATE INDEX ix_signup_status ON signup_requests (status, id)',
        ],
    ];
    foreach ($steps as $n => $sqls) {
        if ($n <= $v) {
            continue;
        }
        foreach ($sqls as $sql) {
            try {
                db_exec($sql);
            } catch (Throwable $e) {
                $m = strtolower($e->getMessage());
                if (strpos($m, 'duplicate') === false && strpos($m, 'already exists') === false) {
                    throw $e;
                }
            }
        }
        if ($v === 0) {
            try {
                db_exec("INSERT INTO settings (k,v) VALUES ('schema_v',?)", [(string)$n]);
            } catch (Throwable $e) {
                db_exec("UPDATE settings SET v=? WHERE k='schema_v'", [(string)$n]);
            }
        } else {
            db_exec("UPDATE settings SET v=? WHERE k='schema_v'", [(string)$n]);
        }
        $v = $n;
    }
}
