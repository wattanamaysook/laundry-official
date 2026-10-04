<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        o.id,
        o.transaction_id,
        o.price,
        o.duration_minutes,
        o.total_amount,
        o.payment_status,
        o.created_at,
        o.paid_at,

        b.name AS branch_name,

        m.machine_code,
        m.name AS machine_name,
        m.status AS machine_status,

        wm.name AS mode_name

    FROM orders o

    INNER JOIN branches b
        ON b.id = o.branch_id

    INNER JOIN machines m
        ON m.id = o.machine_id

    INNER JOIN wash_modes wm
        ON wm.id = o.mode_id

    ORDER BY o.created_at DESC
");

$orders = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT COUNT(*)
    FROM orders
");

$totalOrders = (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT COUNT(*)
    FROM orders
    WHERE payment_status = 'paid'
");

$paidOrders = (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE payment_status = 'paid'
");

$totalRevenue = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function paymentStatusLabel(string $status): string
{
    return match ($status) {
        'paid' => 'ชำระเงินแล้ว',
        'pending' => 'รอชำระเงิน',
        'failed' => 'ชำระเงินไม่สำเร็จ',
        'cancelled' => 'ยกเลิก',
        default => $status,
    };
}

function paymentStatusClass(string $status): string
{
    return match ($status) {
        'paid' => 'status-paid',
        'pending' => 'status-pending',
        'failed' => 'status-failed',
        'cancelled' => 'status-cancelled',
        default => '',
    };
}

function machineStatusLabel(string $status): string
{
    return match ($status) {
        'available' => 'พร้อมใช้งาน',
        'washing' => 'กำลังทำงาน',
        'maintenance' => 'ซ่อมบำรุง',
        default => $status,
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

    <title>Orders | Laundry Admin</title>

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
           Stats
        ========================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

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
            font-size: 28px;

            font-weight: 800;
        }

        .unit {
            color: #777;

            font-size: 14px;

            font-weight: 400;
        }

        /* =========================
           Orders Section
        ========================= */

        .section {
            background: white;

            border-radius: 16px;

            padding: 24px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;
        }

        .section-title {
            margin: 0;

            font-size: 20px;

            font-weight: 750;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;
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
            padding: 14px 12px;

            border-bottom: 1px solid #eee;

            font-size: 14px;

            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .transaction {
            font-family: monospace;

            font-size: 12px;

            color: #555;

            white-space: nowrap;
        }

        .money {
            font-weight: 700;

            white-space: nowrap;
        }

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;
        }

        .status-paid {
            background: #e8f7ed;

            color: #16733a;
        }

        .status-pending {
            background: #fff5d9;

            color: #946d00;
        }

        .status-failed,
        .status-cancelled {
            background: #fdeaea;

            color: #a32929;
        }

        .machine {
            white-space: nowrap;
        }

        .machine-code {
            font-family: monospace;

            font-weight: 700;
        }

        .date {
            white-space: nowrap;

            color: #666;

            font-size: 13px;
        }

        .empty {
            text-align: center;

            color: #888;

            padding: 40px;
        }

        /* =========================
           Responsive
        ========================= */

        @media (max-width: 900px) {

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

            <a
                href="orders.php"
                class="active"
            >
                Orders
            </a>

            <a href="machines.php">
                Machines
            </a>

            <a href="branches.php">
                Branches
            </a>

            <a href="logs.php">
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
                    Orders
                </h1>

                <div class="page-subtitle">
                    รายการทำธุรกรรมทั้งหมด
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
                    Order ทั้งหมด
                </div>

                <div class="stat-value">

                    <?= number_format($totalOrders) ?>

                    <span class="unit">
                        รายการ
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    ชำระเงินแล้ว
                </div>

                <div class="stat-value">

                    <?= number_format($paidOrders) ?>

                    <span class="unit">
                        รายการ
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    รายได้รวม
                </div>

                <div class="stat-value">

                    <?= number_format($totalRevenue, 2) ?>

                    <span class="unit">
                        บาท
                    </span>

                </div>

            </div>

        </section>


        <!-- Orders -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    รายการธุรกรรม
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
                                Transaction ID
                            </th>

                            <th>
                                สาขา
                            </th>

                            <th>
                                เครื่อง
                            </th>

                            <th>
                                โหมด
                            </th>

                            <th>
                                ระยะเวลา
                            </th>

                            <th>
                                ราคา
                            </th>

                            <th>
                                สถานะ
                            </th>

                            <th>
                                สร้างรายการ
                            </th>

                            <th>
                                ชำระเงิน
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($orders) === 0): ?>

                            <tr>

                                <td
                                    colspan="10"
                                    class="empty"
                                >
                                    ยังไม่มีรายการ Order
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($orders as $order): ?>

                                <tr>

                                    <td>
                                        <?= (int) $order['id'] ?>
                                    </td>


                                    <td>

                                        <span class="transaction">
                                            <?= htmlspecialchars(
                                                $order['transaction_id'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $order['branch_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td class="machine">

                                        <div class="machine-code">

                                            <?= htmlspecialchars(
                                                $order['machine_code'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </div>

                                        <div>
                                            <?= htmlspecialchars(
                                                $order['machine_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </div>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $order['mode_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= (int) $order['duration_minutes'] ?>

                                        นาที

                                    </td>


                                    <td class="money">

                                        <?= number_format(
                                            (float) $order['total_amount'],
                                            2
                                        ) ?>

                                        บาท

                                    </td>


                                    <td>

                                        <span
                                            class="status <?= paymentStatusClass(
                                                $order['payment_status']
                                            ) ?>"
                                        >

                                            <?= paymentStatusLabel(
                                                $order['payment_status']
                                            ) ?>

                                        </span>

                                    </td>


                                    <td class="date">

                                        <?= date(
                                            'd/m/Y H:i',
                                            strtotime($order['created_at'])
                                        ) ?>

                                    </td>


                                    <td class="date">

                                        <?php if ($order['paid_at'] !== null): ?>

                                            <?= date(
                                                'd/m/Y H:i',
                                                strtotime($order['paid_at'])
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

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