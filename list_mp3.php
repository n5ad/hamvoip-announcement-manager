<?php
/**
 * list_mp3.php
 * Lists both .mp3 and .wav files in /mp3/ for the Announcements Manager
 * CREATED BY N5AD – adapted for Allmon3
 */

$files = [];

// Find .mp3 files
$mp3_files = glob("/mp3/*.mp3");
foreach ($mp3_files as $f) {
    $files[] = basename($f);
}

// Find .wav files
$wav_files = glob("/mp3/*.wav");
foreach ($wav_files as $f) {
    $files[] = basename($f);
}

// Sort alphabetically
sort($files);

// Remove duplicates (same base name .mp3 + .wav)
$files = array_unique($files);

header('Content-Type: application/json');
echo json_encode($files);
?>
