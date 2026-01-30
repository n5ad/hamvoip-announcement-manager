<?php
// announcement.inc
// Include this file in Allmon3 after successful login (usually in index.php or nodes.php)
// Adjust $_SESSION key below to match Allmon3's actual login session variable

// Most common possibilities in Allmon3 forks: 'loggedin', 'allmon3_loggedin', 'user', 'authenticated'
// You will very likely need to change this line:
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    return;
}
?>

<hr style="margin-top:30px;">
<div id="bottom_cron_section" style="margin-top:20px;">

<h3>Generate Announcement with Piper TTS</h3>
<textarea id="tts_text" rows="4" cols="60" placeholder="Enter the text you want to convert to speech..."></textarea><br><br>
<label for="tts_filename">Filename (one word, letters/numbers/hyphen/underscore only, no extension):</label><br>
<input type="text" id="tts_filename" placeholder="e.g. morningnet" style="width:220px;"><br><br>
<button id="generate_tts" class="submit">Generate WAV File</button>
<div id="tts_status" style="margin-top:10px; font-weight:bold; color:#28a745;"></div>

<h3>Announcement Scheduler</h3>

<select id="mp3_select" class="submit" style="border:2px solid #444; width: 280px;">
    <option value="">-- Select Audio File (MP3 or WAV) --</option>
</select>
<input type="button" class="submit" value="Install Announcement" id="install_announcement">
<input type="button" class="submit" value="Delete MP3/WAV" id="delete_mp3" style="background:#dc3545;color:white;border:1px solid #c82333;">

<br><br>

<select id="ul_select" class="submit" style="border:2px solid #444; width: 280px;">
    <option value="">-- Select .ul Announcement --</option>
</select>
<input type="button" class="submit" value="Play Now" id="run_ul">
<input type="button" class="submit" value="Delete .ul" id="delete_ul" style="background:#dc3545;color:white;border:1px solid #c82333;">

<br><br>

<h3>Cron Manager</h3>
<table id="cron_table" border="1" cellpadding="5" style="width:100%; border-collapse:collapse; font-size:0.95em;">
    <thead style="background:#444;color:white;">
        <tr>
            <th>Min</th><th>Hour</th><th>DOM</th><th>Mon</th><th>DOW</th>
            <th>File</th><th>Description</th><th>Enabled</th><th>Actions</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// All JavaScript logic – copy-paste this entire block
