#!/bin/bash
#
# audio_convert.sh - Convert audio file to ulaw .ul
#
# Usage: audio_convert.sh input_file [output_file.ul]
#
# If output_file is not specified, it will be named the same as input_file but with .ul extension
# Requires sox (install with apt install sox libsox-fmt-mp3)
if [ $# -lt 1 ]; then
    echo "Usage: \$0 [input_file] [output_file.ul]"
    exit 1
fi
INPUT_FILE="$1"
OUTPUT_FILE="${2:-${INPUT_FILE%.*}.ul}"
sox "$INPUT_FILE" -t raw -r 8000 -c 1 -e u-law "$OUTPUT_FILE"
if [ $? -eq 0 ]; then
    echo "Conversion successful!"
    echo "Output file: $OUTPUT_FILE"
else
    echo "Error: Conversion failed."
fi
