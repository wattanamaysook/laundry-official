<?php

session_start();

require __DIR__ . '/../config/database.php';


// =========================
// LOAD BRANCHES FROM DATABASE
// =========================

$db = databaseConnection();

$stmt = $db->query("
    SELECT id, name, address, status
    FROM branches
    ORDER BY id ASC
");

$branches = $stmt->fetchAll();


// =========================
// CHECK BRANCH
// =========================

if (isset($_GET['branch_id'])) {

    $branch_id = filter_input(
        INPUT_GET,
        'branch_id',
        FILTER_VALIDATE_INT
    );

    // ตรวจสอบว่าสาขามีอยู่จริงและเปิดให้บริการ
    $stmt = $db->prepare("
        SELECT id
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

    $_SESSION['branch_id'] = $branch_id;
}

?>

<!DOCTYPE html>

<html lang="th">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>เลือกสาขา | Laundry Official</title>

<link rel="stylesheet" href="../public/css/style.css">

</head>

<body>

<?php include '../includes/navbar.php'; ?>


<!-- =========================
     BRANCHES
========================= -->

<section class="branches-page">


    <div class="branches-header">

        <p class="section-subtitle">
            OUR BRANCHES
        </p>

        <h1>
            เลือกสาขา
        </h1>

        <p>
            กรุณาเลือกสาขาที่ต้องการใช้บริการ
        </p>

    </div>


    <div class="branch-list">


        <?php foreach ($branches as $branch): ?>

            <div class="branch-card">

                <h2>
                    Laundry Official
                </h2>

                <p>
                    <?php echo htmlspecialchars(
                        $branch['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>

                <?php if ($branch['address'] !== ''): ?>

                    <p>
                        📍
                        <?php echo htmlspecialchars(
                            $branch['address'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>

                <?php endif; ?>


                <?php if ($branch['status'] === 'open'): ?>

                    <a
                        href="machines.php?branch_id=<?php echo (int) $branch['id']; ?>"
                        class="branch-button"
                    >
                        เลือกสาขา
                    </a>

                <?php else: ?>

                    <p>
                        ปิดให้บริการ
                    </p>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>


    </div>

</section>


<?php include '../includes/footer.php'; ?>


<script src="../public/js/main.js"></script>

</body>

</html>
