<?php
/**
 * announcement.php
 * 
 * Converts selected MP3/WAV → u-law (.ul) format,
 * copies it to the Asterisk sounds/announcements directory,
 * and (if schedule parameters are provided) installs a cron job
 * to play it automatically using playaudio.sh
 * 
 * CREATED / ADAPTED BY N5AD for Allmon3
 */

$TMP_DIR       = '/mp3';
$CONVERT_SCRIPT = '/etc/asterisk/local/audio_convert.sh';
$PLAY_SCRIPT    = '/etc/asterisk/local/playaudio.sh';
$SOUNDS_DIR     = '/usr/local/share/asterisk/sounds/announcements';

// ────────────────────────────────────────────────
// Get POST data
// ────────────────────────────────────────────────
$mp3  = isset($_POST['file'])  ? basename($_POST['file']) : '';
$min   = $_POST['min']   ?? '';
$hour  = $_POST['hour']  ?? '';
$dom   = $_POST['dom']   ?? '';
$month = $_POST['month'] ?? '';
$dow   = $_POST['dow']   ?? '';
$desc  = $_POST['desc']  ?? '';

if (!$mp3) {
    die("No audio file specified.");
}

// ────────────────────────────────────────────────
// Validate input file exists
// ────────────────────────────────────────────────
$src_file = "$TMP_DIR/$mp3";

if (!file_exists($src_file)) {
    die("Audio file not found: $src_file");
}

// ────────────────────────────────────────────────
// Validate converter script
// ────────────────────────────────────────────────
if (!is_executable($CONVERT_SCRIPT)) {
    die("Conversion script not found or not executable: $CONVERT_SCRIPT");
}

// ────────────────────────────────────────────────
// Convert to .ul (u-law 8kHz mono)
// ────────────────────────────────────────────────
$cmd_convert = escapeshellcmd("$CONVERT_SCRIPT " . escapeshellarg($src_file));
exec($cmd_convert, $output, $ret);

if ($ret !== 0) {
    die("Conversion failed.\nReturn code: $ret\nOutput:\n" . implode("\n", $output));
}

// ────────────────────────────────────────────────
// Determine output .ul filename
// ────────────────────────────────────────────────
$base_name = pathinfo($mp3, PATHINFO_FILENAME);
$ul_file   = "$TMP_DIR/$base_name.ul";

// Check it was actually created
if (!file_exists($ul_file)) {
    die("Conversion appeared to succeed but .ul file is missing: $ul_file");
}

// ────────────────────────────────────────────────
// Copy .ul to Asterisk sounds directory (requires sudo)
// ────────────────────────────────────────────────
$cmd_copy = escapeshellcmd("sudo cp " . escapeshellarg($ul_file) . " " . escapeshellarg("$SOUNDS_DIR/$base_name.ul"));
exec($cmd_copy, $copy_out, $copy_ret);

if ($copy_ret !== 0) {
    die("Failed to copy .ul file to $SOUNDS_DIR.\nReturn code: $copy_ret\nOutput:\n" . implode("\n", $copy_out));
}

// Set safe permissions & ownership
exec(escapeshellcmd("sudo chmod 644 " . escapeshellarg("$SOUNDS_DIR/$base_name.ul")));
exec(escapeshellcmd("sudo chown asterisk:asterisk " . escapeshellarg("$SOUNDS_DIR/$base_name.ul")));
// Note: some systems use root:root or asterisk:audio — adjust if needed

// ────────────────────────────────────────────────
// Install cron job if scheduling info was provided
// ────────────────────────────────────────────────
if ($min && $hour && $dom && $month && $dow) {
    $play_target   = "$SOUNDS_DIR/$base_name";   // no extension — playaudio.sh expects this
    $comment_line  = $desc ? "# Announcement: $desc" : '';

    // Build the full cron line
    $cron_line = "$min $hour $dom $month $dow $PLAY_SCRIPT $play_target";

    // ────────────────────────────────────────────────
    // Append to root crontab (safely via temp file)
    // ────────────────────────────────────────────────
    $tmp_cron = tempnam(sys_get_temp_dir(), 'cron-ann-');
    
    // Get current crontab
    exec("sudo crontab -l > " . escapeshellarg($tmp_cron) . " 2>/dev/null");
    
    // Append comment + cron line
    if ($comment_line) {
        file_put_contents($tmp_cron, $comment_line . PHP_EOL, FILE_APPEND);
    }
    file_put_contents($tmp_cron, $cron_line . PHP_EOL, FILE_APPEND);

    // Install new crontab
    exec("sudo crontab " . escapeshellarg($tmp_cron), $cron_out, $cron_ret);
    unlink($tmp_cron);

    if ($cron_ret !== 0) {
        die("Failed to install cron job.\nReturn code: $cron_ret\nOutput:\n" . implode("\n", $cron_out));
    }

    echo "Conversion successful + cron job installed.\n";
    echo "Cron line: $cron_line\n";
} else {
    echo "Conversion successful. No cron job installed (missing schedule parameters).\n";
}

echo "Installed .ul file: $SOUNDS_DIR/$base_name.ul\n";
?>
