<?php

session_start();

require __DIR__ . '/../config/database.php';

$db = databaseConnection();


// =========================
// CHECK SESSION
// =========================

if (
    !isset($_SESSION['order_id']) ||
    !isset($_SESSION['transaction_id'])
) {
    header('Location: machines.php');
    exit;
}

$order_id = (int) $_SESSION['order_id'];
$transaction_id = $_SESSION['transaction_id'];


// =========================
// GET RUNNING ORDER
// =========================

$stmt = $db->prepare("
    SELECT
        o.id,
        o.transaction_id,
        o.machine_id,
        o.mode_id,
        o.duration_minutes,
        o.total_amount,
        o.payment_status,

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

    WHERE o.id = :order_id
      AND o.transaction_id = :transaction_id
");

$stmt->execute([
    ':order_id' => $order_id,
    ':transaction_id' => $transaction_id
]);

$order = $stmt->fetch();


// =========================
// CHECK ORDER
// =========================

if (!$order) {
    header('Location: machines.php');
    exit;
}


// =========================
// CHECK PAYMENT
// =========================

if ($order['payment_status'] !== 'paid') {
    header('Location: payment.php?mode=' . urlencode($order['mode_code']));
    exit;
}


// =========================
// DATA
// =========================

$branch_name = $order['branch_name'];

$machine_name = $order['machine_name'];
$machine_code = $order['machine_code'];
$machine_capacity = (int) $order['capacity_kg'];

$mode_name = $order['mode_name'];

$duration_minutes = (int) $order['duration_minutes'];

$running_until = $order['running_until'];


// =========================
// CALCULATE REMAINING TIME
// =========================

$remaining_seconds = 0;

if ($running_until !== null) {

    $end_timestamp = strtotime($running_until);

    $remaining_seconds = $end_timestamp - time();

    if ($remaining_seconds < 0) {
        $remaining_seconds = 0;
    }
}


// =========================
// FORMAT TIME
// =========================

$initial_minutes = floor($remaining_seconds / 60);

$initial_seconds = $remaining_seconds % 60;

$initial_time = sprintf(
    '%02d:%02d',
    $initial_minutes,
    $initial_seconds
);

?>


<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>กำลังซัก | Laundry Official</title>

    <link
        rel="stylesheet"
        href="../public/css/style.css"
    >

</head>

<body>


    <?php include '../includes/navbar.php'; ?>


    <!-- =========================
         RUNNING
    ========================= -->

    <section class="summary-page">


        <div class="summary-header">

            <p class="section-subtitle">
                LAUNDRY RUNNING
            </p>

            <h1>
                กำลังซัก
            </h1>

            <p>
                เครื่องกำลังทำงาน กรุณารอจนกว่าการซักจะเสร็จ
            </p>

        </div>


        <div class="summary-card">


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

                </strong>

            </div>


            <!-- Capacity -->

            <div class="summary-item">

                <span>
                    ขนาดเครื่อง
                </span>

                <strong>
                    <?php echo $machine_capacity; ?> KG
                </strong>

            </div>


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


            <!-- Countdown -->

            <div
                class="payment-box"
                style="text-align: center;"
            >

                <p class="section-subtitle">
                    TIME REMAINING
                </p>


                <div
                    id="countdown"
                    style="
                        font-size: 64px;
                        font-weight: 700;
                        margin: 20px 0;
                        letter-spacing: 3px;
                    "
                >
                    <?php echo $initial_time; ?>
                </div>


                <p
                    id="machine-status"
                    class="payment-note"
                >
                    ● เครื่องกำลังทำงาน
                </p>


                <p class="payment-note">

                    ระยะเวลาทั้งหมด

                    <strong>
                        <?php echo $duration_minutes; ?> นาที
                    </strong>

                </p>


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

            </div>


        </div>

    </section>


    <?php include '../includes/footer.php'; ?>


    <script src="../public/js/main.js"></script>


    <script>

        // =========================
        // INITIAL DATA
        // =========================

        let remainingSeconds = <?php echo $remaining_seconds; ?>;

        const countdown =
            document.getElementById('countdown');

        const machineStatus =
            document.getElementById('machine-status');


        // =========================
        // FORMAT TIME
        // =========================

        function formatTime(seconds) {

            const minutes =
                Math.floor(seconds / 60);

            const secs =
                seconds % 60;


            return String(minutes).padStart(2, '0')
                + ':'
                + String(secs).padStart(2, '0');

        }


        // =========================
        // UPDATE COUNTDOWN
        // =========================

        function updateCountdown() {

            if (remainingSeconds <= 0) {

                countdown.textContent = '00:00';

                machineStatus.textContent =
                    '✓ ซักเสร็จแล้ว';

                finishMachine();

                return;
            }


            countdown.textContent =
                formatTime(remainingSeconds);


            remainingSeconds--;

        }


        // =========================
        // FINISH MACHINE
        // =========================

        async function finishMachine() {

            try {

                const response = await fetch(
                    '../api/finish-machine.php',
                    {
                        method: 'POST'
                    }
                );


                const result = await response.json();


                if (result.success) {

                    machineStatus.textContent =
                        '✓ ซักเสร็จแล้ว เครื่องพร้อมใช้งาน';

                }

            } catch (error) {

                console.error(
                    'Finish machine error:',
                    error
                );

            }

        }


        // =========================
        // START TIMER
        // =========================

        updateCountdown();


        const timer = setInterval(() => {

            if (remainingSeconds <= 0) {

                clearInterval(timer);

                finishMachine();

                return;
            }


            updateCountdown();

        }, 1000);

    </script>


</body>

</html>