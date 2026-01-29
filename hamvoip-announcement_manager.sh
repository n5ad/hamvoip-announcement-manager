#!/usr/bin/env bash
#
# setup-hamvoip-announcement-manager.sh
# Adapted for HamVoIP running on Arch Linux
# - Uses pacman instead of apt
# - Uses /srv/http/supermon (typical HamVoIP web root)
# - Apache user/group = http:http
# - Safe & idempotent
#
# Run as root: sudo bash hamvoip-announcement-manager.sh
# Author: N5AD (original) — adapted January 2026
set -euo pipefail

# ────────────────────────────────────────────────
# CONFIG
# ────────────────────────────────────────────────
REPO_URL="https://github.com/n5ad/hamvoip-announcement-manager.git"
TEMP_CLONE="/tmp/supermon-announcements"
TARGET_DIR="/srv/http/supermon/custom"
LINK_PHP="/srv/http/supermon/link.php"
MP3_DIR="/mp3"
LOCAL_DIR="/etc/asterisk/local"
ANNOUNCE_DIR="/var/lib/asterisk/sounds/announcements"
WEB_USER="http"
WEB_GROUP="http"

# ────────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────────
echo_step() { echo -e "\n\033[1;34m==>\033[0m $1"; }
warn() { echo -e "\033[1;33mWARNING:\033[0m $1" >&2; }
error() { echo -e "\033[1;31mERROR:\033[0m $1" >&2; exit 1; }
check_root() { [[ $EUID -eq 0 ]] || error "Run as root (sudo)."; }

# ────────────────────────────────────────────────
check_root

echo_step "1. Installing required packages (sox, git, perl)"
pacman -Syu --needed git sox perl || error "pacman failed. Check internet/mirrors."
# sox in Arch includes MP3 support via libmad/libmp3lame when built normally

if ! command -v git >/dev/null 2>&1; then
    error "git still not found after install — check pacman mirrors."
fi

echo ""
echo "Supermon Announcements Manager - Arch Linux / HamVoIP Setup"
echo "──────────────────────────────────────────────"
echo "GitHub Repo  : $REPO_URL"
echo "Target dir   : $TARGET_DIR"
echo "MP3 dir      : $MP3_DIR"
echo "Local scripts: $LOCAL_DIR"
echo "link.php     : $LINK_PHP"
echo "Web user     : $WEB_USER:$WEB_GROUP"
echo ""

