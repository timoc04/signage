<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Uitgelogd</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #111827;
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-card {
            background: #1f2937;
            padding: 40px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 14px;
        }

        p {
            color: #d1d5db;
            margin-bottom: 24px;
        }

        a {
            display: inline-block;
            background: #2563eb;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 10px;
        }

        a:hover {
            background: #1d4ed8;
        }
    </style>
</head>

<body>

<div class="logout-card">
    <h1>Uitgelogd</h1>

    <p>Je bent succesvol uitgelogd uit het signage beheerpaneel.</p>

    <a href="login.php">
        Opnieuw inloggen
    </a>
</div>

</body>
</html>