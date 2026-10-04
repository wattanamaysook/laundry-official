<?php

session_start();

require __DIR__ . '/../config/database.php';

$db = databaseConnection();


// =========================
// CHECK SESSION
// =========================

if (
    !isset($_SESSION['branch_id']) ||
    !isset($_SESSION['machine_id']) ||
    !isset($_GET['mode'])
) {
    header('Location: machines.php');
    exit;
}

$branch_id = (int) $_SESSION['branch_id'];
$machine_id = (int) $_SESSION['machine_id'];
$mode_code = $_GET['mode'];


// =========================
// GET ORDER DATA FROM DATABASE
// =========================

$stmt = $db->prepare("
    SELECT
        b.id AS branch_id,
        b.name AS branch_name,
        b.status AS branch_status,

        m.id AS machine_id,
        m.machine_code,
        m.name AS machine_name,
        m.capacity_kg,
        m.status AS machine_status,

        wm.id AS mode_id,
        wm.name AS mode_name,
        wm.code AS mode_code,
        wm.billing_unit,

        mmp.price,
        mmp.billing_unit AS price_billing_unit,
        mmp.duration_minutes

    FROM machines m

    INNER JOIN branches b
        ON b.id = m.branch_id

    INNER JOIN machine_mode_prices mmp
        ON mmp.machine_id = m.id

    INNER JOIN wash_modes wm
        ON wm.id = mmp.mode_id

    WHERE b.id = :branch_id
      AND m.id = :machine_id
      AND wm.code = :mode_code
");

$stmt->execute([
    ':branch_id' => $branch_id,
    ':machine_id' => $machine_id,
    ':mode_code' => $mode_code
]);

$orderData = $stmt->fetch();


// =========================
// CHECK DATA
// =========================

if (!$orderData) {
    header('Location: machines.php');
    exit;
}


// =========================
// CHECK BRANCH
// =========================

if ($orderData['branch_status'] !== 'open') {
    header('Location: branches.php');
    exit;
}


// =========================
// CHECK MACHINE
// =========================

if ($orderData['machine_status'] !== 'available') {
    header('Location: machines.php');
    exit;
}


// =========================
// DATA
// =========================

$branch_name = $orderData['branch_name'];

$machine_name = $orderData['machine_name'];
$machine_code = $orderData['machine_code'];
$machine_capacity = (int) $orderData['capacity_kg'];

$mode_id = (int) $orderData['mode_id'];
$mode_name = $orderData['mode_name'];
$mode_code = $orderData['mode_code'];

$total_price = (float) $orderData['price'];


// ใช้ billing_unit จาก machine_mode_prices
// เพราะเป็นค่าที่กำหนดจริงต่อเครื่อง + โหมด

$billing_unit = $orderData['price_billing_unit'];

$duration_minutes = (int) $orderData['duration_minutes'];


// =========================
// PRICE UNIT
// =========================

$price_unit = $billing_unit === 'per_hour'
    ? 'บาท / ชั่วโมง'
    : 'บาท / ครั้ง';


// =========================
// CHECK DURATION
// =========================

// ระบบปัจจุบันรองรับการคิดราคาแบบต่อรอบ
// และต้องมีระยะเวลาทำงานมากกว่า 0

if ($billing_unit === 'per_cycle' && $duration_minutes <= 0) {
    die('ไม่พบระยะเวลาการทำงานของโหมดนี้');
}


// =========================
// CREATE / REUSE ORDER
// =========================

$order_id = $_SESSION['order_id'] ?? null;
$transaction_id = $_SESSION['transaction_id'] ?? null;

$useExistingOrder = false;


// =========================
// CHECK EXISTING ORDER
// =========================

if ($order_id !== null) {

    $stmt = $db->prepare("
        SELECT
            id,
            transaction_id,
            branch_id,
            machine_id,
            mode_id,
            price,
            duration_minutes,
            total_amount,
            payment_status
        FROM orders
        WHERE id = :order_id
    ");

    $stmt->execute([
        ':order_id' => (int) $order_id
    ]);

    $existingOrder = $stmt->fetch();


    if (
        $existingOrder &&
        (int) $existingOrder['branch_id'] === $branch_id &&
        (int) $existingOrder['machine_id'] === $machine_id &&
        (int) $existingOrder['mode_id'] === $mode_id &&
        $existingOrder['payment_status'] === 'pending'
    ) {

        $useExistingOrder = true;

        $order_id = (int) $existingOrder['id'];

        $transaction_id = $existingOrder['transaction_id'];


        // =========================
        // UPDATE OLD ORDER
        // =========================

        if (
            $billing_unit === 'per_cycle' &&
            (
                (int) $existingOrder['duration_minutes'] <= 0 ||
                (float) $existingOrder['price'] !== $total_price
            )
        ) {

            $stmt = $db->prepare("
                UPDATE orders
                SET
                    price = :price,
                    duration_minutes = :duration_minutes,
                    total_amount = :total_amount
                WHERE id = :order_id
            ");

            $stmt->execute([
                ':price' => $total_price,
                ':duration_minutes' => $duration_minutes,
                ':total_amount' => $total_price,
                ':order_id' => $order_id
            ]);
        }
    }
}


// =========================
// CREATE NEW PENDING ORDER
// =========================

if (!$useExistingOrder) {

    // =========================
    // CREATE UNIQUE TRANSACTION ID
    // =========================

    do {

        $transaction_id =
            'LW-' .
            date('Ymd-His') .
            '-' .
            strtoupper(bin2hex(random_bytes(3)));

        $stmt = $db->prepare("
            SELECT id
            FROM orders
            WHERE transaction_id = :transaction_id
        ");

        $stmt->execute([
            ':transaction_id' => $transaction_id
        ]);

        $transactionExists = $stmt->fetch();

    } while ($transactionExists);


    // =========================
    // INSERT ORDER
    // =========================

    $stmt = $db->prepare("
        INSERT INTO orders (
            transaction_id,
            branch_id,
            machine_id,
            mode_id,
            price,
            duration_minutes,
            total_amount,
            payment_status
        )
        VALUES (
            :transaction_id,
            :branch_id,
            :machine_id,
            :mode_id,
            :price,
            :duration_minutes,
            :total_amount,
            'pending'
        )
    ");

    $stmt->execute([
        ':transaction_id' => $transaction_id,
        ':branch_id' => $branch_id,
        ':machine_id' => $machine_id,
        ':mode_id' => $mode_id,
        ':price' => $total_price,
        ':duration_minutes' => $duration_minutes,
        ':total_amount' => $total_price
    ]);

    $order_id = (int) $db->lastInsertId();


    // =========================
    // ACTIVITY LOG
    // =========================

    $description = sprintf(
        'สร้าง Order %s จำนวนเงิน %.2f บาท',
        $transaction_id,
        $total_price
    );

    $logStmt = $db->prepare("
        INSERT INTO activity_logs (
            action,
            description,
            entity_type,
            entity_id
        )
        VALUES (
            :action,
            :description,
            :entity_type,
            :entity_id
        )
    ");

    $logStmt->execute([
        ':action' => 'order_created',
        ':description' => $description,
        ':entity_type' => 'order',
        ':entity_id' => $order_id
    ]);
}


// =========================
// STORE CURRENT ORDER
// =========================

$_SESSION['order_id'] = $order_id;
$_SESSION['transaction_id'] = $transaction_id;


// =========================
// STORE ORDER HISTORY
// =========================

// ถ้ายังไม่มี order_history ให้สร้าง Array

if (
    !isset($_SESSION['order_history']) ||
    !is_array($_SESSION['order_history'])
) {
    $_SESSION['order_history'] = [];
}


// เพิ่ม Order ID เข้า History
// แต่ป้องกันไม่ให้ Order เดิมถูกเพิ่มซ้ำ

if (
    $order_id !== null &&
    !in_array(
        (int) $order_id,
        $_SESSION['order_history'],
        true
    )
) {
    $_SESSION['order_history'][] = (int) $order_id;
}


// =========================
// STORE ORDER DATA IN SESSION
// =========================

$_SESSION['mode_id'] = $mode_id;
$_SESSION['mode_code'] = $mode_code;
$_SESSION['price'] = $total_price;
$_SESSION['duration_minutes'] = $duration_minutes;

?>


<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>ชำระเงิน | Laundry Official</title>

    <link
        rel="stylesheet"
        href="../public/css/style.css"
    >

</head>

<body>


    <?php include '../includes/navbar.php'; ?>


    <!-- =========================
         PAYMENT
    ========================= -->

    <section class="summary-page">


        <div class="summary-header">

            <p class="section-subtitle">
                PAYMENT
            </p>

            <h1>
                ชำระเงิน
            </h1>

            <p>
                ตรวจสอบข้อมูลก่อนชำระเงิน
            </p>

        </div>


        <div class="summary-card">


            <!-- Branch -->

            <div class="summary-item">

                <span>
                    สาขา
                </span>

                <strong>
                    <?php echo htmlspecialchars(
                        $branch_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>

            </div>


            <!-- Machine -->

            <div class="summary-item">

                <span>
                    เครื่อง
                </span>

                <strong>

                    <?php echo htmlspecialchars(
                        $machine_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                    #

                    <?php echo htmlspecialchars(
                        $machine_code,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                    (<?php echo $machine_capacity; ?> KG)

                </strong>

            </div>


            <!-- Mode -->

            <div class="summary-item">

                <span>
                    โหมดการซัก
                </span>

                <strong>

                    <?php echo htmlspecialchars(
                        $mode_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </strong>

            </div>


            <!-- Duration -->

            <div class="summary-item">

                <span>
                    ระยะเวลาทำงาน
                </span>

                <strong>

                    <?php if ($billing_unit === 'per_cycle'): ?>

                        <?php echo $duration_minutes; ?> นาที

                    <?php else: ?>

                        คิดตามชั่วโมง

                    <?php endif; ?>

                </strong>

            </div>


            <!-- Total -->

            <div class="summary-total">

                <span>
                    ยอดชำระ
                </span>

                <strong>

                    <?php echo number_format(
                        $total_price,
                        2
                    ); ?>

                    <?php echo $price_unit; ?>

                </strong>

            </div>


            <!-- Payment Method -->

            <div class="summary-item">

                <span>
                    วิธีชำระเงิน
                </span>

                <strong>
                    PromptPay
                </strong>

            </div>


            <!-- Payment Box -->

            <div class="payment-box">


                <div class="payment-qr">

                    <div class="qr-placeholder">
                        QR
                    </div>

                </div>


                <p class="payment-note">
                    QR Payment จำลอง
                </p>


                <p class="payment-amount">

                    ยอดชำระ

                    <?php echo number_format(
                        $total_price,
                        2
                    ); ?>

                    <?php echo $price_unit; ?>

                </p>


                <!-- Duration -->

                <?php if ($billing_unit === 'per_cycle'): ?>

                    <p class="payment-note">

                        เครื่องจะทำงานประมาณ

                        <strong>
                            <?php echo $duration_minutes; ?> นาที
                        </strong>

                    </p>

                <?php endif; ?>


                <!-- Transaction ID -->

                <p class="payment-note">

                    Transaction ID:

                    <strong>
                        <?php echo htmlspecialchars(
                            $transaction_id,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </strong>

                </p>


                <!-- Payment Button -->

                <a
                    href="success.php?mode=<?php echo urlencode($mode_code); ?>"
                    class="summary-button"
                >
                    จำลองการชำระเงินสำเร็จ
                </a>


            </div>


        </div>

    </section>


    <?php include '../includes/footer.php'; ?>


    <script src="../public/js/main.js"></script>


</body>

</html>
