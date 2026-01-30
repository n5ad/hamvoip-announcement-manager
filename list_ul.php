<?php
// list_ul.php
// Lists .ul files in Asterisk announcements directory
// CREATED BY N5AD – adapted for Allmon3

$SOUNDS_DIR = '/usr/local/share/asterisk/sounds/announcements';

$files = glob("$SOUNDS_DIR/*.ul");
$out = [];

foreach ($files as $f) {
    $out[] = basename($f);
}

header('Content-Type: application/json');
echo json_encode($out);
?>
