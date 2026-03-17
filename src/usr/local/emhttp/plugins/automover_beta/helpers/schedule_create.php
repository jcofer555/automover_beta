<?php
declare(strict_types=1);

require_once __DIR__ . '/rebuild_cron.php';

// ── Constants ─────────────────────────────────────────────────────────────────
define('SCHEDULES_CFG', '/boot/config/plugins/automover_beta/schedules.cfg');
define('CRON_PATTERN',  '/^([\*\/0-9,-]+\s+){4}[\*\/0-9,-]+$/');

// ── Allowed settings keys ─────────────────────────────────────────────────────
const ALLOWED_KEYS = [
    'AGE_BASED_FILTER',
    'AGE_DAYS',
    'ALLOW_DURING_PARITY',
    'AUTOSTART',
    'CONTAINER_NAMES',
    'CRON_EXPRESSION',
    'CRON_MODE',
    'DAILY_MINUTE',
    'DAILY_TIME',
    'DRY_RUN',
    'ENABLE_CLEANUP',
    'ENABLE_JDUPES',
    'ENABLE_NOTIFICATIONS',
    'ENABLE_SCRIPTS',
    'ENABLE_TRIM',
    'EXCLUSIONS_ENABLED',
    'FORCE_RECONSTRUCTIVE_WRITE',
    'HASH_PATH',
    'HIDDEN_FILTER',
    'HOURLY_FREQUENCY',
    'IO_PRIORITY',
    'MANUAL_MOVE',
    'MONTHLY_DAY',
    'MONTHLY_MINUTE',
    'MONTHLY_TIME',
    'NOTIFICATION_SERVICE',
    'POOL_NAME',
    'POST_SCRIPT',
    'PRE_SCRIPT',
    'PRIORITIES',
    'PROCESS_PRIORITY',
    'PUSHOVER_USER_KEY',
    'QBITTORRENT_DAYS_FROM',
    'QBITTORRENT_DAYS_TO',
    'QBITTORRENT_HOST',
    'QBITTORRENT_PASSWORD',
    'QBITTORRENT_SCRIPT',
    'QBITTORRENT_STATUS',
    'QBITTORRENT_USERNAME',
    'SIZE_BASED_FILTER',
    'SIZE_MB',
    'SIZE_UNIT',
    'STOP_ALL_CONTAINERS',
    'STOP_THRESHOLD',
    'THRESHOLD',
    'WEBHOOK_DISCORD',
    'WEBHOOK_GOTIFY',
    'WEBHOOK_NTFY',
    'WEBHOOK_PUSHOVER',
    'WEBHOOK_SLACK',
    'WEEKLY_DAY',
    'WEEKLY_MINUTE',
    'WEEKLY_TIME',
];

// ── respond() ─────────────────────────────────────────────────────────────────
function respond(int $code, array $payload): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

// ── load_schedules() ──────────────────────────────────────────────────────────
function load_schedules(string $cfg): array {
    $real = realpath($cfg);
    if ($real === false || !file_exists($real)) {
        return [];
    }
    $schedules = parse_ini_file($real, true, INI_SCANNER_RAW);
    return is_array($schedules) ? $schedules : [];
}

// ── check_duplicate() ─────────────────────────────────────────────────────────
// Reject if another schedule already has the exact same cron expression.
function check_duplicate(array $schedules, string $new_cron, string $exclude_id = ''): void {
    foreach ($schedules as $id => $s) {
        if ($id === $exclude_id) continue;
        if (trim((string)($s['CRON'] ?? '')) === $new_cron) {
            respond(409, [
                'error'       => 'A schedule with this cron expression already exists',
                'conflict_id' => $id,
            ]);
        }
    }
}

// ── append_schedule() ─────────────────────────────────────────────────────────
function append_schedule(string $cfg, string $id, string $cron, string $settings_json): void {
    $real   = realpath($cfg);
    $target = ($real !== false) ? $real : $cfg;

    $block  = "\n[{$id}]\n";
    $block .= "CRON=\"{$cron}\"\n";
    $block .= "ENABLED=\"yes\"\n";
    $block .= "SETTINGS=\"{$settings_json}\"\n";

    if (file_put_contents($target, $block, FILE_APPEND) === false) {
        respond(500, ['error' => 'Failed to write schedule']);
    }
}

// ── main() ────────────────────────────────────────────────────────────────────
function main(): void {
    $cron     = trim($_POST['cron']     ?? '');
    $settings = $_POST['settings']      ?? [];

    if (!is_array($settings)) {
        $settings = [];
    }

    // Allowlist — strip anything that shouldn't be persisted
    $settings = array_intersect_key($settings, array_flip(ALLOWED_KEYS));
    unset($settings['csrf_token']);

    // Validate cron expression
    if (!preg_match(CRON_PATTERN, $cron)) {
        respond(400, ['error' => 'Invalid cron expression']);
    }

    // Validate pool name is present
    if (trim($settings['POOL_NAME'] ?? '') === '') {
        respond(400, ['error' => 'Pool name is required']);
    }

    // Ensure config directory exists
    $cfg_dir = dirname(SCHEDULES_CFG);
    if (!is_dir($cfg_dir)) {
        mkdir($cfg_dir, 0755, true);
    }

    $schedules = load_schedules(SCHEDULES_CFG);
    check_duplicate($schedules, $cron);

    // Encode settings as escaped JSON for safe INI storage
    $settings_json = addcslashes(
        json_encode($settings, JSON_UNESCAPED_SLASHES),
        '"'
    );

    $id = 'schedule_' . time();
    append_schedule(SCHEDULES_CFG, $id, $cron, $settings_json);

    rebuild_cron();

    respond(200, [
        'success' => true,
        'id'      => $id,
    ]);
}

main();