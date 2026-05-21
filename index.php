<?php

header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config.php';

function loadSettings(): array
{
    if (!file_exists(SETTINGS_FILE)) {
        return [];
    }

    $json = file_get_contents(SETTINGS_FILE);
    $settings = json_decode($json, true);

    return is_array($settings) ? $settings : [];
}

$settings = loadSettings();
$files = [];

foreach (scandir(MEDIA_DIR) as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (in_array($extension, ALLOWED_EXTENSIONS, true)) {
        $files[] = [
            'src' => MEDIA_URL . '/' . $file,
            'name' => $file,
            'duration' => $settings[$file]['duration'] ?? IMAGE_DEFAULT_DURATION,
        ];
    }
}

sort($files);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signage Player</title>
    <style>
        html, body {
            margin: 0;
            width: 100%;
            height: 100%;
            background: black;
            overflow: hidden;
        }

        img, video {
            width: 100vw;
            height: 100vh;
            object-fit: contain;
            background: black;
        }

        #message {
            color: white;
            font-family: Arial, sans-serif;
            font-size: 32px;
            text-align: center;
            margin-top: 40vh;
        }
    </style>
</head>
<body>

<div id="player"></div>

<script>
const media = <?php echo json_encode($files); ?>;
let index = 0;

function showNext() {
    const player = document.getElementById("player");
    player.innerHTML = "";

    if (media.length === 0) {
        player.innerHTML = "<div id='message'>Geen media beschikbaar</div>";
        return;
    }

    const item = media[index];
    const file = item.src;

    index = (index + 1) % media.length;

    if (file.toLowerCase().endsWith(".mp4")) {
        const video = document.createElement("video");
        video.src = file;
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.onended = showNext;
        player.appendChild(video);
    } else {
        const img = document.createElement("img");
        img.src = file;
        player.appendChild(img);
        setTimeout(showNext, item.duration * 1000);
    }
}

showNext();
</script>

<script>
setInterval(function () {
    location.reload();
}, 3600000);
</script>

</body>
</html>