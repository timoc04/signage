<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$message = '';

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

if (isset($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    $path = MEDIA_DIR . '/' . $fileToDelete;

    if (is_file($path)) {
        unlink($path);
        $message = 'Bestand verwijderd.';
    }
}

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
<html>
<head>
    <title>Signage Admin</title>
</head>
<body>
    <h1>Signage beheer</h1>

    <p>
        <a href="../index.php" target="_blank">Open player</a> |
        <a href="logout.php">Uitloggen</a>
    </p>

    <?php if ($message): ?>
        <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>

    <h2>Media uploaden</h2>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="media" accept=".jpg,.jpeg,.png,.webp,.mp4" required>
        <button type="submit">Uploaden</button>
    </form>

    <h2>Huidige media</h2>

    <ul>
        <?php foreach ($files as $file): ?>
            <li>
                <?php echo htmlspecialchars($file); ?>
                -
                <a href="?delete=<?php echo urlencode($file); ?>"
                   onclick="return confirm('Weet je zeker dat je dit bestand wilt verwijderen?');">
                   Verwijderen
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>