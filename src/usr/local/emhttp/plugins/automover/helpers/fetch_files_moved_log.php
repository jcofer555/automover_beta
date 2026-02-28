<?php
$logDir = '/tmp/automover_beta';
$logFile = "$logDir/files_moved.log";
$prevLog = "$logDir/files_moved_prev.log";
$lastRunLog = "$logDir/last_run.log";

// ✅ Ensure the /tmp/automover_beta directory exists
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$keyword = isset($_GET['filter']) ? strtolower(trim($_GET['filter'])) : null;

$lines = file_exists($logFile)
    ? file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

$matched = [];
$movedCount = 0;
$noMoveDetected = false;

// 🔍 Filter and count moved lines
foreach ($lines as $line) {
    $lower = strtolower($line);

    if ($keyword && strpos($lower, $keyword) === false) {
        continue;
    }

    // Preserve "no files moved" and "dry run" lines for display
    if (strpos($lower, 'no files moved for this move') !== false) {
        $matched[] = $line;
        $noMoveDetected = true;
        continue;
    }
    if (strpos($lower, 'dry run: no files would have been moved') !== false) {
        $matched[] = $line;
        $noMoveDetected = true;
        continue;
    }

    if (strpos($line, '->') !== false) {
        $movedCount++;
        $matched[] = $line;
    }
}

// ✅ Reverse to show newest first
$matched = array_reverse($matched);

// ==========================================================
// 🧩 Append previous run’s moved file list if no files moved
// ==========================================================
if ($noMoveDetected && file_exists($prevLog)) {
    $matched[] = "";
    $matched[] = "----- Previous Run Moved Files -----";
    $prevLines = file($prevLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach (array_reverse($prevLines) as $pline) {
        if (strpos($pline, '->') !== false) {
            $matched[] = $pline;
        }
    }
}

// 🔍 Check lastRunLog for dry run or no-op messages
$lastMessage = "No files moved for this run";

$lastRunLines = file_exists($lastRunLog)
    ? file($lastRunLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];

foreach (array_reverse($lastRunLines) as $line) {
    if (stripos($line, 'Dry run: No files would have been moved') !== false) {
        $lastMessage = 'Dry run: No files would have been moved';
        break;
    }
    if (stripos($line, 'No files moved for this run') !== false) {
        $lastMessage = 'No files moved for this run';
        break;
    }
}

// ✅ Final log text
$logText = count($matched) > 0
    ? implode("\n", $matched)
    : $lastMessage;

// ⏱ Extract duration from the last session block
$duration = null;
$sessionBlock = [];
$collecting = false;

for ($i = count($lastRunLines) - 1; $i >= 0; $i--) {
    $line = $lastRunLines[$i];

    if (stripos($line, 'Session finished') !== false) {
        $collecting = true;
    }

    if ($collecting) {
        array_unshift($sessionBlock, $line);
        if (stripos($line, 'Session started') !== false) {
            break; // full session block captured
        }
    }
}

foreach ($sessionBlock as $line) {
    if (stripos($line, 'Duration:') === 0) {
        $duration = trim(substr($line, 9));
        break;
    }
}

// ✅ Only override if duration is truly missing
if (
    $duration === null &&
    ($lastMessage === 'Dry run: No files would have been moved' || $lastMessage === 'No files moved for this run')
) {
    $duration = 'Nothing to track yet';
}

header('Content-Type: application/json');
echo json_encode([
    'log' => $logText,
    'moved' => $movedCount,
    'duration' => $duration,
    'total' => count($matched)
]);
?>