echo -n "Continue setup? (y/N) "
read -r answer
[[ "$answer" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }

# ────────────────────────────────────────────────
echo_step "2. Enter your AllStar node number"
echo -n "Node number (e.g., 12345): "
read -r NODE_NUMBER
[[ "$NODE_NUMBER" =~ ^[0-9]+$ ]] || error "Invalid node number — digits only."
echo "Using node: $NODE_NUMBER"

# ────────────────────────────────────────────────
echo_step "3. Cloning GitHub repo"
rm -rf "$TEMP_CLONE"
git clone --depth 1 "$REPO_URL" "$TEMP_CLONE" || error "Git clone failed"

# ────────────────────────────────────────────────
echo_step "4. Copying PHP & inc files to $TARGET_DIR"
mkdir -p "$TARGET_DIR"
cp -v "$TEMP_CLONE"/*.{php,inc} "$TARGET_DIR"/ 2>/dev/null || warn "No .php/.inc files copied (maybe already there?)"
rm -rf "$TEMP_CLONE"

# ────────────────────────────────────────────────
# ────────────────────────────────────────────────
echo_step "5. Creating /mp3 directory + permissions"
mkdir -p "$MP3_DIR"
echo "Web server runs as $WEB_USER:$WEB_GROUP"

# Determine the user who invoked sudo (for group addition)
INVOKER="${SUDO_USER:-$(whoami)}"
if [[ "$INVOKER" = "root" ]]; then
    warn "Script run directly as root – skipping adding user to $WEB_GROUP"
else
    if id -nG "$INVOKER" 2>/dev/null | grep -qw "$WEB_GROUP"; then
        echo "$INVOKER is already in $WEB_GROUP group"
    else
        echo "Adding $INVOKER to $WEB_GROUP group (for easier file access from your account)"
        usermod -aG "$WEB_GROUP" "$INVOKER" || warn "Failed to add $INVOKER to group (check manually)"
    fi
fi

chown -R "$WEB_USER:$WEB_GROUP" "$MP3_DIR"
chmod -R 2775 "$MP3_DIR"
echo "MP3 directory ready (setgid enabled)."

# ────────────────────────────────────────────────
echo_step "6. Ownership & permissions on custom files"
chown -R "$WEB_USER:$WEB_GROUP" "$TARGET_DIR"
find "$TARGET_DIR" -type f \( -name "*.php" -o -name "*.inc" \) -exec chmod 644 {} \;

# ────────────────────────────────────────────────
echo_step "7. Creating Announcements dir + permissions"
mkdir -p "$ANNOUNCE_DIR"
chown -R "$WEB_USER:$WEB_GROUP" "$ANNOUNCE_DIR"
chmod -R 2775 "$ANNOUNCE_DIR"

# ────────────────────────────────────────────────
echo_step "8. Installing prerequisite scripts in $LOCAL_DIR"
mkdir -p "$LOCAL_DIR"
chown asterisk:asterisk "$LOCAL_DIR" 2>/dev/null || chown root:root "$LOCAL_DIR"
chmod 755 "$LOCAL_DIR"

# playaudio.sh
PLAY_SCRIPT="$LOCAL_DIR/playaudio.sh"
if [[ ! -f "$PLAY_SCRIPT" ]]; then
    echo "Creating $PLAY_SCRIPT"
    cat > "$PLAY_SCRIPT" << EOF
#!/bin/bash
# playaudio.sh – Play audio over AllStar node
NODE="$NODE_NUMBER"
[ "\$EUID" -eq 0 ] || { echo "Run with sudo"; exit 1; }
[ -z "\$1" ] && { echo "Usage: \$0 <audio_file_without_extension>"; exit 1; }
asterisk -rx "rpt localplay \${NODE} \$1"
EOF
   
    chown root:root "$PLAY_SCRIPT" 
    chmod 755 "$PLAY_SCRIPT"
else
    echo "$PLAY_SCRIPT already exists — skipping creation"
fi

# audio_convert.sh
CONVERT_SCRIPT="$LOCAL_DIR/audio_convert.sh"
if [[ ! -f "$CONVERT_SCRIPT" ]]; then
    echo "Creating $CONVERT_SCRIPT"
    cat > "$CONVERT_SCRIPT" << 'EOF'
#!/bin/bash
# Convert to ulaw .ul
[ $# -lt 1 ] && { echo "Usage: $0 input_file [output.ul]"; exit 1; }
INPUT="$1"
OUTPUT="${2:-${INPUT%.*}.ul}"
sox "$INPUT" -t raw -r 8000 -c 1 -e u-law "$OUTPUT" && \
    echo "Converted → $OUTPUT" || echo "sox conversion failed"
EOF
    chmod 755 "$CONVERT_SCRIPT"
    chown asterisk:asterisk "$CONVERT_SCRIPT" 2>/dev/null || chown root:root "$CONVERT_SCRIPT"
else
    echo "$CONVERT_SCRIPT already exists — skipping"
fi

# ────────────────────────────────────────────────
echo_step "9. Install/overwrite link.php (backup old one)"
if [[ -f "$LINK_PHP" ]]; then
    cp -v "$LINK_PHP" "${LINK_PHP}.bak"
fi
wget -O "$LINK_PHP" "https://raw.githubusercontent.com/n5ad/hamvoip-announcement-manager/main/link.php" || error "wget link.php failed"
chown "$WEB_USER:$WEB_GROUP" "$LINK_PHP"
chmod 644 "$LINK_PHP"

# ────────────────────────────────────────────────
echo_step "10. Sudoers rule for http user"
SUDOERS_FILE="/etc/sudoers.d/99-supermon-announcements"
if [[ -f "$SUDOERS_FILE" ]]; then
    echo "$SUDOERS_FILE exists — skipping"
else
    cat > "$SUDOERS_FILE" << EOF
# /etc/sudoers.d/99-supermon-announcements  (Arch/HamVoIP)
$WEB_USER ALL=(root) NOPASSWD: $LOCAL_DIR/playaudio.sh
$WEB_USER ALL=(root) NOPASSWD: /usr/bin/crontab
$WEB_USER ALL=(root) NOPASSWD: $LOCAL_DIR/audio_convert.sh
$WEB_USER ALL=(root) NOPASSWD: /usr/bin/cp, /usr/bin/chown, /usr/bin/chmod
$WEB_USER ALL=(root) NOPASSWD: /usr/local/bin/piper_prompt_tts.sh
$WEB_USER ALL=(root) NOPASSWD: /bin/rm $ANNOUNCE_DIR/*.ul
EOF
    chmod 0440 "$SUDOERS_FILE"
    visudo -c -f "$SUDOERS_FILE" || error "sudoers syntax error!"
    echo "Sudoers rule created."
fi

# ────────────────────────────────────────────────
echo_step "11. Installing Piper TTS 1.2.0 (auto-detect arch)"
# ────────────────────────────────────────────────
echo_step "11. Installing Piper TTS 1.2.0 (architecture-aware)"
if [[ -f "/opt/piper/bin/piper" ]]; then
    echo "Piper already present — skipping download/install"
else
    ARCH=$(uname -m)
    echo "Detected architecture: $ARCH"

    case "$ARCH" in
        aarch64|arm64)
            PIPER_FILE="piper_arm64.tar.gz"
            ;;
        armv7l|armv7|armhf)
            PIPER_FILE="piper_armv7.tar.gz"   # v1.2.0 name; works for armv7l
            # Alternative newer name if using post-v1.2: "piper_linux_armv7l.tar.gz"
            ;;
        x86_64|amd64)
            PIPER_FILE="piper_amd64.tar.gz"
            ;;
        *)
            error "Unsupported architecture: $ARCH — no Piper binary available. Build from source or check https://github.com/rhasspy/piper/releases"
            ;;
    esac

    DOWNLOAD_URL="https://github.com/rhasspy/piper/releases/download/v1.2.0/$PIPER_FILE"

    echo "Downloading $PIPER_FILE for $ARCH ..."
    wget "$DOWNLOAD_URL" -O /tmp/piper.tar.gz || error "Download failed for $PIPER_FILE — check URL or internet"

    mkdir -p /opt/piper/bin
    tar -xzf /tmp/piper.tar.gz -C /opt/piper/bin
    chmod +x /opt/piper/bin/piper

    mkdir -p /opt/piper/voices
    cd /opt/piper/voices || error "Failed to cd into /opt/piper/voices"

    # Download the Lessac medium voice (English US) — same for all arches
    wget -4 https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx
    wget -4 https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx.json

    chown "$WEB_USER:$WEB_GROUP" *.onnx *.onnx.json
    chmod 644 *.onnx *.onnx.json

    rm -f /tmp/piper.tar.gz
    echo "Piper $ARCH binary installed successfully."
fi
# ────────────────────────────────────────────────
echo_step "12. Downloading piper_prompt_tts.sh"
if [[ -f "/usr/local/bin/piper_prompt_tts.sh" ]]; then
    echo "piper_prompt_tts.sh exists — skipping"
else
    wget -O /usr/local/bin/piper_prompt_tts.sh \
        https://raw.githubusercontent.com/n5ad/hamvoip-announcement-manager/main/piper_prompt_tts.sh
    chown root:root /usr/local/bin/piper_prompt_tts.sh
    chmod 755 /usr/local/bin/piper_prompt_tts.sh
fi

# ────────────────────────────────────────────────
echo_step "13. Quick Piper test"
echo "Piper version:"
/opt/piper/bin/piper --version || warn "Piper binary test failed"

echo "Generating test WAV ..."
echo "Test announcement from node $(hostname)" | \
    /opt/piper/bin/piper --model /opt/piper/voices/en_US-lessac-medium.onnx \
    --output_file /mp3/piper_test.wav

if [[ -f /mp3/piper_test.wav ]]; then
    ls -l /mp3/piper_test.wav
    echo "Test WAV created successfully."
else
    warn "Test WAV not created — check Piper / permissions / sox"
fi

# ────────────────────────────────────────────────
echo_step "14. Setup finished"
echo ""
echo " → Log into Supermon → Announcements Manager link should now appear"
echo " → Test file conversion & playback manually if needed"
echo " → If Piper test failed, check /mp3 permissions and model path"
echo ""
echo "73 de N5AD (adapted for Arch/HamVoIP)"





