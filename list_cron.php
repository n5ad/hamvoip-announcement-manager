<?php
/*
 * list_cron.php
 * Lists announcement cron jobs with time, file, description
 * CREATED BY N5AD – adapted for Allmon3
 */

header('Content-Type: application/json');

$cron = shell_exec('sudo crontab -l 2>/dev/null');
if ($cron === null) {
    echo json_encode([]);
    exit;
}

$lines = explode("\n", $cron);
$entries = [];
$last_comment = "";

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "") continue;

    if (strpos($line, '# Announcement:') === 0) {
        $last_comment = trim(str_replace('# Announcement:', '', $line));
        continue;
    }

    if (strpos($line, 'playaudio.sh') !== false) {
        $parts = preg_split('/\s+/', $line);
        $time = implode(" ", array_slice($parts, 0, 5));
        $file = basename(end($parts));

        $entries[] = [
            "time" => $time,
            "file" => $file,
            "desc" => $last_comment,
            "raw"  => $line
        ];

        $last_comment = "";
    }
}

echo json_encode($entries);
?>
