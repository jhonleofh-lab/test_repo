<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'service' => 'Asset Prim Render DB Tester',
    'time_utc' => gmdate('c'),
], JSON_PRETTY_PRINT);
