<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

// รายได้วันนี้
$stmt = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE payment_status = 'paid'
      AND DATE(paid_at) = CURDATE()
");

$todayRevenue = (float) $stmt->fetchColumn();


// จำนวน Order วันนี้
$stmt = $db->query("
    SELECT COUNT(*)
    FROM orders
    WHERE DATE(created_at) = CURDATE()
");

$todayOrders = (int) $stmt->fetchColumn();


// เครื่องกำลังทำงาน
$stmt = $db->query("
    SELECT COUNT(*)
    FROM machines
    WHERE status = 'washing'
");

$washingMachines = (int) $stmt->fetchColumn();


// เครื่องพร้อมใช้งาน
$stmt = $db->query("
    SELECT COUNT(*)
    FROM machines
    WHERE status = 'available'
");

$availableMachines = (int) $stmt->fetchColumn();


// เครื่องซ่อมบำรุง
$stmt = $db->query("
    SELECT COUNT(*)
    FROM machines
    WHERE status = 'maintenance'
");

$maintenanceMachines = (int) $stmt->fetchColumn();


// จำนวนเครื่องทั้งหมด
$stmt = $db->query("
    SELECT COUNT(*)
    FROM machines
");

$totalMachines = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Branch Machine Summary
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        b.id,
        b.name,
        b.status AS branch_status,

        COUNT(m.id) AS total_machines,

        SUM(
            CASE
                WHEN m.status = 'available'
                THEN 1
                ELSE 0
            END
        ) AS available_machines,

        SUM(
            CASE
                WHEN m.status = 'washing'
                THEN 1
                ELSE 0
            END
        ) AS washing_machines,

        SUM(
            CASE
                WHEN m.status = 'maintenance'
                THEN 1
                ELSE 0
            END
        ) AS maintenance_machines

    FROM branches b

    LEFT JOIN machines m
        ON m.branch_id = b.id

    GROUP BY
        b.id,
        b.name,
        b.status

    ORDER BY b.id ASC
");

$branches = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Recent Orders
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        o.id,
        o.transaction_id,
        o.total_amount,
        o.payment_status,
        o.created_at,
        o.paid_at,

        b.name AS branch_name,
        m.machine_code,
        m.name AS machine_name,
        wm.name AS mode_name

    FROM orders o

    INNER JOIN branches b
        ON b.id = o.branch_id

    INNER JOIN machines m
        ON m.id = o.machine_id

    INNER JOIN wash_modes wm
        ON wm.id = o.mode_id

    ORDER BY o.created_at DESC

    LIMIT 10
");

$recentOrders = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Helper
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

function machineStatusClass(string $status): string
{
    return match ($status) {
        'available' => 'machine-available',
        'washing' => 'machine-washing',
        'maintenance' => 'machine-maintenance',
        default => '',
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

    <title>Admin Dashboard | Laundry</title>

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
           Stat Cards
        ========================= */

        .stats {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

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

        .stat-unit {
            font-size: 14px;

            color: #777;

            margin-left: 4px;
        }

        /* =========================
           Section
        ========================= */

        .section {
            background: white;

            border-radius: 16px;

            padding: 24px;

            margin-bottom: 24px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 18px;
        }

        .section-title {
            margin: 0;

            font-size: 20px;

            font-weight: 750;
        }

        .section-link {
            color: #111;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;
        }

        /* =========================
           Branch Table
        ========================= */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th {
            text-align: left;

            font-size: 13px;

            color: #777;

            font-weight: 600;

            padding: 12px;

            border-bottom: 1px solid #eee;
        }

        td {
            padding: 14px 12px;

            border-bottom: 1px solid #eee;

            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* =========================
           Status
        ========================= */

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;
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

        .machine-status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;
        }

        .machine-available {
            background: #e8f7ed;

            color: #16733a;
        }

        .machine-washing {
            background: #e8f0ff;

            color: #2455a4;
        }

        .machine-maintenance {
            background: #fdeaea;

            color: #a32929;
        }

        /* =========================
           Order Table
        ========================= */

        .transaction {
            font-family: monospace;

            font-size: 12px;

            color: #555;
        }

        .money {
            font-weight: 700;
        }

        .empty {
            text-align: center;

            color: #888;

            padding: 30px;
        }

        /* =========================
           Responsive
        ========================= */

        @media (max-width: 1100px) {

            .stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 700px) {

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
                align-items: flex-start;

                gap: 12px;

                flex-direction: column;
            }

        }

    </style>

</head>

<body>

    <!-- =========================
         Sidebar
    ========================== -->

    <aside class="sidebar">

        <div class="brand">
            LAUNDRY
        </div>

        <div class="admin-label">
            Administration
        </div>

        <nav class="nav">

            <a
                href="index.php"
                class="active"
            >
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

            <a href="logs.php">
                Activity Logs
            </a>

            <a href="../index.php">
                ← กลับหน้าลูกค้า
            </a>

        </nav>

    </aside>


    <!-- =========================
         Main Content
    ========================== -->

    <main class="main">

        <div class="topbar">

            <div>

                <h1 class="page-title">
                    Dashboard
                </h1>

                <div class="page-subtitle">
                    ภาพรวมระบบ Smart Laundry
                </div>

            </div>

            <div class="admin-badge">
                Admin
            </div>

        </div>


        <!-- =========================
             Statistics
        ========================== -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    รายได้วันนี้
                </div>

                <div class="stat-value">
                    <?= number_format($todayRevenue, 2) ?>

                    <span class="stat-unit">
                        บาท
                    </span>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    รายการวันนี้
                </div>

                <div class="stat-value">
                    <?= number_format($todayOrders) ?>

                    <span class="stat-unit">
                        รายการ
                    </span>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    เครื่องกำลังทำงาน
                </div>

                <div class="stat-value">
                    <?= number_format($washingMachines) ?>

                    <span class="stat-unit">
                        / <?= number_format($totalMachines) ?>
                    </span>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    เครื่องพร้อมใช้งาน
                </div>

                <div class="stat-value">
                    <?= number_format($availableMachines) ?>

                    <span class="stat-unit">
                        เครื่อง
                    </span>
                </div>

            </div>

        </section>


        <!-- =========================
             Machine Overview
        ========================== -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    สถานะเครื่องซักผ้าแยกตามสาขา
                </h2>

                <a
                    href="machines.php"
                    class="section-link"
                >
                    ดูทั้งหมด →
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                สาขา
                            </th>

                            <th>
                                สถานะสาขา
                            </th>

                            <th>
                                เครื่องทั้งหมด
                            </th>

                            <th>
                                พร้อมใช้งาน
                            </th>

                            <th>
                                กำลังทำงาน
                            </th>

                            <th>
                                ซ่อมบำรุง
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($branches) === 0): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty"
                                >
                                    ยังไม่มีข้อมูลสาขา
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($branches as $branch): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $branch['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>

                                        <?php if ($branch['branch_status'] === 'open'): ?>

                                            <span class="status status-paid">
                                                เปิดให้บริการ
                                            </span>

                                        <?php else: ?>

                                            <span class="status status-cancelled">
                                                ปิดให้บริการ
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= (int) $branch['total_machines'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $branch['available_machines'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $branch['washing_machines'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $branch['maintenance_machines'] ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =========================
             Quick Status
        ========================== -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    สถานะระบบ
                </h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                สถานะ
                            </th>

                            <th>
                                จำนวน
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>
                                <span class="machine-status machine-available">
                                    <?= machineStatusLabel('available') ?>
                                </span>
                            </td>

                            <td>
                                <?= number_format($availableMachines) ?>
                                เครื่อง
                            </td>

                        </tr>

                        <tr>

                            <td>
                                <span class="machine-status machine-washing">
                                    <?= machineStatusLabel('washing') ?>
                                </span>
                            </td>

                            <td>
                                <?= number_format($washingMachines) ?>
                                เครื่อง
                            </td>

                        </tr>

                        <tr>

                            <td>
                                <span class="machine-status machine-maintenance">
                                    <?= machineStatusLabel('maintenance') ?>
                                </span>
                            </td>

                            <td>
                                <?= number_format($maintenanceMachines) ?>
                                เครื่อง
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =========================
             Recent Orders
        ========================== -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    รายการล่าสุด
                </h2>

                <a
                    href="orders.php"
                    class="section-link"
                >
                    ดู Order ทั้งหมด →
                </a>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Transaction
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
                                จำนวนเงิน
                            </th>

                            <th>
                                สถานะ
                            </th>

                            <th>
                                เวลา
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (count($recentOrders) === 0): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="empty"
                                >
                                    ยังไม่มีรายการ
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentOrders as $order): ?>

                                <tr>

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

                                    <td>
                                        <?= htmlspecialchars(
                                            $order['machine_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $order['mode_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
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

                                    <td>

                                        <?= htmlspecialchars(
                                            date(
                                                'd/m/Y H:i',
                                                strtotime($order['created_at'])
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
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
