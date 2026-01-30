<?php
/**
 * delete_file.php
 * 
 * Deletes either an MP3/WAV file from /mp3/ or a .ul file from the Asterisk
 * announcements directory.
 * 
 * Called from:
 *   - "Delete MP3" button next to MP3/WAV dropdown
 *   - "Delete UL" button next to .ul dropdown
 * 
 * CREATED / ADAPTED BY N5AD for Allmon3
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

if (empty($_POST['type']) || empty($_POST['file'])) {
    echo "Missing required parameters: type and file.";
    exit;
}

$type    = strtolower(trim($_POST['type']));
$filename = basename(trim($_POST['file']));  // prevent path traversal

// ────────────────────────────────────────────────
// Determine file path based on type
// ────────────────────────────────────────────────
if ($type === 'mp3' || $type === 'wav') {
    $file_path = "/mp3/" . $filename;
} elseif ($type === 'ul') {
    $file_path = "/usr/local/share/asterisk/sounds/announcements/" . $filename;
} else {
    echo "Invalid type specified. Must be 'mp3', 'wav', or 'ul'.";
    exit;
}

// ────────────────────────────────────────────────
// Security: only allow expected extensions
// ────────────────────────────────────────────────
$allowed_ext = ['mp3', 'wav', 'ul'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    echo "Invalid file extension. Only .mp3, .wav, .ul allowed.";
    exit;
}

// ────────────────────────────────────────────────
// Check if file exists
// ────────────────────────────────────────────────
if (!file_exists($file_path)) {
    echo "File not found: " . htmlspecialchars($filename);
    exit;
}

// ────────────────────────────────────────────────
// Attempt to delete the file
// ────────────────────────────────────────────────
if (@unlink($file_path)) {
    // Optional: you could add logging here if desired
    // error_log("Deleted announcement file: $file_path", 0);
    echo "Successfully deleted " . htmlspecialchars($filename);
} else {
    echo "Failed to delete " . htmlspecialchars($filename) . ". Check permissions.";
}
?>
