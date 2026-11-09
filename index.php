<?php
declare(strict_types=1);

function envv(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/health') {
    respond(['status'=>'ok','service'=>'Asset Prim Render DB Tester','time_utc'=>gmdate('c')]);
}

if ($path === '/db-check') {
    $required = ['DB_HOST','DB_NAME','DB_USER','DB_PASS'];
    $missing = array_values(array_filter($required, fn($k) => envv($k) === null));
    if ($missing) respond(['status'=>'configuration_error','missing_variables'=>$missing],500);

    $host=envv('DB_HOST'); $port=(int)envv('DB_PORT','3306');
    $db=envv('DB_NAME'); $user=envv('DB_USER'); $pass=envv('DB_PASS');
    $charset=envv('DB_CHARSET','utf8mb4');
    $dsn="mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $opts=[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>15
    ];

    if (filter_var(envv('DB_SSL','false'), FILTER_VALIDATE_BOOLEAN)) {
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
        $ca=envv('DB_SSL_CA');
        if ($ca) $opts[PDO::MYSQL_ATTR_SSL_CA]=$ca;
    }

    try {
        $t=microtime(true);
        $pdo=new PDO($dsn,$user,$pass,$opts);
        $connectMs=round((microtime(true)-$t)*1000,2);
        $version=$pdo->query('SELECT VERSION()')->fetchColumn();
        $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $counts=[];
        foreach($tables as $table){
            $q='`'.str_replace('`','``',(string)$table).'`';
            try {$counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM {$q}")->fetchColumn();}
            catch(Throwable $e){$counts[$table]='count_failed';}
        }
        $times=[];
        for($i=0;$i<5;$i++){
            $s=microtime(true); $pdo->query('SELECT 1')->fetchColumn();
            $times[]=round((microtime(true)-$s)*1000,2);
        }
        $storage=null;
        try {
            $s=$pdo->prepare("SELECT ROUND(COALESCE(SUM(data_length+index_length),0)/1024/1024,3) FROM information_schema.tables WHERE table_schema=DATABASE()");
            $s->execute(); $storage=(float)$s->fetchColumn();
        } catch(Throwable $e) {}
        respond([
            'status'=>'connected','database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),
            'server_version'=>$version,'connection_time_ms'=>$connectMs,
            'table_count'=>count($tables),'table_record_counts'=>$counts,
            'select_1_query_times_ms'=>$times,
            'average_query_time_ms'=>round(array_sum($times)/count($times),2),
            'estimated_storage_mb'=>$storage,'read_only_test'=>true,
            'checked_at_utc'=>gmdate('c')
        ]);
    } catch(Throwable $e) {
        error_log('DB check failed: '.$e->getMessage());
        $debug=filter_var(envv('APP_DEBUG','false'),FILTER_VALIDATE_BOOLEAN);
        respond(['status'=>'connection_failed','error'=>$debug?$e->getMessage():'Database connection failed. Check Render environment variables, host, port, credentials, network access and TLS settings.','error_type'=>get_class($e)],503);
    }
}

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Asset Prim Render DB Tester</title>
<style>
body{margin:0;background:#0b1220;color:#e5edf8;font:16px/1.6 system-ui,Arial}main{max-width:760px;margin:8vh auto;padding:24px}.card{background:#121d30;border:1px solid #26364e;border-radius:18px;padding:28px}h1{margin:0 0 8px}p{color:#a9bad0}a{display:inline-block;margin:8px 8px 0 0;padding:11px 16px;border-radius:10px;background:#2563eb;color:white;text-decoration:none}
</style></head><body><main><section class="card"><h1>Asset Prim · Render DB Tester</h1>
<p>PHP service is running. Use the read-only endpoints below to test Render connectivity.</p>
<a href="/health">Check web service</a><a href="/db-check">Test database connection</a>
<p>Database credentials are read from Render Environment Variables and are never displayed.</p></section></main></body></html>
