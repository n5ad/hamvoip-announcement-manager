<?php
/**
 * list_announcements.php
 *
 * Returns a JSON list of all announcement cron jobs found in root's crontab.
 * Looks specifically for entries preceded by a comment line starting with:
 *   # Announcement:
 *
 * Each returned entry includes:
 *   - description (from the comment)
 *   - schedule (the cron timing fields)
 *   - command (the full play command)
 *
 * CREATED BY N5AD – adapted for Allmon3
 */

header('Content-Type: application/json');

$cron_entries = [];
$current      = null;

$output = [];
$return_var = 0;

// Read root crontab safely
exec('sudo crontab -l 2>/dev/null', $output, $return_var);

if ($return_var !== 0) {
    echo json_encode([
        'error' => 'Unable to read root crontab (may be empty or permission issue)'
    ]);
    exit;
}

foreach ($output as $line) {
    $trimmed = trim($line);

    // Skip empty lines
    if ($trimmed === '') {
        continue;
    }

    // Found an announcement description comment
    if (strpos($trimmed, '# Announcement:') === 0) {
        $current = [
            'description' => trim(substr($trimmed, strlen('# Announcement:'))),
            'schedule'    => '',
            'command'     => '',
            'raw'         => ''  // we'll fill this when we find the cron line
        ];
        continue;
    }

    // If we have a pending announcement and this looks like a cron line
    if ($current && preg_match('/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(.+)$/', $trimmed, $matches)) {
        $current['schedule'] = $matches[1];   // min hour dom month dow
        $current['command']  = $matches[2];   // the rest (playaudio.sh ...)
        $current['raw']      = $trimmed;      // full original line for delete/edit

        $cron_entries[] = $current;
        $current = null;  // reset for next possible announcement
    }
}

// If there was a dangling comment with no cron line after it, we ignore it here

echo json_encode($cron_entries, JSON_PRETTY_PRINT);
?>
