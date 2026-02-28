<?php
header('Content-Type: application/json');
$lockFile = '/tmp/automover_beta/lock.txt';
echo json_encode(['locked' => file_exists($lockFile)]);
