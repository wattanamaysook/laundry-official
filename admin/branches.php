<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

/*
|--------------------------------------------------------------------------
| Handle Branch Status Update
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $branchId = isset($_POST['branch_id'])
        ? (int) $_POST['branch_id']
        : 0;

    $newStatus = $_POST['status'] ?? '';

    $allowedStatuses = [
        'open',
        'closed',
    ];

    if (
        $branchId <= 0 ||
        !in_array($newStatus, $allowedStatuses, true)
    ) {

        $message = 'ข้อมูลไม่ถูกต้อง';
        $messageType = 'error';

    } else {

        $stmt = $db->prepare("
            SELECT id
            FROM branches
            WHERE id = ?
        ");

        $stmt->execute([$branchId]);

        $branchExists = $stmt->fetchColumn();

        if ($branchExists === false) {

            $message = 'ไม่พบสาขาที่ต้องการแก้ไข';
            $messageType = 'error';

        } else {

            $stmt = $db->prepare("
                UPDATE branches
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $branchId,
            ]);

            $message = 'อัปเดตสถานะสาขาเรียบร้อยแล้ว';
            $messageType = 'success';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Branch Statistics
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT COUNT(*)
    FROM branches
");

$totalBranches = (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT COUNT(*)
    FROM branches
    WHERE status = 'open'
");

$openBranches = (int) $stmt->fetchColumn();


$stmt = $db->query("
    SELECT COUNT(*)
    FROM branches
    WHERE status = 'closed'
");

$closedBranches = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Get Branches
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        b.id,
        b.name,
        b.address,
        b.status,

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
        b.address,
        b.status

    ORDER BY b.id ASC
");

$branches = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function branchStatusLabel(string $status): string
{
    return match ($status) {
        'open' => 'เปิดให้บริการ',
        'closed' => 'ปิดให้บริการ',
        default => $status,
    };
}

function branchStatusClass(string $status): string
{
    return match ($status) {
        'open' => 'status-open',
        'closed' => 'status-closed',
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

    <title>Branches | Laundry Admin</title>

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
            font-size: 30px;

            font-weight: 800;
        }

        .unit {
            color: #777;

            font-size: 14px;

            font-weight: 400;
        }

        /* =========================
           Message
        ========================= */

        .message {
            padding: 14px 18px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 14px;

            font-weight: 600;
        }

        .message-success {
            background: #e8f7ed;

            color: #16733a;
        }

        .message-error {
            background: #fdeaea;

            color: #a32929;
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

        /* =========================
           Table
        ========================= */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
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
            padding: 16px 12px;

            border-bottom: 1px solid #eee;

            font-size: 14px;

            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .branch-name {
            font-weight: 700;
        }

        .address {
            color: #777;

            font-size: 13px;

            margin-top: 4px;
        }

        /* =========================
           Status
        ========================= */

        .status {
            display: inline-block;

            padding: 6px 11px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;

            white-space: nowrap;
        }

        .status-open {
            background: #e8f7ed;

            color: #16733a;
        }

        .status-closed {
            background: #fdeaea;

            color: #a32929;
        }

        /* =========================
           Machine Summary
        ========================= */

        .machine-summary {
            display: flex;

            flex-wrap: wrap;

            gap: 6px;
        }

        .machine-count {
            font-size: 12px;

            padding: 4px 8px;

            border-radius: 7px;

            background: #f1f1f1;
        }

        .available {
            color: #16733a;
        }

        .washing {
            color: #2455a4;
        }

        .maintenance {
            color: #a32929;
        }

        /* =========================
           Form
        ========================= */

        .status-form {
            display: flex;

            align-items: center;

            gap: 8px;
        }

        select {
            padding: 8px 10px;

            border: 1px solid #ddd;

            border-radius: 8px;

            background: white;

            font-size: 13px;

            cursor: pointer;
        }

        button {
            border: none;

            background: #111;

            color: white;

            padding: 8px 13px;

            border-radius: 8px;

            font-size: 13px;

            cursor: pointer;
        }

        button:hover {
            background: #333;
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

            <a href="index.php">
                Dashboard
            </a>

            <a href="orders.php">
                Orders
            </a>

            <a href="machines.php">
                Machines
            </a>

            <a
                href="branches.php"
                class="active"
            >
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
         Main
    ========================== -->

    <main class="main">

        <div class="topbar">

            <div>

                <h1 class="page-title">
                    Branches
                </h1>

                <div class="page-subtitle">
                    จัดการสถานะและตรวจสอบข้อมูลแต่ละสาขา
                </div>

            </div>

            <div class="admin-badge">
                Admin
            </div>

        </div>


        <!-- =========================
             Message
        ========================= -->

        <?php if ($message !== ''): ?>

            <div
                class="message <?= $messageType === 'success'
                    ? 'message-success'
                    : 'message-error' ?>"
            >

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =========================
             Statistics
        ========================= -->

        <section class="stats">

            <div class="stat-card">

                <div class="stat-title">
                    สาขาทั้งหมด
                </div>

                <div class="stat-value">

                    <?= number_format($totalBranches) ?>

                    <span class="unit">
                        สาขา
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    เปิดให้บริการ
                </div>

                <div class="stat-value">

                    <?= number_format($openBranches) ?>

                    <span class="unit">
                        สาขา
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    ปิดให้บริการ
                </div>

                <div class="stat-value">

                    <?= number_format($closedBranches) ?>

                    <span class="unit">
                        สาขา
                    </span>

                </div>

            </div>

        </section>


        <!-- =========================
             Branch Table
        ========================= -->

        <section class="section">

            <div class="section-header">

                <h2 class="section-title">
                    รายการสาขา
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
                                สาขา
                            </th>

                            <th>
                                ที่อยู่
                            </th>

                            <th>
                                สถานะสาขา
                            </th>

                            <th>
                                เครื่อง
                            </th>

                            <th>
                                จัดการ
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (count($branches) === 0): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    style="text-align:center;padding:40px;color:#888;"
                                >
                                    ยังไม่มีข้อมูลสาขา
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($branches as $branch): ?>

                                <tr>

                                    <td>
                                        <?= (int) $branch['id'] ?>
                                    </td>


                                    <td>

                                        <div class="branch-name">

                                            <?= htmlspecialchars(
                                                $branch['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="address">

                                            <?= htmlspecialchars(
                                                $branch['address'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span
                                            class="status <?= branchStatusClass(
                                                $branch['status']
                                            ) ?>"
                                        >

                                            <?= branchStatusLabel(
                                                $branch['status']
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="machine-summary">

                                            <span class="machine-count">
                                                ทั้งหมด
                                                <?= (int) $branch['total_machines'] ?>
                                            </span>

                                            <span class="machine-count available">
                                                พร้อมใช้
                                                <?= (int) $branch['available_machines'] ?>
                                            </span>

                                            <span class="machine-count washing">
                                                กำลังทำงาน
                                                <?= (int) $branch['washing_machines'] ?>
                                            </span>

                                            <span class="machine-count maintenance">
                                                ซ่อม
                                                <?= (int) $branch['maintenance_machines'] ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <form
                                            method="POST"
                                            class="status-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="branch_id"
                                                value="<?= (int) $branch['id'] ?>"
                                            >

                                            <select name="status">

                                                <option
                                                    value="open"
                                                    <?= $branch['status'] === 'open'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    เปิดให้บริการ
                                                </option>

                                                <option
                                                    value="closed"
                                                    <?= $branch['status'] === 'closed'
                                                        ? 'selected'
                                                        : '' ?>
                                                >
                                                    ปิดให้บริการ
                                                </option>

                                            </select>

                                            <button type="submit">
                                                บันทึก
                                            </button>

                                        </form>

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