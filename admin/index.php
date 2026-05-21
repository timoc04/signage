<?php
session_start();

require_once __DIR__ . '/../config.php';

$timeout = SESSION_TIMEOUT;

if (isset($_SESSION['last_activity'])) {
    $inactiveTime = time() - $_SESSION['last_activity'];

    if ($inactiveTime > $timeout) {
        session_unset();
        session_destroy();

        header('Location: logout.php?timeout=1');
        exit;
    }
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$message = '';

function sanitizeScreenName(string $screen): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $screen);
}

function getScreens(): array
{
    if (!is_dir(SCREENS_DIR)) {
        mkdir(SCREENS_DIR, 0755, true);
    }

    $screens = [];

    foreach (scandir(SCREENS_DIR) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (is_dir(SCREENS_DIR . '/' . $item)) {
            $screens[] = $item;
        }
    }

    sort($screens);

    return $screens;
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

function saveSettings(string $settingsFile, array $settings): void
{
    file_put_contents(
        $settingsFile,
        json_encode($settings, JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function isImageFile(string $file): bool
{
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
}

/**
 * Create screen.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['new_screen'])
) {
    $newScreen = sanitizeScreenName(trim($_POST['new_screen']));

    if ($newScreen === '') {
        $message = 'Schermnaam mag niet leeg zijn.';
    } else {
        $screenDir = SCREENS_DIR . '/' . $newScreen;
        $mediaDir = $screenDir . '/media';
        $settingsFile = $screenDir . '/settings.json';

        if (is_dir($screenDir)) {
            $message = 'Dit scherm bestaat al.';
        } else {
            mkdir($mediaDir, 0755, true);
            file_put_contents($settingsFile, '{}');

            $message = 'Scherm aangemaakt.';
        }
    }
}

$screens = getScreens();

if (empty($screens)) {
    $mainDir = SCREENS_DIR . '/main/media';
    mkdir($mainDir, 0755, true);
    file_put_contents(SCREENS_DIR . '/main/settings.json', '{}');

    $screens = ['main'];
}

$currentScreen = sanitizeScreenName($_GET['screen'] ?? $screens[0]);

if (!in_array($currentScreen, $screens, true)) {
    $currentScreen = $screens[0];
}

$screenDir = SCREENS_DIR . '/' . $currentScreen;
$mediaDir = $screenDir . '/media';
$settingsFile = $screenDir . '/settings.json';
$mediaUrl = '../screens/' . $currentScreen . '/media';

if (!is_dir($mediaDir)) {
    mkdir($mediaDir, 0755, true);
}

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, '{}');
}

$settings = loadSettings($settingsFile);

/**
 * Upload media file.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media'])) {
    $file = $_FILES['media'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        $message = 'Bestandstype niet toegestaan.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload mislukt.';
    } else {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
        move_uploaded_file($file['tmp_name'], $mediaDir . '/' . $safeName);

        if (isImageFile($safeName)) {
            $settings[$safeName]['duration'] = IMAGE_DEFAULT_DURATION;
            saveSettings($settingsFile, $settings);
        }

        $message = 'Bestand geüpload.';
    }
}

/**
 * Update image duration.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['duration_file'])
    && isset($_POST['duration_seconds'])
) {
    $fileName = basename($_POST['duration_file']);
    $duration = (int) $_POST['duration_seconds'];

    if ($duration < 1) {
        $message = 'Weergavetijd moet minimaal 1 seconde zijn.';
    } elseif (!is_file($mediaDir . '/' . $fileName)) {
        $message = 'Bestand bestaat niet.';
    } elseif (!isImageFile($fileName)) {
        $message = 'Weergavetijd kan alleen bij afbeeldingen worden aangepast.';
    } else {
        $settings[$fileName]['duration'] = $duration;
        saveSettings($settingsFile, $settings);

        $message = 'Weergavetijd opgeslagen.';
    }
}

/**
 * Rename media file.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['rename_old'])
    && isset($_POST['rename_new'])
) {
    $oldName = basename($_POST['rename_old']);
    $newName = trim(basename($_POST['rename_new']));

    $oldPath = $mediaDir . '/' . $oldName;
    $oldExtension = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));

    if ($newName === '') {
        $message = 'Nieuwe naam mag niet leeg zijn.';
    } elseif (!is_file($oldPath)) {
        $message = 'Oorspronkelijk bestand bestaat niet.';
    } else {
        if (!str_contains($newName, '.')) {
            $newName .= '.' . $oldExtension;
        }

        $newExtension = strtolower(pathinfo($newName, PATHINFO_EXTENSION));

        if (!in_array($newExtension, ALLOWED_EXTENSIONS, true)) {
            $message = 'Nieuwe bestandstype is niet toegestaan.';
        } else {
            $safeNewName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($newName));
            $newPath = $mediaDir . '/' . $safeNewName;

            if (is_file($newPath)) {
                $message = 'Er bestaat al een bestand met deze naam.';
            } else {
                rename($oldPath, $newPath);

                if (isset($settings[$oldName])) {
                    $settings[$safeNewName] = $settings[$oldName];
                    unset($settings[$oldName]);
                    saveSettings($settingsFile, $settings);
                }

                $message = 'Bestand hernoemd.';
            }
        }
    }
}

/**
 * Delete media file.
 */
if (isset($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    $path = $mediaDir . '/' . $fileToDelete;

    if (is_file($path)) {
        unlink($path);

        unset($settings[$fileToDelete]);
        saveSettings($settingsFile, $settings);

        $message = 'Bestand verwijderd.';
    }
}

/**
 * Get media files.
 */
$files = [];

foreach (scandir($mediaDir) as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (in_array($extension, ALLOWED_EXTENSIONS, true)) {
        $files[] = $file;
    }
}

sort($files);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Signage Admin</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        header {
            background: #111827;
            color: white;
            padding: 24px 40px;
        }

        header h1 {
            margin: 0;
            font-size: 28px;
        }

        header p {
            margin: 6px 0 0;
            color: #cbd5e1;
        }

        main {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .top-links {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .button,
        button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .button.secondary {
            background: #374151;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        input[type="file"],
        input[type="text"],
        input[type="number"],
        select {
            box-sizing: border-box;
            padding: 10px;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            width: 100%;
            margin-bottom: 10px;
        }

        .screen-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 18px;
        }

        .media-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .preview {
            height: 130px;
            background: #111827;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview img,
        .preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-info {
            padding: 12px;
            font-size: 14px;
        }

        .filename {
            word-break: break-all;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .rename-form {
            margin-bottom: 12px;
        }

        .rename-button {
            width: 100%;
            background: #2563eb;
        }

        .delete {
            display: block;
            text-align: center;
            color: white;
            background: #dc2626;
            padding: 9px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .empty,
        .small-text {
            color: #6b7280;
        }

        .small-text {
            font-size: 13px;
            margin-bottom: 12px;
        }

        @media (max-width: 800px) {
            .screen-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Signage beheer</h1>

    <p>
        Beheer meerdere signage schermen, media en weergavetijden.
    </p>

    <div class="top-links">
        <a class="button" href="../index.php?screen=<?php echo urlencode($currentScreen); ?>" target="_blank">
            Open huidig scherm
        </a>

        <a class="button secondary" href="logout.php">
            Uitloggen
        </a>
    </div>
</header>

<main>
    <?php if ($message): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Schermen</h2>

        <div class="screen-actions">
            <form method="GET">
                <label>Scherm kiezen</label>

                <select name="screen" onchange="this.form.submit()">
                    <?php foreach ($screens as $screen): ?>
                        <option
                            value="<?php echo htmlspecialchars($screen); ?>"
                            <?php echo $screen === $currentScreen ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($screen); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <form method="POST">
                <label>Nieuw scherm aanmaken</label>

                <input
                    type="text"
                    name="new_screen"
                    placeholder="Bijvoorbeeld: bar, entree, zaal1"
                    required
                >

                <button type="submit">
                    Scherm aanmaken
                </button>
            </form>
        </div>

        <p class="small-text">
            Player URL huidig scherm:
            <strong>
                /signage/<?php echo htmlspecialchars($currentScreen); ?>
            </strong>
        </p>
    </section>

    <section class="card">
        <h2>Media uploaden voor: <?php echo htmlspecialchars($currentScreen); ?></h2>

        <form method="POST" enctype="multipart/form-data">
            <input
                type="file"
                name="media"
                accept=".jpg,.jpeg,.png,.webp,.mp4"
                required
            >

            <button type="submit">
                Uploaden
            </button>
        </form>
    </section>

    <section class="card">
        <h2>Huidige media</h2>

        <?php if (empty($files)): ?>
            <p class="empty">
                Er is nog geen media geüpload voor dit scherm.
            </p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($files as $file): ?>
                    <?php
                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        $url = $mediaUrl . '/' . rawurlencode($file);
                        $isImage = isImageFile($file);
                        $duration = $settings[$file]['duration'] ?? IMAGE_DEFAULT_DURATION;
                    ?>

                    <div class="media-item">
                        <div class="preview">
                            <?php if ($extension === 'mp4'): ?>
                                <video src="<?php echo htmlspecialchars($url); ?>" muted></video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($url); ?>" alt="">
                            <?php endif; ?>
                        </div>

                        <div class="file-info">
                            <div class="filename">
                                <?php echo htmlspecialchars($file); ?>
                            </div>

                            <?php if ($isImage): ?>
                                <form class="rename-form" method="POST">
                                    <input
                                        type="hidden"
                                        name="duration_file"
                                        value="<?php echo htmlspecialchars($file); ?>"
                                    >

                                    <input
                                        type="number"
                                        name="duration_seconds"
                                        min="1"
                                        value="<?php echo htmlspecialchars((string) $duration); ?>"
                                        required
                                    >

                                    <button class="rename-button" type="submit">
                                        Weergavetijd opslaan
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="small-text">
                                    Video wordt volledig afgespeeld.
                                </div>
                            <?php endif; ?>

                            <form class="rename-form" method="POST">
                                <input
                                    type="hidden"
                                    name="rename_old"
                                    value="<?php echo htmlspecialchars($file); ?>"
                                >

                                <input
                                    type="text"
                                    name="rename_new"
                                    placeholder="Nieuwe naam"
                                    required
                                >

                                <button class="rename-button" type="submit">
                                    Hernoemen
                                </button>
                            </form>

                            <a
                                class="delete"
                                href="?screen=<?php echo urlencode($currentScreen); ?>&delete=<?php echo urlencode($file); ?>"
                                onclick="return confirm('Weet je zeker dat je dit bestand wilt verwijderen?');"
                            >
                                Verwijderen
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
const timeoutSeconds = <?php echo SESSION_TIMEOUT; ?>;

setTimeout(function () {
    window.location.href = "logout.php?timeout=1";
}, timeoutSeconds * 1000);
</script>

</body>
</html>