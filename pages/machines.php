<?php

session_start();

require __DIR__ . '/../config/database.php';


// =========================
// DATABASE
// =========================

$db = databaseConnection();


// =========================
// CHECK BRANCH
// =========================

if (isset($_GET['branch_id'])) {

    $branch_id = filter_input(
        INPUT_GET,
        'branch_id',
        FILTER_VALIDATE_INT
    );

    if ($branch_id === false || $branch_id === null) {
        header('Location: branches.php');
        exit;
    }

    // ตรวจสอบว่าสาขามีอยู่จริงและเปิดให้บริการ
    $stmt = $db->prepare("
        SELECT id, name
        FROM branches
        WHERE id = :id
        AND status = 'open'
    ");

    $stmt->execute([
        ':id' => $branch_id
    ]);

    $branch = $stmt->fetch();

    if (!$branch) {
        header('Location: branches.php');
        exit;
    }

    // เก็บสาขาที่เลือกไว้ใน Session
    $_SESSION['branch_id'] = (int) $branch_id;
}


// =========================
// CHECK SESSION BRANCH
// =========================

if (!isset($_SESSION['branch_id'])) {

    header('Location: branches.php');
    exit;
}

$branch_id = (int) $_SESSION['branch_id'];


// =========================
// AUTO FINISH EXPIRED MACHINES
// =========================

// ตรวจสอบเครื่องที่กำลังซัก
// แต่เวลาทำงานหมดแล้ว
//
// ถ้าหมดเวลาแล้ว
// เปลี่ยนสถานะกลับเป็น available
// และล้าง running_until

$db->exec("
    UPDATE machines
    SET
        status = 'available',
        running_until = NULL
    WHERE status = 'washing'
      AND running_until IS NOT NULL
      AND running_until <= CURRENT_TIMESTAMP
");


// =========================
// LOAD MACHINES
// =========================

$stmt = $db->prepare("
    SELECT
        id,
        machine_code,
        name,
        capacity_kg,
        status,
        running_until,
        CASE
            WHEN running_until > CURRENT_TIMESTAMP THEN 'washing'
            ELSE status
        END AS display_status,
        GREATEST(0, TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, running_until)) AS remaining_seconds
    FROM machines
    WHERE branch_id = :branch_id
    ORDER BY capacity_kg ASC, machine_code ASC
");

$stmt->execute([
    ':branch_id' => $branch_id
]);

$machines = $stmt->fetchAll();


// =========================
// GET BRANCH NAME
// =========================

$stmt = $db->prepare("
    SELECT name
    FROM branches
    WHERE id = :branch_id
");

$stmt->execute([
    ':branch_id' => $branch_id
]);

$branch = $stmt->fetch();

if (!$branch) {
    header('Location: branches.php');
    exit;
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
        เลือกเครื่อง | Laundry Official
    </title>

    <link
        rel="stylesheet"
        href="../public/css/style.css"
    >

</head>

<body>


    <?php include '../includes/navbar.php'; ?>


    <section class="machines-page">
        <div class="machines-header">
            <p class="section-subtitle">WASHING MACHINES</p>
            <h1>เลือกเครื่องซักผ้า</h1>
            <p><?php echo htmlspecialchars($branch['name'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="machine-list">
            <?php foreach ($machines as $machine): ?>
                <?php
                $displayStatus = $machine['display_status'];
                $isAvailable = $displayStatus === 'available';
                $remainingSeconds = (int) $machine['remaining_seconds'];
                ?>
                <div class="machine-card">
                    <div class="machine-number">#<?php echo htmlspecialchars($machine['machine_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <h2><?php echo htmlspecialchars($machine['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p>ขนาด <?php echo (int) $machine['capacity_kg']; ?> kg</p>

                    <span class="machine-status <?php echo $isAvailable ? 'available' : 'unavailable'; ?>">
                        ● <?php
                        if ($displayStatus === 'available') {
                            echo 'พร้อมใช้งาน';
                        } elseif ($displayStatus === 'washing') {
                            echo 'กำลังใช้งาน';
                        } else {
                            echo 'ปิดปรับปรุง';
                        }
                        ?>
                    </span>

                    <?php if ($displayStatus === 'washing' && $remainingSeconds > 0): ?>
                        <p class="machine-remaining" data-remaining-seconds="<?php echo $remainingSeconds; ?>">
                            เหลือเวลา <span class="remaining-time"><?php echo sprintf('%02d:%02d', floor($remainingSeconds / 60), $remainingSeconds % 60); ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if ($isAvailable): ?>
                        <a href="mode.php?machine_id=<?php echo (int) $machine['id']; ?>" class="machine-button">เลือกเครื่อง</a>
                    <?php else: ?>
                        <button class="machine-button" disabled>
                            <?php echo $displayStatus === 'washing' ? 'กำลังใช้งาน' : 'ไม่สามารถเลือกได้'; ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>


    <?php include '../includes/footer.php'; ?>


    <script>
        document.querySelectorAll('[data-remaining-seconds]').forEach((timer) => {
            let remaining = Number(timer.dataset.remainingSeconds);
            const output = timer.querySelector('.remaining-time');

            const tick = () => {
                if (remaining <= 0) {
                    window.location.reload();
                    return;
                }

                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                output.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                remaining -= 1;
            };

            tick();
            window.setInterval(tick, 1000);
        });

    </script>

    <script src="../public/js/main.js"></script>


</body>

</html>
