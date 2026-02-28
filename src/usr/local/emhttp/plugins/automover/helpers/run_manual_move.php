<?php
header('Content-Type: application/json');

$pool   = $_POST['pool'] ?? 'cache';
$csrf   = $_POST['csrf_token'] ?? '';
$cookie = $_COOKIE['csrf_token'] ?? $csrf;

// Absolute path to automover_beta script
$script = '/usr/local/emhttp/plugins/automover_beta/helpers/automover_beta.sh';

// Verify automover_beta script exists
if (!file_exists($script)) {
    echo json_encode(['ok' => false, 'error' => 'automover_beta.sh not found']);
    exit;
}

// Run automover_beta silently in the background
$cmd = sprintf(
    '/bin/bash %s --force-now --pool %s >/dev/null 2>&1 &',
    escapeshellarg($script),
    escapeshellarg($pool)
);
exec($cmd);

echo json_encode(['ok' => true, 'message' => 'Manual move started']);
