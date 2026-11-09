<?php
declare(strict_types=1);

function envv(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/health') {
    require __DIR__ . '/health.php';
    exit;
}

if ($path === '/db-check') {
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missing = [];
    foreach ($required as $key) {
        if (envv($key) === null) $missing[] = $key;
    }
    if ($missing) {
        jsonResponse([
            'status' => 'configuration_error',
            'missing_variables' => $missing,
            'hint' => 'Set these in Render Dashboard > Environment.',
        ], 500);
    }

    $host = envv('DB_HOST');
    $port = (int) envv('DB_PORT', '3306');
    $db   = envv('DB_NAME');
    $user = envv('DB_USER');
    $pass = envv('DB_PASS');
    $charset = envv('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 15,
    ];

    // TLS is provider-dependent. Enable only when Filess gives TLS connection details.
    if (filter_var(envv('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        $ca = envv('DB_SSL_CA');
        if ($ca) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
    }

    $started = microtime(true);
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $connectMs = round((microtime(true) - $started) * 1000, 2);

        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        $currentDb = $pdo->query('SELECT DATABASE()')->fetchColumn();

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tableCounts = [];
        foreach ($tables as $table) {
            // Quote identifier safely; table names come from SHOW TABLES.
            $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
                $tableCounts[$table] = (int)$count;
            } catch (Throwable $e) {
                $tableCounts[$table] = 'count_failed';
            }
        }

        $queryTimes = [];
        for ($i = 0; $i < 5; $i++) {
            $t = microtime(true);
            $pdo->query('SELECT 1')->fetchColumn();
            $queryTimes[] = round((microtime(true) - $t) * 1000, 2);
        }

        $storageMb = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT ROUND(COALESCE(SUM(data_length + index_length),0)/1024/1024,3)
                 FROM information_schema.tables WHERE table_schema = DATABASE()"
            );
            $stmt->execute();
            $storageMb = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Some shared providers restrict information_schema statistics.
        }

        jsonResponse([
            'status' => 'connected',
            'service' => 'Asset Prim Render DB Tester',
            'database' => $currentDb,
            'server_version' => $version,
            'connection_time_ms' => $connectMs,
            'table_count' => count($tables),
            'table_record_counts' => $tableCounts,
            'select_1_query_times_ms' => $queryTimes,
            'average_query_time_ms' => round(array_sum($queryTimes) / count($queryTimes), 2),
            'estimated_storage_mb' => $storageMb,
            'read_only_test' => true,
            'checked_at_utc' => gmdate('c'),
        ]);
    } catch (Throwable $e) {
        error_log('Database check failed: ' . $e->getMessage());
        $debug = filter_var(envv('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        jsonResponse([
            'status' => 'connection_failed',
            'error' => $debug ? $e->getMessage() : 'Could not connect to database. Check Render environment variables, host, port, credentials, network access and TLS requirements.',
            'error_type' => get_class($e),
        ], 503);
    }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Asset Prim · Render DB Test</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1220;color:#e5edf8;font:16px/1.6 system-ui,Arial,sans-serif}
main{max-width:760px;margin:7vh auto;padding:24px}.card{background:#121d30;border:1px solid #26364e;border-radius:18px;padding:28px}
h1{margin:0 0 8px;font-size:28px}p{color:#a9bad0}.pill{display:inline-block;background:#123b2c;color:#7ce5ad;padding:4px 11px;border-radius:30px;font-size:13px}
a{display:inline-block;margin:8px 8px 0 0;padding:11px 16px;border-radius:10px;background:#2563eb;color:white;text-decoration:none}
code{color:#9dc1ff}small{color:#8da2bd}
</style>
</head>
<body><main><section class="card">
<span class="pill">DEPLOYMENT READY</span>
<h1>Asset Prim · Render DB Tester</h1>
<p>PHP service is running. Use the read-only endpoint below to test the Render-to-Filess.io database connection.</p>
<a href="/health">Check web service</a>
<a href="/db-check">Test database connection</a>
<p><small>Database credentials are read from Render Environment Variables. No credentials are displayed by this page.</small></p>
</section></main></body></html>
