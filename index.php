<?php

header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config.php';

function sanitizeScreenName(string $screen): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $screen);
}

function loadSettings(string $settingsFile): array
{
    if (!file_exists($settingsFile)) {
        return [];
    }

    $json = file_get_contents($settingsFile);
    $settings = json_decode($json, true);

    return is_array($settings) ? $settings : [];
}

$screen = sanitizeScreenName($_GET['screen'] ?? 'main');

$mediaDir = SCREENS_DIR . '/' . $screen . '/media';
$settingsFile = SCREENS_DIR . '/' . $screen . '/settings.json';
$mediaUrl = SCREENS_URL . '/' . $screen . '/media';

$files = [];

if (is_dir($mediaDir)) {
    $settings = loadSettings($settingsFile);

    foreach (scandir($mediaDir) as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (in_array($extension, ALLOWED_EXTENSIONS, true)) {
            $files[] = [
                'src' => $mediaUrl . '/' . $file,
                'name' => $file,
                'duration' => $settings[$file]['duration'] ?? IMAGE_DEFAULT_DURATION,
            ];
        }
    }

    sort($files);
}
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

        img,
        video {
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

function preloadNext(nextIndex) {
    const item = media[nextIndex];

    if (!item) {
        return;
    }

    const file = item.src;

    if (file.toLowerCase().endsWith(".mp4")) {
        const video = document.createElement("video");

        video.src = file;
        video.preload = "auto";
    } else {
        const img = new Image();

        img.src = file;
    }
}

function reloadAfterPlaylist() {
    setTimeout(function () {
        location.reload();
    }, 1000);
}

function showNext() {
    const player = document.getElementById("player");

    if (media.length === 0) {
        player.innerHTML =
            "<div id='message'>Geen media beschikbaar</div>";

        return;
    }

    const item = media[index];
    const file = item.src;

    const nextIndex = (index + 1) % media.length;

    preloadNext(nextIndex);

    if (file.toLowerCase().endsWith(".mp4")) {
        const video = document.createElement("video");

        let movedToNext = false;

        function goNextOnce() {
            if (movedToNext) {
                return;
            }

            movedToNext = true;

            if (nextIndex === 0) {
                reloadAfterPlaylist();
            } else {
                index = nextIndex;
                showNext();
            }
        }

        video.src = file;
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.preload = "auto";

        video.onended = goNextOnce;
        video.onerror = goNextOnce;

        video.onloadedmetadata = function () {
            const fallbackTime = (video.duration + 2) * 1000;

            setTimeout(function () {
                goNextOnce();
            }, fallbackTime);
        };

        player.innerHTML = "";
        player.appendChild(video);

        video.load();

        video.play().catch(function () {
            goNextOnce();
        });

    } else {
        const img = document.createElement("img");

        img.src = file;

        img.onload = function () {
            player.innerHTML = "";
            player.appendChild(img);

            setTimeout(function () {

                if (nextIndex === 0) {
                    reloadAfterPlaylist();
                } else {
                    index = nextIndex;
                    showNext();
                }

            }, item.duration * 1000);
        };

        img.onerror = function () {

            if (nextIndex === 0) {
                reloadAfterPlaylist();
            } else {
                index = nextIndex;
                showNext();
            }
        };
    }
}

showNext();
</script>

</body>
</html>