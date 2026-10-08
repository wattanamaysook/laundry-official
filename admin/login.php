<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'กรุณากรอก Username และ Password';

    } else {

        try {

            $db = databaseConnection();

            $stmt = $db->prepare(
                'SELECT id, username, password_hash, name
                 FROM admins
                 WHERE username = :username
                 AND is_active = 1
                 LIMIT 1'
            );

            $stmt->execute([
                ':username' => $username,
            ]);

            $admin = $stmt->fetch();

            if (
                $admin &&
                password_verify($password, $admin['password_hash'])
            ) {

                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];

                header('Location: index.php');
                exit;
            }

            $error = 'Username หรือ Password ไม่ถูกต้อง';

        } catch (Throwable $e) {

            $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';

        }
    }
}

?>

<!DOCTYPE html>

<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Login | Laundry Official</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #0f0f0f;
    color: white;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
}

.login-card {

    width: min(420px, calc(100% - 32px));

    padding: 32px;

    background: #181818;

    border: 1px solid #2d2d2d;

    border-radius: 16px;

    box-shadow:
        0 20px 60px rgba(0,0,0,.45);
}

h1 {

    margin: 0 0 8px;

    font-size: 28px;
}

.subtitle {

    margin: 0 0 28px;

    color: #999;
}

label {

    display: block;

    margin-bottom: 8px;

    font-size: 14px;
}

input {

    width: 100%;

    padding: 13px 14px;

    margin-bottom: 18px;

    border: 1px solid #333;

    border-radius: 10px;

    background: #101010;

    color: white;

    outline: none;
}

input:focus {

    border-color: #777;
}

button {

    width: 100%;

    padding: 13px;

    border: 0;

    border-radius: 10px;

    background: white;

    color: #111;

    font-weight: 700;

    cursor: pointer;
}

button:hover {

    opacity: .9;
}

.error {

    margin-bottom: 18px;

    padding: 12px;

    border-radius: 10px;

    background: #351818;

    color: #ff9b9b;

    font-size: 14px;
}

</style>

</head>

<body>

<div class="login-card">

    <h1>Admin Login</h1>

    <p class="subtitle">
        Laundry Official
    </p>

    <?php if ($error !== ''): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <form method="POST">

        <label for="username">
            Username
        </label>

        <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
        >

        <label for="password">
            Password
        </label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>

</html>
