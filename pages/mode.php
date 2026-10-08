<?php

session_start();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/display_helpers.php';

$db = databaseConnection();


// =========================
// CHECK BRANCH
// =========================

if (!isset($_SESSION['branch_id'])) {

    header('Location: branches.php');
    exit;

}

$branch_id = (int) $_SESSION['branch_id'];


// =========================
// CHECK MACHINE
// =========================

if (!isset($_GET['machine_id'])) {

    header('Location: machines.php');
    exit;

}

$machine_id = filter_input(
    INPUT_GET,
    'machine_id',
    FILTER_VALIDATE_INT
);

if ($machine_id === false || $machine_id === null) {

    header('Location: machines.php');
    exit;

}


// =========================
// LOAD MACHINE
// =========================

// ตรวจสอบว่าเครื่องมีอยู่จริง
// อยู่ในสาขาที่เลือก
// และพร้อมใช้งาน

$stmt = $db->prepare("
    SELECT
        m.id,
        m.machine_code,
        m.name,
        m.capacity_kg,
        m.status,
        b.name AS branch_name
    FROM machines m
    INNER JOIN branches b
        ON b.id = m.branch_id
    WHERE m.id = :machine_id
      AND m.branch_id = :branch_id
      AND m.status = 'available'
      AND (m.running_until IS NULL OR m.running_until <= CURRENT_TIMESTAMP)
      AND b.status = 'open'
");

$stmt->execute([
    ':machine_id' => $machine_id,
    ':branch_id' => $branch_id
]);

$machine = $stmt->fetch();


// ถ้าเครื่องไม่ถูกต้องหรือไม่พร้อมใช้งาน
if (!$machine) {

    header('Location: machines.php');
    exit;

}


// เก็บเครื่องที่เลือกไว้ใน Session
$_SESSION['machine_id'] = (int) $machine['id'];


// =========================
// LOAD WASHING MODES + PRICES
// =========================

$stmt = $db->prepare("
    SELECT
        wm.id AS mode_id,
        wm.name,
        wm.code,
        wm.billing_unit,
        mmp.price
    FROM machine_mode_prices mmp
    INNER JOIN wash_modes wm
        ON wm.id = mmp.mode_id
    WHERE mmp.machine_id = :machine_id
    ORDER BY wm.id ASC
");

$stmt->execute([
    ':machine_id' => $machine_id
]);

$modePrices = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>เลือกโหมด | Laundry Official</title>

<link rel="stylesheet" href="../public/css/style.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>


<!-- Washing Modes -->
<section class="modes-page">

    <div class="modes-header">

        <p class="section-subtitle">
            WASHING MODES
        </p>

        <h1>
            เลือกโหมดการซัก
        </h1>

        <p>
            กรุณาเลือกโหมดที่เหมาะกับประเภทผ้าของคุณ
        </p>

    </div>


    <!-- Selection Context -->

    <div class="selection-context">

        <span>สาขา</span>

        <strong>
            <?php echo htmlspecialchars(
                $machine['branch_name'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </strong>


        <span>เครื่อง</span>

        <strong>
            #<?php echo htmlspecialchars(
                $machine['machine_code'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>

            ·

            <?php echo htmlspecialchars(
                $machine['name'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </strong>

    </div>


    <div class="mode-list">


        <?php foreach ($modePrices as $index => $mode): ?>

            <?php

            // คำอธิบายแต่ละโหมด
            $descriptions = [
                'normal' =>
                    'เหมาะสำหรับเสื้อผ้าทั่วไปในชีวิตประจำวัน เช่น เสื้อผ้าฝ้าย หรือกางเกงยีนส์ที่สกปรกปานกลาง',

                'quick' =>
                    'เหมาะสำหรับผ้าปริมาณน้อยและไม่สกปรกมาก เพื่อช่วยประหยัดเวลา',

                'delicate' =>
                    'เหมาะสำหรับผ้าเนื้อบางเบา เช่น ผ้าไหม ผ้าลูกไม้ หรือชุดชั้นใน',

                'bedding' =>
                    'เหมาะสำหรับผ้าปูที่นอน ปลอกหมอน หรือผ้านวมที่มีขนาดใหญ่',

                'wool' =>
                    'ใช้รอบการหมุนและอุณหภูมิต่ำ เพื่อช่วยลดความเสี่ยงที่ผ้าจะหดตัวหรือเสียหาย',

                'rinse_spin' =>
                    'เหมาะสำหรับผ้าที่ซักแล้วและต้องการปั่นแห้ง'
            ];

            $modeDisplayName = laundryModeLabel((string) $mode['code'], (string) $mode['name']);

            $description = $descriptions[$mode['code']]
                ?? 'เลือกโหมดนี้เพื่อใช้งานเครื่องซักผ้า';

            $unit = $mode['code'] === 'rinse_spin'
                ? 'บาท / ครั้ง'
                : ($mode['billing_unit'] === 'per_hour'
                ? 'บาท / ชั่วโมง'
                : 'บาท / ครั้ง');

            ?>

            <div class="mode-card">

                <div class="mode-number">

                    <?php echo str_pad(
                        (string) ($index + 1),
                        2,
                        '0',
                        STR_PAD_LEFT
                    ); ?>

                </div>


                <h2>
                    <?php echo htmlspecialchars($modeDisplayName, ENT_QUOTES, 'UTF-8'); ?>
                </h2>


                <p>
                    <?php echo htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>


                <p>
                    ราคา
                    <?php echo number_format(
                        (float) $mode['price'],
                        2
                    ); ?>

                    <?php echo $unit; ?>
                </p>


                <a
                    href="summary.php?mode=<?php echo urlencode($mode['code']); ?>"
                    class="mode-button"
                >
                    เลือกโหมด
                </a>

            </div>

        <?php endforeach; ?>


    </div>

</section>


<?php include '../includes/footer.php'; ?>


<script src="../public/js/main.js"></script>

</body>

</html>
