<?php

session_start();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/display_helpers.php';

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
// LOAD ORDER DATA FROM DATABASE
// =========================

$stmt = $db->prepare("
    SELECT
        b.id AS branch_id,
        b.name AS branch_name,

        m.id AS machine_id,
        m.machine_code,
        m.name AS machine_name,
        m.capacity_kg,
        m.status AS machine_status,

        wm.id AS mode_id,
        wm.name AS mode_name,
        wm.code AS mode_code,
        wm.billing_unit,

        mmp.price

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
      AND b.status = 'open'
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
// CHECK MACHINE STATUS
// =========================

if ($orderData['machine_status'] !== 'available') {
    header('Location: machines.php');
    exit;
}


// =========================
// PREPARE DISPLAY DATA
// =========================

$branch_name = $orderData['branch_name'];

$machine_name = $orderData['machine_name'];
$machine_code = $orderData['machine_code'];
$machine_capacity = (int) $orderData['capacity_kg'];

$mode_name = $orderData['mode_name'];
$mode_code = $orderData['mode_code'];
$machine_display = laundryMachineLabel($machine_capacity, $machine_code);
$mode_display = laundryModeLabel($mode_code, $mode_name);

$total_price = (float) $orderData['price'];

$price_unit = $mode_code === 'rinse_spin'
    ? 'บาท / ครั้ง'
    : ($orderData['billing_unit'] === 'per_hour'
        ? 'บาท / ชั่วโมง'
        : 'บาท / ครั้ง');


// เก็บข้อมูลสำคัญไว้ใน Session
$_SESSION['mode_id'] = (int) $orderData['mode_id'];
$_SESSION['mode_code'] = $mode_code;
$_SESSION['price'] = $total_price;

?>

<!DOCTYPE html>

<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>สรุปรายการ | Laundry Official</title>

<link rel="stylesheet" href="../public/css/style.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>


<!-- =========================
     SUMMARY
========================= -->

<section class="summary-page">


    <div class="summary-header">

        <p class="section-subtitle">
            ORDER SUMMARY
        </p>

        <h1>
            สรุปรายการ
        </h1>

        <p>
            ตรวจสอบข้อมูลก่อนดำเนินการชำระเงิน
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

                <?php echo htmlspecialchars($machine_display, ENT_QUOTES, 'UTF-8'); ?>

            </strong>

        </div>


        <!-- Mode -->

        <div class="summary-item">

            <span>
                โหมดการซัก
            </span>

            <strong>
                <?php echo htmlspecialchars($mode_display, ENT_QUOTES, 'UTF-8'); ?>
            </strong>

        </div>


        <!-- Price -->

        <div class="summary-item">

            <span>
                ราคา
            </span>

            <strong>

                <?php echo number_format($total_price, 2); ?> <?php echo $price_unit; ?>

            </strong>

        </div>


        <!-- Total -->

        <div class="summary-total">

            <span>
                ยอดชำระ
            </span>

            <strong>

                ฿<?php echo number_format($total_price, 2); ?>

            </strong>

        </div>


        <!-- Payment -->

        <a
            href="payment.php?mode=<?php echo urlencode($mode_code); ?>"
            class="summary-button"
        >
            ดำเนินการชำระเงิน
        </a>


    </div>

</section>


<?php include '../includes/footer.php'; ?>


<script src="../public/js/main.js"></script>

</body>

</html>
