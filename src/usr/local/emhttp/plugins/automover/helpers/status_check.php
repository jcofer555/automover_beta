<?php
$cronFile   = '/boot/config/plugins/automover_beta/automover_beta.cron';
$logFile    = '/tmp/automover_beta/last_run.log';
$bootFail   = '/tmp/automover_beta/boot_failure';
$arrayStateFile = '/var/local/emhttp/var.ini';
$statusFile = '/tmp/automover_beta/temp_logs/status.txt';

$status     = 'Stopped';
$lastRun    = 'None';
$lastRunTs  = '';

// Ensure status directory exists
$dir = dirname($statusFile);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

// Autostart failure override
if (file_exists($bootFail)) {
    $status    = 'Autostart Failed';
    $lastRun   = trim(file_get_contents($bootFail));
    $lastRunTs = '';
} else {
    // Extract most recent valid timestamp
    if (file_exists($logFile)) {
        $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        foreach ($lines as $line) {
            if (preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line, $match)) {
                $lastRunTs = $match[0];
                break;
            }
        }
    }

    $automover_betaRunning = file_exists($cronFile) && strpos(file_get_contents($cronFile), 'automover_beta.sh') !== false;

    // Check array state
    $arrayStopped = false;
    $parityRunning = false;

    if (file_exists($arrayStateFile)) {
        $varIni = file_get_contents($arrayStateFile);

        if (preg_match('/mdState="([^"]+)"/', $varIni, $match)) {
            $arrayStopped = ($match[1] === 'STOPPED');
        }

        if (preg_match('/mdResync="([1-9][0-9]*)"/', $varIni)) {
            $parityRunning = true;
        }
    }

    // Base Status
    if ($arrayStopped) {
        $status = 'Array Is Not Started While automover_beta Is ' . ($automover_betaRunning ? 'Running' : 'Stopped');
    } elseif ($parityRunning) {
        $status = 'Parity Check Happening While automover_beta Is ' . ($automover_betaRunning ? 'Running' : 'Stopped');
    } else {
        $status = $automover_betaRunning ? 'Running' : 'Stopped';
    }

// Override ONLY when automover_beta.sh reports active file movement
if (file_exists($statusFile)) {
    $movingState = trim(file_get_contents($statusFile));

    // Case-insensitive check: does the line START with "Moving Files For Share:"
    if (stripos($movingState, 'moving files for share:') === 0) {
        $status = $movingState; // preserve full text including share name
    }
}

    // Compute readable time difference
    if ($lastRunTs) {
        $lastTs = strtotime($lastRunTs);
        if ($lastTs) {
            $nowTs = time();
            $diff = $nowTs - $lastTs;

            if ($diff < 10) {
                $lastRun = "just now";
            } elseif ($diff < 60) {
                $lastRun = "$diff seconds ago";
            } elseif ($diff < 3600) {
                $min = floor($diff / 60);
                $lastRun = "$min minute" . ($min !== 1 ? "s" : "") . " ago";
            } elseif ($diff < 86400) {
                $hrs = floor($diff / 3600);
                $lastRun = "$hrs hour" . ($hrs !== 1 ? "s" : "") . " ago";
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                $lastRun = "$days day" . ($days !== 1 ? "s" : "") . " ago";
            } elseif ($diff < 2592000) {
                $weeks = floor($diff / 604800);
                $lastRun = "over $weeks week" . ($weeks !== 1 ? "s" : "") . " ago";
            } elseif ($diff < 7776000) {
                $months = floor($diff / 2592000);
                $lastRun = "over $months month" . ($months !== 1 ? "s" : "") . " ago";
            } else {
                $lastRun = "on " . date('M d, Y h:i A', $lastTs);
            }
        }
    }
}

// Always write detected status to file (so automover_beta.sh can restore it)
file_put_contents($statusFile, $status);

header('Content-Type: application/json');
echo json_encode([
    'status'       => $status,
    'last_run'     => $lastRun,
    'last_run_ts'  => $lastRunTs
]);
?>
