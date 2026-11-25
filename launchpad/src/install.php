<?php
declare(strict_types=1);

require_once LP_SRC . '/ui.php';

function install_page(string $inner, string $msg = ''): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up · JoinPath</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600&display=swap">'
        . '<link rel="stylesheet" href="/assets/app.css?v=3"></head><body><div class="auth"><div class="card" style="max-width:560px">'
        . '<div class="h" style="font-size:22px;margin-bottom:20px">JoinPath setup</div>'
        . ($msg !== '' ? '<div class="flash bad" role="alert" style="margin-bottom:20px">' . e($msg) . '</div>' : '') . $inner . '</div></div></body></html>';
}

function install_run_schema(PDO $pdo): void
{
    $sql = (string)file_get_contents(LP_ROOT . '/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $isTable = stripos($stmt, 'CREATE TABLE') === 0;
        if ($isTable) {
            $stmt = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $stmt);
            if ($mysql) {
                $stmt .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            }
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // safe to re-run: an index that already exists is fine
            if (!$isTable && (stripos($e->getMessage(), 'Duplicate key name') !== false || stripos($e->getMessage(), 'already exists') !== false)) {
                continue;
            }
            throw $e;
        }
    }
}

function install_main(): void
{
    session_boot();
    $keyFile = LP_ROOT . '/install.key';
    $code = is_file($keyFile) ? trim((string)file_get_contents($keyFile)) : '';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'dreamgrowthtrack.com');
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    if (strpos($path, '/assets/') === 0) {
        http_response_code(404);
        return;
    }
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    if ($code === '') {
        install_page('<p>The install code file is missing. Upload <span class="mono">launchpad/install.key</span> again and reload.</p>');
        return;
    }
    $v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'account' => 'Dream Growth Agency', 'email' => '', 'app_url' => 'https://' . preg_replace('/[^a-z0-9.\-]/i', '', $host), 'pages_domain' => 'joinpath.in'];
    $err = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        foreach (array_keys($v) as $k) {
            $v[$k] = trim((string)($_POST[$k] ?? $v[$k]));
        }
        $pass = (string)($_POST['db_pass'] ?? '');
        $adminPw = (string)($_POST['admin_pw'] ?? '');
        try {
            if (!hash_equals($code, trim((string)($_POST['code'] ?? '')))) {
                sleep(2);
                throw new RuntimeException('Install code is not right.');
            }
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid admin email.');
            }
            if (strlen($adminPw) < 10) {
                throw new RuntimeException('Admin password needs at least 10 characters.');
            }
            if (!preg_match('#^https://[a-z0-9.\-]+$#i', $v['app_url']) || !preg_match('/^[a-z0-9.\-]+$/i', $v['pages_domain'])) {
                throw new RuntimeException('Check the app address and pages domain.');
            }
            foreach (['curl', 'openssl', 'pdo_mysql'] as $ext) {
                if (!extension_loaded($ext)) {
                    throw new RuntimeException('PHP extension missing: ' . $ext);
                }
            }
            if (!in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
                throw new RuntimeException('This server cannot do AES-256-GCM.');
            }
            try {
                $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e) {
                throw new RuntimeException('Could not connect to the database. Check the name, user and password. (' . $e->getMessage() . ')');
            }
            install_run_schema($pdo);
            if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                throw new RuntimeException('This database already has users. Use a fresh, empty database.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO accounts (name,status,created_at) VALUES (?,?,?)')->execute([$v['account'] ?: 'Admin', 'active', $now]);
            $aid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO users (account_id,email,name,password_hash,is_admin,lang,created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$aid, strtolower($v['email']), 'Admin', password_hash($adminPw, PASSWORD_DEFAULT), 1, 'en', $now]);
            $config = [
                'db' => ['dsn' => 'mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', 'user' => $v['db_user'], 'pass' => $pass],
                'app_key' => base64_encode(random_bytes(32)),
                'cron_key' => bin2hex(random_bytes(16)),
                'app_url' => rtrim($v['app_url'], '/'),
                'pages_domain' => strtolower($v['pages_domain']),
                'timezone' => 'Asia/Kolkata',
                'graph_version' => 'v25.0',
            ];
            $php = "<?php\nreturn " . var_export($config, true) . ";\n";
            $tmp = LP_ROOT . '/config.php.tmp';
            if (file_put_contents($tmp, $php, LOCK_EX) === false) {
                throw new RuntimeException('Could not write config.php. Check folder permissions.');
            }
            @chmod($tmp, 0600);
            rename($tmp, LP_ROOT . '/config.php');
            @unlink($keyFile);
            install_page('<h1 style="font-size:24px;margin-bottom:12px">All set</h1><p>JoinPath is installed and the install code is deleted.</p><p><a class="btn" href="/login">Go to login</a></p>'
                . '<p class="muted">Next: add a cron job (every minute) with the command <span class="mono">/usr/local/bin/php ' . e(LP_ROOT) . '/cron.php</span></p>');
            return;
        } catch (Throwable $e) {
            $err = $e instanceof RuntimeException ? $e->getMessage() : 'Setup failed: ' . $e->getMessage();
        }
    }
    $f = function (string $label, string $name, string $val, string $type = 'text', string $help = '') {
        return '<div class="field"><label for="i_' . $name . '">' . e($label) . '</label><input id="i_' . $name . '" name="' . $name . '" type="' . $type . '" value="' . ($type === 'password' ? '' : e($val)) . '" autocomplete="off" required>'
            . ($help !== '' ? '<div class="help">' . $help . '</div>' : '') . '</div>';
    };
    install_page('<form method="post">' . csrf_field()
        . $f('Install code', 'code', '', 'password', 'From the file launchpad/install.key (or the one I gave you).')
        . '<h2 style="margin:8px 0 12px;font-size:17px">Database</h2>'
        . $f('Host', 'db_host', $v['db_host']) . $f('Database name', 'db_name', $v['db_name'], 'text', 'Full name, e.g. dreamgro_launchpad') . $f('Database user', 'db_user', $v['db_user']) . $f('Database password', 'db_pass', '', 'password')
        . '<h2 style="margin:8px 0 12px;font-size:17px">Your admin login</h2>'
        . $f('Business name', 'account', $v['account']) . $f('Email', 'email', $v['email'], 'email') . $f('Password', 'admin_pw', '', 'password', 'At least 10 characters.')
        . '<h2 style="margin:8px 0 12px;font-size:17px">Addresses</h2>'
        . $f('App address', 'app_url', $v['app_url']) . $f('Pages domain', 'pages_domain', $v['pages_domain'])
        . '<button class="btn" type="submit" style="width:100%">Install</button></form>', $err);
}
