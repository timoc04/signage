<?php
session_start();

require_once __DIR__ . '/../config.php';

$timeout = SESSION_TIMEOUT;

/**
 * Check session timeout.
 */
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
        move_uploaded_file($file['tmp_name'], MEDIA_DIR . '/' . $safeName);

        $message = 'Bestand geüpload.';
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

    $oldPath = MEDIA_DIR . '/' . $oldName;
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
            $newPath = MEDIA_DIR . '/' . $safeNewName;

            if (is_file($newPath)) {
                $message = 'Er bestaat al een bestand met deze naam.';
            } else {
                rename($oldPath, $newPath);

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
    $path = MEDIA_DIR . '/' . $fileToDelete;

    if (is_file($path)) {
        unlink($path);

        $message = 'Bestand verwijderd.';
    }
}

/**
 * Get media files.
 */
$files = [];

foreach (scandir(MEDIA_DIR) as $file) {
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
        input[type="text"] {
            box-sizing: border-box;
            padding: 10px;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            width: 100%;
            margin-bottom: 10px;
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

        .empty {
            color: #6b7280;
        }
    </style>
</head>

<body>

<header>
    <h1>Signage beheer</h1>

    <p>
        Upload, hernoem en verwijder media voor je fullscreen signage player.
    </p>

    <div class="top-links">
        <a class="button" href="../index.php" target="_blank">
            Open player
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
        <h2>Media uploaden</h2>

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
                Er is nog geen media geüpload.
            </p>
        <?php else: ?>
            <div class="media-grid">
                <?php foreach ($files as $file): ?>
                    <?php
                        $extension = strtolower(
                            pathinfo($file, PATHINFO_EXTENSION)
                        );

                        $url = '../media/' . rawurlencode($file);
                    ?>

                    <div class="media-item">
                        <div class="preview">
                            <?php if ($extension === 'mp4'): ?>
                                <video
                                    src="<?php echo htmlspecialchars($url); ?>"
                                    muted
                                ></video>
                            <?php else: ?>
                                <img
                                    src="<?php echo htmlspecialchars($url); ?>"
                                    alt=""
                                >
                            <?php endif; ?>
                        </div>

                        <div class="file-info">
                            <div class="filename">
                                <?php echo htmlspecialchars($file); ?>
                            </div>

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

                                <button
                                    class="rename-button"
                                    type="submit"
                                >
                                    Hernoemen
                                </button>
                            </form>

                            <a
                                class="delete"
                                href="?delete=<?php echo urlencode($file); ?>"
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