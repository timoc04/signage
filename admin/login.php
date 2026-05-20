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
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Signage Login</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #111827, #1e3a8a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 18px;
            padding: 36px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 30px;
            color: #111827;
        }

        p {
            color: #6b7280;
            margin-bottom: 24px;
        }

        input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        button {
            width: 100%;
            border: none;
            background: #2563eb;
            color: white;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .footer {
            margin-top: 22px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="login-card">
    <h1>Signage beheer</h1>

    <p>Log in om media te uploaden en beheren.</p>

    <?php if ($error): ?>
        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="password"
               name="password"
               placeholder="Wachtwoord"
               required>

        <button type="submit">
            Inloggen
        </button>
    </form>

    <div class="footer">
        TimoClaessens.nl Signage
    </div>
</div>

</body>
</html>