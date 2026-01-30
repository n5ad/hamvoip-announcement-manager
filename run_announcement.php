<?php
/**
 * run_announcement.php
 * 
 * Immediately plays a .ul announcement file (or any compatible sound file)
 * on the AllStar node using playaudio.sh
 * 
 * Called from:
 *   - The "Play Now" button next to the .ul dropdown
 *   - The "Play" button in the Cron Manager table
 * 
 * CREATED / ADAPTED BY N5AD for Allmon3
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (empty($_POST['file'])) {
    echo "No file specified.";
    exit;
}

// ────────────────────────────────────────────────
// Sanitize and prepare filename
// ────────────────────────────────────────────────
$input_file = basename($_POST['file']);

// Remove any extension if it was accidentally included (e.g. "mynotice.ul" → "mynotice")
$base_name = pathinfo($input_file, PATHINFO_FILENAME);

// AllStar playaudio.sh expects the path relative to sounds/ without extension
// Typical location: /usr/local/share/asterisk/sounds/announcements/<base_name>
$play_path = "announcements/" . $base_name;

// ────────────────────────────────────────────────
// Path to the play script
// ────────────────────────────────────────────────
$play_script = "/etc/asterisk/local/playaudio.sh";

// Verify the script exists and is executable
if (!file_exists($play_script) || !is_executable($play_script)) {
    echo "Error: playaudio.sh not found or not executable at $play_script.";
    exit;
}

// ────────────────────────────────────────────────
// Build and execute the command with sudo
// playaudio.sh expects:   <filename-without-extension>
// and internally does:    asterisk -rx "rpt localplay <NODE> <filename>"
// ────────────────────────────────────────────────
$cmd = escapeshellcmd("sudo $play_script " . escapeshellarg($play_path));

exec($cmd . " 2>&1", $output, $retval);

if ($retval === 0) {
    echo "Playing '$base_name' now.";
} else {
    $error_msg = implode("\n", $output);
    echo "Failed to play '$base_name'.\n";
    echo "Return code: $retval\n";
    echo "Output:\n$error_msg";
}
?>
