<?php
function app_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
        $local = $cfg['local_override'] ?? null;
        if ($local && file_exists($local)) {
            $cfg = array_replace_recursive($cfg, require $local);
        }
    }
    return $cfg;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = app_config();
        date_default_timezone_set($cfg['timezone'] ?? 'Asia/Dhaka');
        if (($cfg['db']['driver'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . $cfg['db']['path']);
        } else {
            $d = $cfg['db'];
            $pdo = new PDO(
                "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4",
                $d['user'], $d['pass']
            );
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        init_schema($pdo);
    }
    return $pdo;
}

function table_columns(PDO $pdo, string $table): array {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll();
        return array_column($rows, 'Field');
    }
    $rows = $pdo->query("PRAGMA table_info(`$table`)")->fetchAll();
    return array_column($rows, 'name');
}

function init_schema(PDO $pdo): void {
    $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $ai = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id $ai, username VARCHAR(60) UNIQUE NOT NULL, pass_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    // v2 model: one record = one website (domain + hosting dates together)
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id $ai, client_name VARCHAR(120) NOT NULL, client_phone VARCHAR(30) DEFAULT '',
        name VARCHAR(190) NOT NULL,
        domain_expiry DATE NULL, hosting VARCHAR(120) DEFAULT '', hosting_expiry DATE NULL,
        provider VARCHAR(120) DEFAULT '', amount DECIMAL(12,2) DEFAULT 0, notes VARCHAR(255) DEFAULT '',
        type VARCHAR(10) DEFAULT '', expiry_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(60) PRIMARY KEY, v TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reminder_log (
        id $ai, service_id INT NOT NULL, kind VARCHAR(10) DEFAULT 'domain',
        expiry_date DATE NOT NULL, days_before INT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    // If this is an old (v1) database, add the new columns
    $cols = table_columns($pdo, 'services');
    foreach (['domain_expiry' => 'DATE NULL', 'hosting' => "VARCHAR(120) DEFAULT ''", 'hosting_expiry' => 'DATE NULL'] as $c => $def) {
        if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE services ADD COLUMN $c $def");
    }
    if ($isMysql) { try { $pdo->exec("ALTER TABLE services MODIFY expiry_date DATE NULL"); } catch (Throwable $e) {} }
    $lcols = table_columns($pdo, 'reminder_log');
    if (!in_array('kind', $lcols, true)) $pdo->exec("ALTER TABLE reminder_log ADD COLUMN kind VARCHAR(10) DEFAULT 'domain'");

    // Default settings
    $defaults = [
        'admin_email' => '',
        'from_email'  => '',
        'cron_key'    => bin2hex(random_bytes(12)),
        'remind_days' => '7,3,1,0',
    ];
    $stmt = $pdo->prepare($isMysql
        ? "INSERT IGNORE INTO settings (k, v) VALUES (?, ?)"
        : "INSERT OR IGNORE INTO settings (k, v) VALUES (?, ?)");
    foreach ($defaults as $k => $v) { $stmt->execute([$k, $v]); }

    migrate_v1_to_v2($pdo);
}

// v1: domain and hosting were separate rows -> v2: merge paired rows into one record (runs once)
function migrate_v1_to_v2(PDO $pdo): void {
    $s = $pdo->prepare("SELECT v FROM settings WHERE k = 'model_v2'");
    $s->execute();
    $row = $s->fetch();
    if ($row && $row['v'] === '1') return;

    $hasOld = (int)$pdo->query("SELECT COUNT(*) c FROM services WHERE type IN ('domain','hosting') AND domain_expiry IS NULL AND hosting_expiry IS NULL")->fetch()['c'];
    if ($hasOld > 0) {
        $domains  = $pdo->query("SELECT * FROM services WHERE type = 'domain'")->fetchAll();
        $hostings = $pdo->query("SELECT * FROM services WHERE type = 'hosting'")->fetchAll();
        $hostByName = [];
        foreach ($hostings as $h) $hostByName[$h['name']][] = $h;
        $upd = $pdo->prepare("UPDATE services SET domain_expiry = ?, hosting = ?, hosting_expiry = ?, type = '' WHERE id = ?");
        $del = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $usedHost = [];
        foreach ($domains as $d) {
            $h = $hostByName[$d['name']][0] ?? null;
            if ($h) {
                $upd->execute([$d['expiry_date'], $h['provider'] ?? '', $h['expiry_date'], $d['id']]);
                $del->execute([$h['id']]);
                $usedHost[$h['id']] = true;
            } else {
                $upd->execute([$d['expiry_date'], '', null, $d['id']]);
            }
        }
        // Convert any unpaired hosting rows to the v2 shape too
        foreach ($hostings as $h) {
            if (empty($usedHost[$h['id']])) {
                $u2 = $pdo->prepare("UPDATE services SET hosting = ?, hosting_expiry = ?, domain_expiry = NULL, type = '' WHERE id = ?");
                $u2->execute([$h['provider'] ?? '', $h['expiry_date'], $h['id']]);
            }
        }
    }
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $pdo->prepare("INSERT INTO settings (k, v) VALUES ('model_v2', '1') ON DUPLICATE KEY UPDATE v = '1'")->execute();
    } else {
        $pdo->prepare("INSERT INTO settings (k, v) VALUES ('model_v2', '1') ON CONFLICT(k) DO UPDATE SET v = '1'")->execute();
    }
}

function setting(string $k, string $default = ''): string {
    $s = db()->prepare("SELECT v FROM settings WHERE k = ?");
    $s->execute([$k]);
    $row = $s->fetch();
    return $row ? (string)$row['v'] : $default;
}

function set_setting(string $k, string $v): void {
    $pdo = db();
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $s = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)");
    } else {
        $s = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v");
    }
    $s->execute([$k, $v]);
}