$(document).ready(function() {

    function loadMP3Dropdown() {
        $.getJSON("custom/list_mp3.php", function(files) {
            $("#mp3_select").empty().append(
                $("<option>").val("").text("-- Select Audio File (MP3/WAV) --")
            );
            $.each(files, function(i, f) {
                $("#mp3_select").append($("<option>").val(f).text(f));
            });
        }).fail(function() { alert("Failed to load MP3/WAV list"); });
    }
    loadMP3Dropdown();

    function loadULDropdown() {
        $.getJSON("custom/list_ul.php", function(files) {
            $("#ul_select").empty().append(
                $("<option>").val("").text("-- Select .ul Announcement --")
            );
            $.each(files, function(i, f) {
                $("#ul_select").append($("<option>").val(f).text(f));
            });
        }).fail(function() { alert("Failed to load .ul list"); });
    }
    loadULDropdown();

    function loadCronList() {
        $.getJSON("custom/list_cron.php", function(crons) {
            let tbody = $("#cron_table tbody").empty();
            $.each(crons, function(i, c) {
                let parts = c.time.split(/\s+/);
                let row = $("<tr>");
                $.each(parts, function(j, p) { row.append($("<td>").text(p)); });
                row.append($("<td>").text(c.file));
                row.append($("<td>").text(c.desc || ""));

                // Toggle button
                let tog = $("<button>").text("Toggle").addClass("small-btn")
                    .click(function() {
                        let enable = confirm("Enable this cron job?") ? 1 : 0;
                        $.post("custom/toggle_cron.php", {raw_line: c.raw, enable: enable}, function(resp) {
                            alert(resp);
                            loadCronList();
                        });
                    });
                row.append($("<td>").append(tog));

                // Actions cell
                let acts = $("<td>");
                $("<button>").text("Play").addClass("small-btn play").click(function(){
                    $.post("custom/run_announcement.php", {file: c.file}, function(r){ alert(r); });
                }).appendTo(acts);
                $("<button>").text("Edit").addClass("small-btn edit").click(function(){
                    let min   = prompt("Minute",   parts[0]); if(min===null)return;
                    let hour  = prompt("Hour",     parts[1]); if(hour===null)return;
                    let dom   = prompt("Day",      parts[2]); if(dom===null)return;
                    let month = prompt("Month",    parts[3]); if(month===null)return;
                    let dow   = prompt("DOW",      parts[4]); if(dow===null)return;
                    $.post("custom/update_announcement.php", {
                        raw_line: c.raw, min, hour, dom, month, dow
                    }, function(resp){ alert(resp); loadCronList(); });
                }).appendTo(acts);
                $("<button>").text("Delete").addClass("small-btn del").click(function(){
                    if(!confirm("Delete cron for "+c.file+"?")) return;
                    $.post("custom/delete_announcement.php", {raw_line: c.raw}, function(r){
                        alert(r); loadCronList();
                    });
                }).appendTo(acts);

                row.append(acts);
                tbody.append(row);
            });
        }).fail(function() { alert("Failed to load cron list"); });
    }
    loadCronList();

    // Generate TTS
    $("#generate_tts").click(function() {
        let text = $("#tts_text").val().trim();
        let fn   = $("#tts_filename").val().trim();
        if (!text || !fn) { alert("Text and filename required"); return; }
        if (!/^[a-zA-Z0-9_-]+$/.test(fn)) {
            alert("Filename: letters, numbers, hyphen or underscore only");
            return;
        }
        $.post("custom/piper_generate.php", {text: text, filename: fn}, function(resp) {
            $("#tts_status").text(resp);
            if (resp.includes("Success")) {
                setTimeout(loadMP3Dropdown, 1500);
            }
        });
    });

    // Install from MP3/WAV
    $("#install_announcement").click(function() {
        let file = $("#mp3_select").val();
        if (!file) { alert("Select a file first"); return; }
        let min   = prompt("Minute (0-59 or *)", "0");
        let hour  = prompt("Hour (0-23, ranges, lists, *)", "*");
        let dom   = prompt("Day of month (1-31, *, ranges)", "*");
        let month = prompt("Month (1-12, *, ranges)", "*");
        let dow   = prompt("Day of week (0-7 Sun=0or7, *)", "*");
        let desc  = prompt("Description / name of this announcement", file);
        if (!desc) desc = file;

        $.post("custom/announcement.php", {
            file: file, min, hour, dom, month, dow, desc
        }, function(resp) {
            alert(resp);
            if (resp.includes("successful")) {
                loadULDropdown();
                loadCronList();
                loadMP3Dropdown();
            }
        });
    });

    // Delete MP3/WAV
    $("#delete_mp3").click(function() {
        let f = $("#mp3_select").val();
        if (!f || !confirm("Delete " + f + "?")) return;
        $.post("custom/delete_file.php", {type:"mp3", file:f}, function(r){
            alert(r);
            loadMP3Dropdown();
        });
    });

    // Delete .ul
    $("#delete_ul").click(function() {
        let f = $("#ul_select").val();
        if (!f || !confirm("Delete " + f + "?")) return;
        $.post("custom/delete_file.php", {type:"ul", file:f}, function(r){
            alert(r);
            loadULDropdown();
        });
    });

    // Play now from .ul dropdown
    $("#run_ul").click(function() {
        let f = $("#ul_select").val();
        if (!f) { alert("Select announcement first"); return; }
        $.post("custom/run_announcement.php", {file:f}, function(r){ alert(r); });
    });

});
</script>

<style>
    .small-btn { font-size:0.9em; padding:4px 8px; margin:0 3px; cursor:pointer; }
    .play  { background:#17a2b8; color:white; border:none; }
    .edit  { background:#ffc107; color:black; border:none; }
    .del   { background:#dc3545; color:white; border:none; }
    .submit { padding:6px 12px; margin:4px; }
</style>
