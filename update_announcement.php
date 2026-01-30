<?php
/**
 * update_announcement.php
 * 
 * Updates an existing cron job's schedule (minute, hour, dom, month, dow)
 * while keeping the same command / file to play
 * 
 * Called from the "Edit" button in the Cron Manager table
 * 
 * CREATED / ADAPTED BY N5AD for Allmon3
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

// ────────────────────────────────────────────────
// Required fields
// ────────────────────────────────────────────────
if (!isset($_POST['raw_line']) || trim($_POST['raw_line']) === '') {
    echo "Error: Missing original cron line";
    exit;
}

$old_line = trim($_POST['raw_line']);

// New schedule values
$min   = trim($_POST['min']   ?? '');
$hour  = trim($_POST['hour']  ?? '');
$dom   = trim($_POST['dom']   ?? '');
$month = trim($_POST['month'] ?? '');
$dow   = trim($_POST['dow']   ?? '');

if ($min === '' || $hour === '' || $dom === '' || $month === '' || $dow === '') {
    echo "Error: All schedule fields (min, hour, dom, month, dow) are required";
    exit;
}

// ────────────────────────────────────────────────
// Extract the command part from the old line
// We want to keep the same command (playaudio.sh + file)
// ────────────────────────────────────────────────
if (!preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(.+)$/', $old_line, $matches)) {
    echo "Error: Could not parse original cron line";
    exit;
}

$old_schedule = $matches[1];           // old minute hour dom month dow
$command      = $matches[2];           // playaudio.sh + path + filename

// Build the NEW cron line with updated schedule but same command
$new_line = "$min $hour $dom $month $dow $command";

// ────────────────────────────────────────────────
// Read current crontab
// ────────────────────────────────────────────────
$output = [];
$return_var = 0;
exec('sudo crontab -l 2>/dev/null', $output, $return_var);

if ($return_var !== 0) {
    echo "Failed to read current crontab.";
    exit;
}

// ────────────────────────────────────────────────
// Replace the old line with the new one
// ────────────────────────────────────────────────
$new_crontab = [];
$found = false;

foreach ($output as $line) {
    if (trim($line) === $old_line) {
        $found = true;
        $new_crontab[] = $new_line;
    } else {
        $new_crontab[] = $line;
    }
}

if (!$found) {
    echo "The original cron line was not found in crontab.";
    exit;
}

// ────────────────────────────────────────────────
// Write new crontab via temporary file (safest method)
// ────────────────────────────────────────────────
$tempfile = tempnam(sys_get_temp_dir(), 'cron_upd_');

if (!file_put_contents($tempfile, implode(PHP_EOL, $new_crontab) . PHP_EOL)) {
    echo "Failed to write temporary crontab file.";
    unlink($tempfile);
    exit;
}

exec("sudo crontab " . escapeshellarg($tempfile), $out, $ret);
unlink($tempfile);

if ($ret === 0) {
    echo "Cron job updated successfully!\n";
    echo "New schedule: $min $hour $dom $month $dow\n";
    echo "Command remains: $command";
} else {
    echo "Failed to install updated crontab.\n";
    echo "Return code: $ret";
}

?>
