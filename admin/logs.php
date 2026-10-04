<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

/*
|--------------------------------------------------------------------------
| Get Activity Logs
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        id,
        action,
        description,
        entity_type,
        entity_id,
        created_at
    FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 100
");

$logs = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT COUNT(*)
    FROM activity_logs
");

$totalLogs = (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT COUNT(*)
    FROM activity_logs
    WHERE DATE(created_at) = CURDATE()
");

$todayLogs = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function actionLabel(string $action): string
{
    return match ($action) {
        'machine_status_update' => 'เปลี่ยนสถานะเครื่อง',
        'branch_status_update' => 'เปลี่ยนสถานะสาขา',
        'order_created' => 'สร้าง Order',
        'payment_success' => 'ชำระเงินสำเร็จ',
        'machine_started' => 'เริ่มการทำงาน',
        'machine_finished' => 'เครื่องทำงานเสร็จ',
        default => $action,
    };
}

function actionClass(string $action): string
{
    return match ($action) {
        'payment_success' => 'action-success',
        'machine_finished' => 'action-success',
        'machine_status_update' => 'action-machine',
        'branch_status_update' => 'action-branch',
        default => 'action-default',
    };
}

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Activity Logs | Laundry Admin</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f5f5f5;

            color: #111;
        }

        /* =========================
           Sidebar
        ========================= */

        .sidebar {
            position: fixed;

            left: 0;
            top: 0;
            bottom: 0;

            width: 240px;

            background: #111;

            color: white;

            padding: 28px 18px;
        }

        .brand {
            font-size: 24px;

            font-weight: 800;

            letter-spacing: 2px;

            padding: 0 14px 30px;
        }

        .admin-label {
            color: #888;

            font-size: 12px;

            padding: 0 14px 20px;

            text-transform: uppercase;
        }

        .nav {
            display: flex;

            flex-direction: column;

            gap: 8px;
        }

        .nav a {
            color: #ccc;

            text-decoration: none;

            padding: 13px 14px;

            border-radius: 10px;

            transition: 0.2s;
        }

        .nav a:hover,
        .nav a.active {
            background: #fff;

            color: #111;
        }

        /* =========================
           Main
        ========================= */

        .main {
            margin-left: 240px;

            padding: 32px;
        }

        .topbar {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 28px;
        }

        .page-title {
            margin: 0;

            font-size: 30px;

            font-weight: 800;
        }

        .page-subtitle {
            margin-top: 6px;

            color: #777;

            font-size: 14px;
        }

        .admin-badge {
            background: white;

            padding: 10px 16px;

            border-radius: 10px;

            font-size: 14px;

            box-shadow:
                0 2px 10px rgba(0, 0, 0, 0.05);
        }

        /* =========================
           Statistics
        ========================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;

            margin-bottom: 28px;
        }

        .stat-card {
            background: white;

            border-radius: 16px;

            padding: 22px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .stat-title {
            color: #777;

            font-size: 14px;

            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 30px;

            font-weight: 800;
        }

        .unit {
            color: #777;

            font-size: 14px;

            font-weight: 400;
        }

        /* =========================
           Section
        ========================= */

        .section {
            background: white;

            border-radius: 16px;

            padding: 24px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-title {
            margin: 0;

            font-size: 20px;

            font-weight: 750;
        }

        /* =========================
           Table
        ========================= */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }

        th {
            text-align: left;

            font-size: 13px;

            color: #777;

            font-weight: 600;

            padding: 12px;

            border-bottom: 1px solid #eee;

            white-space: nowrap;
        }

        td {
            padding: 15px 12px;

            border-bottom: 1px solid #eee;

            font-size: 14px;

            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* =========================
           Action
        ========================= */

        .action {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;
        }

        .action-success {
            background: #e8f7ed;

            color: #16733a;
        }

        .action-machine {
            background: #e8f0ff;

            color: #2455a4;
        }

        .action-branch {
            background: #fff5d9;

            color: #946d00;
        }

        .action-default {
            background: #f0f0f0;

            color: #555;
        }

        .description {
            max-width: 500px;

            line-height: 1.5;
        }

        .entity {
            font-family: monospace;

            color: #666;

            font-size: 12px;
        }

        .date {
            white-space: nowrap;

            color: #666;

            font-size: 13px;
        }

        .empty {
            text-align: center;

            color: #888;

            padding: 50px;
        }

        /* =========================
           Responsive
        ========================= */

        @media (max-width: 800px) {

            .sidebar {
                position: static;

                width: 100%;

                padding: 18px;
            }

            .brand {
                padding-bottom: 10px;
            }

            .admin-label {
                display: none;
            }

            .nav {
                flex-direction: row;

                overflow-x: auto;
            }

            .nav a {
                white-space: nowrap;
            }

            .main {
                margin-left: 0;

                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;

                align-items: flex-start;

                gap: 12px;
            }

        }

    </style>

</head>

<body>

    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="brand">
            LAUNDRY
        </div>

        <div class="admin-label">
            Administration
        </div>

        <nav class="nav">

            <a href="index.php">
                Dashboard
            </a>

            <a href="orders.php">
                Orders
            </a>

            <a href="machines.php">
                Machines
            </a>

            <a href="branches.php">
                Branches
            </a>

            <a
                href="logs.php"
                class="active"
            >
                Activity Logs
            </a>

            <a href="../index.php">
                ← กลับหน้าลูกค้า
            </a>

        </nav>

    </aside>


    <!-- Main -->

    <main class="main">

        <div class="topbar">

            <div>

                <h1 class="page-title">
                    Activity Logs
                </h1>

                <div class="page-subtitle">
                    ประวัติการทำงานของระบบ
                </div>

            </div>

            <div class="admin-badge">
                Admin
            </div>

        </div>


        <!-- Statistics -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    Logs ทั้งหมด
                </div>

                <div class="stat-value">

                    <?= number_format($totalLogs) ?>

                    <span class="unit">
                        รายการ
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Logs วันนี้
                </div>

                <div class="stat-value">

                    <?= number_format($todayLogs) ?>

                    <span class="unit">
                        รายการ
                    </span>

                </div>

            </div>

        </section>


        <!-- Logs -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    ประวัติการทำงานล่าสุด
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Action
                            </th>

                            <th>
                                รายละเอียด
                            </th>

                            <th>
                                Entity
                            </th>

                            <th>
                                เวลา
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($logs) === 0): ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="empty"
                                >
                                    ยังไม่มี Activity Logs
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($logs as $log): ?>

                                <tr>

                                    <td>
                                        <?= (int) $log['id'] ?>
                                    </td>


                                    <td>

                                        <span
                                            class="action <?= actionClass(
                                                $log['action']
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                actionLabel(
                                                    $log['action']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td class="description">

                                        <?= htmlspecialchars(
                                            $log['description'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $log['entity_type'] !== null
                                        ): ?>

                                            <span class="entity">

                                                <?= htmlspecialchars(
                                                    $log['entity_type'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                #

                                                <?= (int) $log['entity_id'] ?>

                                            </span>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <td class="date">

                                        <?= date(
                                            'd/m/Y H:i:s',
                                            strtotime(
                                                $log['created_at']
                                            )
                                        ) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>

</html>