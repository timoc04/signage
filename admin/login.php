<?php
session_start();
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Onjuist wachtwoord.';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Signage login</h1>

    <?php if ($error): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="password" name="password" placeholder="Wachtwoord">
        <button type="submit">Inloggen</button>
    </form>
</body>
</html>