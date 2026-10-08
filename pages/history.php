<?php

session_start();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/display_helpers.php';

$db = databaseConnection();


// =========================
// CHECK ORDER HISTORY
// =========================

// ระบบยังไม่มี Customer Login
// ดังนั้นใช้ order_history ใน Session
// เพื่อให้ลูกค้าเห็นเฉพาะรายการของ Session นี้

$orderHistory = $_SESSION['order_history'] ?? [];


// =========================
// CLEAN ORDER HISTORY
// =========================

// ตรวจสอบให้เป็น Array
if (!is_array($orderHistory)) {
    $orderHistory = [];
}


// แปลงค่าเป็น Integer
// และเอา ID ที่ไม่ถูกต้องออก

$orderHistory = array_map(
    'intval',
    $orderHistory
);

$orderHistory = array_filter(
    $orderHistory,
    function ($id) {
        return $id > 0;
    }
);


// ลบ Order ID ซ้ำ

$orderHistory = array_values(
    array_unique($orderHistory)
);


$orders = [];


// =========================
// LOAD ORDER HISTORY
// =========================

if (!empty($orderHistory)) {

    // สร้าง ?, ?, ? ตามจำนวน Order ID

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($orderHistory),
            '?'
        )
    );


    $stmt = $db->prepare("
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
            m.capacity_kg,
            m.status AS machine_status,
            m.running_until,

            wm.name AS mode_name,
            wm.code AS mode_code

        FROM orders o

        INNER JOIN branches b
            ON b.id = o.branch_id

        INNER JOIN machines m
            ON m.id = o.machine_id

        INNER JOIN wash_modes wm
            ON wm.id = o.mode_id

        WHERE o.id IN ($placeholders)

        ORDER BY o.created_at DESC
    ");


    $stmt->execute($orderHistory);

    $orders = $stmt->fetchAll();
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

    <title>
        ประวัติการทำรายการ | Laundry Official
    </title>

    <link
        rel="stylesheet"
        href="../public/css/style.css"
    >

</head>


<body>


    <?php include '../includes/navbar.php'; ?>


    <!-- =========================
         HISTORY PAGE
    ========================= -->

    <section class="machines-page">


        <div class="machines-header">

            <p class="section-subtitle">
                TRANSACTION HISTORY
            </p>

            <h1>
                ประวัติการทำรายการ
            </h1>

            <p>
                รายการซักของคุณ
            </p>

        </div>


        <?php if (empty($orders)): ?>


            <!-- =========================
                 NO HISTORY
            ========================= -->

            <div class="summary-card">

                <div class="summary-header">

                    <p class="section-subtitle">
                        NO TRANSACTIONS
                    </p>

                    <h2>
                        ยังไม่มีรายการทำรายการ
                    </h2>

                    <p>
                        เลือกเครื่องซักผ้าเพื่อเริ่มใช้งาน
                    </p>

                </div>


                <a
                    href="branches.php"
                    class="summary-button"
                >
                    เริ่มใช้งาน
                </a>

            </div>


        <?php else: ?>


            <!-- =========================
                 HISTORY LIST
            ========================= -->

            <div class="machine-list">


                <?php foreach ($orders as $order): ?>


                    <?php

                    // =========================
                    // CHECK RUNNING
                    // =========================

                    $isRunning =
                        $order['payment_status'] === 'paid'
                        && $order['machine_status'] === 'washing'
                        && $order['running_until'] !== null
                        && strtotime($order['running_until']) > time();


                    // =========================
                    // CHECK FINISHED
                    // =========================

                    $isFinished =
                        $order['payment_status'] === 'paid'
                        && !$isRunning;

                    $machineDisplay = laundryMachineLabel(
                        (int) $order['capacity_kg'],
                        (string) $order['machine_code']
                    );
                    $modeDisplay = laundryModeLabel(
                        (string) $order['mode_code'],
                        (string) $order['mode_name']
                    );

                    ?>


                    <div class="machine-card">


                        <!-- =========================
                             TRANSACTION
                        ========================= -->

                        <div class="machine-number">

                            #

                            <?php echo htmlspecialchars(
                                $order['transaction_id'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>

                        </div>


                        <!-- =========================
                             MACHINE
                        ========================= -->

                        <h2>

                            <?php echo htmlspecialchars($machineDisplay, ENT_QUOTES, 'UTF-8'); ?>

                        </h2>


                        <!-- =========================
                             BRANCH
                        ========================= -->

                        <p>

                            สาขา

                            <strong>

                                <?php echo htmlspecialchars(
                                    $order['branch_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </strong>

                        </p>


                        <!-- =========================
                             MODE
                        ========================= -->

                        <p>

                            โหมด

                            <strong>

                                <?php echo htmlspecialchars($modeDisplay, ENT_QUOTES, 'UTF-8'); ?>

                            </strong>

                        </p>


                        <!-- =========================
                             DURATION
                        ========================= -->

                        <p>

                            ระยะเวลา

                            <strong>

                                <?php echo (int) $order['duration_minutes']; ?>

                                นาที

                            </strong>

                        </p>


                        <!-- =========================
                             PRICE
                        ========================= -->

                        <p>

                            ราคา

                            <strong>

                                <?php echo number_format(
                                    (float) $order['total_amount'],
                                    2
                                ); ?>

                                บาท

                            </strong>

                        </p>


                        <!-- =========================
                             STATUS
                        ========================= -->

                        <?php if ($isRunning): ?>


                            <span class="machine-status available">

                                ● กำลังซัก

                            </span>


                            <a
                                href="running.php"
                                class="machine-button"
                            >
                                ดูสถานะเครื่อง
                            </a>


                        <?php elseif ($isFinished): ?>


                            <span class="machine-status available">

                                ✓ ซักเสร็จแล้ว

                            </span>


                        <?php elseif ($order['payment_status'] === 'pending'): ?>


                            <span class="machine-status unavailable">

                                ● รอชำระเงิน

                            </span>


                            <a
                                href="payment.php?mode=<?php echo urlencode($order['mode_code']); ?>"
                                class="machine-button"
                            >
                                ดำเนินการชำระเงิน
                            </a>


                        <?php elseif ($order['payment_status'] === 'cancelled'): ?>


                            <span class="machine-status unavailable">

                                ● ยกเลิกรายการ

                            </span>


                        <?php elseif ($order['payment_status'] === 'failed'): ?>


                            <span class="machine-status unavailable">

                                ● ชำระเงินไม่สำเร็จ

                            </span>


                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </section>


    <?php include '../includes/footer.php'; ?>


    <script src="../public/js/main.js"></script>


</body>

</html>
