<?php
/**
 * delete_announcement.php
 * 
 * Deletes a specific cron job entry (and cleans up any orphaned # Announcement: comment)
 * Used from the Cron Manager table's "Delete" button
 * 
 * CREATED / ADAPTED BY N5AD for Allmon3
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (!isset($_POST['raw_line']) || trim($_POST['raw_line']) === '') {
    echo "Error: Missing or empty cron line";
    exit;
}

$raw = trim($_POST['raw_line']);

// ────────────────────────────────────────────────
// Read current root crontab
// ────────────────────────────────────────────────
$output = [];
$return_var = 0;
exec('sudo crontab -l 2>/dev/null', $output, $return_var);

if ($return_var !== 0) {
    echo "Failed to read current crontab.";
    exit;
}

$lines = $output;

// ────────────────────────────────────────────────
// Remove the exact matching cron line
// ────────────────────────────────────────────────
$new_lines = [];
$removed = false;

foreach ($lines as $line) {
    if (trim($line) === $raw) {
        $removed = true;
        continue; // skip this line
    }
    $new_lines[] = $line;
}

if (!$removed) {
    echo "Cron line not found in crontab.";
    exit;
}

// ────────────────────────────────────────────────
// Clean up any orphaned # Announcement: lines
// (if a comment exists without a following cron job)
// ────────────────────────────────────────────────
$final_lines = [];
$i = 0;
while ($i < count($new_lines)) {
    $current = trim($new_lines[$i]);

    if (strpos($current, '# Announcement:') === 0) {
        // Look ahead: if next line doesn't exist or isn't a valid cron line
        if ($i + 1 >= count($new_lines) ||
            trim($new_lines[$i + 1]) === '' ||
            strpos(trim($new_lines[$i + 1]), '#') === 0) {
            // Orphaned comment → skip it
            $i++;
            continue;
        }
    }

    $final_lines[] = $new_lines[$i];
    $i++;
}

// ────────────────────────────────────────────────
// Write cleaned crontab back via temp file
// ────────────────────────────────────────────────
$tempfile = tempnam(sys_get_temp_dir(), 'cron_del_');

if (!file_put_contents($tempfile, implode(PHP_EOL, $final_lines) . PHP_EOL)) {
    echo "Failed to write temporary crontab file.";
    unlink($tempfile);
    exit;
}

exec("sudo crontab " . escapeshellarg($tempfile), $out, $ret);
unlink($tempfile);

if ($ret === 0) {
    echo "Cron entry deleted successfully.";
} else {
    echo "Failed to update crontab after deletion.\nReturn code: $ret";
}
?>
