<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$db = databaseConnection();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

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
        'available' => 'available',
        'washing' => 'washing',
        'maintenance' => 'maintenance',
        default => '',
    };
}


/*
|--------------------------------------------------------------------------
| Update Machine Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $machineId = filter_input(
        INPUT_POST,
        'machine_id',
        FILTER_VALIDATE_INT
    );

    $newStatus = $_POST['status'] ?? '';

    $allowedStatuses = [
        'available',
        'maintenance',
    ];

    if (!$machineId) {

        $error = 'ไม่พบเครื่องที่ต้องการแก้ไข';

    } elseif (!in_array($newStatus, $allowedStatuses, true)) {

        $error = 'สถานะไม่ถูกต้อง';

    } else {

        try {

            $db->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Get current machine
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                SELECT
                    id,
                    machine_code,
                    name,
                    status
                FROM machines
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$machineId]);

            $machine = $stmt->fetch();

            if (!$machine) {

                throw new RuntimeException(
                    'ไม่พบเครื่องที่ต้องการแก้ไข'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent changing washing machine
            |--------------------------------------------------------------------------
            */

            if ($machine['status'] === 'washing') {

                throw new RuntimeException(
                    'เครื่องนี้กำลังทำงานอยู่ ไม่สามารถเปลี่ยนสถานะได้'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | No change
            |--------------------------------------------------------------------------
            */

            if ($machine['status'] === $newStatus) {

                throw new RuntimeException(
                    'สถานะเครื่องเหมือนเดิมอยู่แล้ว'
                );
            }


            $oldStatus = $machine['status'];


            /*
            |--------------------------------------------------------------------------
            | Update Machine
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare("
                UPDATE machines
                SET
                    status = ?,
                    running_until = NULL
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $machineId,
            ]);


            /*
            |--------------------------------------------------------------------------
            | Create Activity Log
            |--------------------------------------------------------------------------
            */

            $description = sprintf(
                'เปลี่ยนสถานะเครื่อง %s จาก "%s" เป็น "%s"',
                $machine['machine_code'],
                machineStatusLabel($oldStatus),
                machineStatusLabel($newStatus)
            );

            $stmt = $db->prepare("
                INSERT INTO activity_logs (
                    action,
                    description,
                    entity_type,
                    entity_id
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                'machine_status_update',
                $description,
                'machine',
                $machineId,
            ]);


            $db->commit();

            $message = 'อัปเดตสถานะเครื่องและบันทึก Activity Log แล้ว';

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'available') AS available,
        SUM(status = 'washing') AS washing,
        SUM(status = 'maintenance') AS maintenance
    FROM machines
");

$stats = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Get Machines
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        m.id,
        m.machine_code,
        m.name,
        m.capacity_kg,
        m.status,
        m.running_until,
        b.name AS branch_name
    FROM machines m
    INNER JOIN branches b
        ON b.id = m.branch_id
    ORDER BY
        b.id ASC,
        m.capacity_kg ASC,
        m.machine_code ASC
");

$machines = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Machines | Laundry Admin</title>

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

        /* Sidebar */

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
        }

        .nav a:hover,
        .nav a.active {
            background: white;

            color: #111;
        }

        /* Main */

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
        }

        /* Alert */

        .alert {
            padding: 14px 18px;

            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        .success {
            background: #e8f7ed;

            color: #16733a;
        }

        .error {
            background: #fdeaea;

            color: #a52828;
        }

        /* Stats */

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

        /* Section */

        .section {
            background: white;

            border-radius: 16px;

            padding: 24px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.05);

            margin-bottom: 24px;
        }

        .section-title {
            margin: 0 0 20px;

            font-size: 20px;

            font-weight: 750;
        }

        /* Table */

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

            color: #777;

            font-size: 13px;

            padding: 12px;

            border-bottom: 1px solid #eee;

            white-space: nowrap;
        }

        td {
            padding: 15px 12px;

            border-bottom: 1px solid #eee;

            font-size: 14px;
        }

        /* Status */

        .status {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 600;
        }

        .status.available {
            background: #e8f7ed;

            color: #16733a;
        }

        .status.washing {
            background: #fff5d9;

            color: #946d00;
        }

        .status.maintenance {
            background: #fdeaea;

            color: #a52828;
        }

        /* Form */

        .status-form {
            display: flex;

            gap: 8px;
        }

        select {
            border: 1px solid #ddd;

            border-radius: 8px;

            padding: 8px 10px;

            background: white;
        }

        button {
            border: none;

            background: #111;

            color: white;

            border-radius: 8px;

            padding: 8px 12px;

            cursor: pointer;
        }

        button:hover {
            opacity: 0.85;
        }

        .running-time {
            color: #946d00;

            font-size: 13px;
        }

        @media (max-width: 900px) {

            .sidebar {
                position: static;

                width: 100%;
            }

            .main {
                margin-left: 0;

                padding: 20px;
            }

            .stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

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

        <a
            href="machines.php"
            class="active"
        >
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


<main class="main">

    <div class="topbar">

        <div>

            <h1 class="page-title">
                Machines
            </h1>

            <div class="page-subtitle">
                จัดการสถานะเครื่องซักผ้า
            </div>

        </div>

        <div class="admin-badge">
            Admin
        </div>

    </div>


    <?php if ($message !== ''): ?>

        <div class="alert success">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>


    <!-- Statistics -->

    <section class="stats">

        <div class="stat-card">

            <div class="stat-title">
                เครื่องทั้งหมด
            </div>

            <div class="stat-value">
                <?= (int) $stats['total'] ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                พร้อมใช้งาน
            </div>

            <div class="stat-value">
                <?= (int) $stats['available'] ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                กำลังทำงาน
            </div>

            <div class="stat-value">
                <?= (int) $stats['washing'] ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                ซ่อมบำรุง
            </div>

            <div class="stat-value">
                <?= (int) $stats['maintenance'] ?>
            </div>

        </div>

    </section>


    <!-- Machines -->

    <section class="section">

        <h2 class="section-title">
            รายการเครื่องทั้งหมด
        </h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            รหัสเครื่อง
                        </th>

                        <th>
                            สาขา
                        </th>

                        <th>
                            ชื่อเครื่อง
                        </th>

                        <th>
                            ความจุ
                        </th>

                        <th>
                            สถานะ
                        </th>

                        <th>
                            ทำงานถึง
                        </th>

                        <th>
                            จัดการ
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($machines as $machine): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $machine['machine_code'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $machine['branch_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $machine['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= (int) $machine['capacity_kg'] ?>
                            kg
                        </td>

                        <td>

                            <span
                                class="status <?= machineStatusClass(
                                    $machine['status']
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    machineStatusLabel(
                                        $machine['status']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <?php if (
                                $machine['running_until'] !== null
                            ): ?>

                                <span class="running-time">

                                    <?= htmlspecialchars(
                                        $machine['running_until'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if (
                                $machine['status'] === 'washing'
                            ): ?>

                                <span class="status washing">
                                    กำลังทำงาน
                                </span>

                            <?php else: ?>

                                <form
                                    method="post"
                                    class="status-form"
                                >

                                    <input
                                        type="hidden"
                                        name="machine_id"
                                        value="<?= (int) $machine['id'] ?>"
                                    >

                                    <select name="status">

                                        <option
                                            value="available"
                                            <?= $machine['status'] === 'available'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            พร้อมใช้งาน
                                        </option>

                                        <option
                                            value="maintenance"
                                            <?= $machine['status'] === 'maintenance'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            ซ่อมบำรุง
                                        </option>

                                    </select>

                                    <button type="submit">
                                        บันทึก
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>

</html>