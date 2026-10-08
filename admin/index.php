<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM machines
");

$totalMachines = (int) $stmt->fetch()['total'];

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM machines
    WHERE status = 'washing'
");

$washingMachines = (int) $stmt->fetch()['total'];

$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM branches
");

$totalBranches = (int) $stmt->fetch()['total'];

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Laundry Official</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #0f0f0f;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .navbar {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            background: #181818;
            border-bottom: 1px solid #2d2d2d;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
        }

        .admin {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout {
            padding: 8px 14px;
            border-radius: 8px;
            background: #333;
            color: #fff;
            text-decoration: none;
        }

        .logout:hover {
            background: #444;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 40px 20px;
        }

        h1 {
            margin-top: 0;
        }

        .subtitle {
            color: #999;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .card {
            padding: 25px;
            background: #181818;
            border: 1px solid #2d2d2d;
            border-radius: 16px;
        }

        .card-title {
            color: #999;
            font-size: 14px;
        }

        .card-value {
            margin-top: 10px;
            font-size: 36px;
            font-weight: 700;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 35px;
        }

        .menu a {
            padding: 18px;
            background: #181818;
            border: 1px solid #2d2d2d;
            border-radius: 12px;
            color: #fff;
            text-decoration: none;
        }

        .menu a:hover {
            background: #222;
        }

        @media (max-width: 800px) {

            .cards {
                grid-template-columns: 1fr;
            }

            .menu {
                grid-template-columns: 1fr 1fr;
            }

        }

    </style>

</head>

<body>

<header class="navbar">

    <div class="logo">
        Laundry Official — Admin
    </div>

    <div class="admin">

        <span>
            <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
        </span>

        <a class="logout" href="logout.php">
            Logout
        </a>

    </div>

</header>

<main class="container">

    <h1>Dashboard</h1>

    <p class="subtitle">
        ระบบจัดการ Laundry Official
    </p>

    <section class="cards">

        <div class="card">

            <div class="card-title">
                จำนวนเครื่องทั้งหมด
            </div>

            <div class="card-value">
                <?= $totalMachines ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">
                เครื่องกำลังซัก
            </div>

            <div class="card-value">
                <?= $washingMachines ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">
                จำนวนสาขา
            </div>

            <div class="card-value">
                <?= $totalBranches ?>
            </div>

        </div>

    </section>

    <section class="menu">

        <a href="machines.php">
            🧺 จัดการเครื่องซักผ้า
        </a>

        <a href="branches.php">
            🏢 จัดการสาขา
        </a>

        <a href="orders.php">
            💳 รายการคำสั่งซื้อ
        </a>

        <a href="logs.php">
            📋 Activity Logs
        </a>

    </section>

</main>

</body>

</html>