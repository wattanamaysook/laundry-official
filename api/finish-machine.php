<?php

session_start();

require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$db = databaseConnection();


// =========================
// CHECK SESSION
// =========================

if (!isset($_SESSION['order_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบ Order'
    ]);

    exit;
}

$order_id = (int) $_SESSION['order_id'];


// =========================
// GET ORDER
// =========================

$stmt = $db->prepare("
    SELECT
        o.id,
        o.machine_id,
        o.transaction_id,
        o.payment_status,

        m.machine_code

    FROM orders o

    INNER JOIN machines m
        ON m.id = o.machine_id

    WHERE o.id = :order_id
");

$stmt->execute([
    ':order_id' => $order_id
]);

$order = $stmt->fetch();


// =========================
// CHECK ORDER
// =========================

if (!$order) {

    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบรายการ'
    ]);

    exit;
}


// =========================
// CHECK PAYMENT
// =========================

if ($order['payment_status'] !== 'paid') {

    echo json_encode([
        'success' => false,
        'message' => 'รายการยังไม่ได้ชำระเงิน'
    ]);

    exit;
}


// =========================
// CHECK MACHINE
// =========================

$stmt = $db->prepare("
    SELECT
        id,
        status,
        running_until,
        TIMESTAMPDIFF(SECOND, CURRENT_TIMESTAMP, running_until) AS seconds_remaining
    FROM machines
    WHERE id = :machine_id
");

$stmt->execute([
    ':machine_id' => $order['machine_id']
]);

$machine = $stmt->fetch();


if (!$machine) {

    echo json_encode([
        'success' => false,
        'message' => 'ไม่พบเครื่อง'
    ]);

    exit;
}


// =========================
// CHECK RUNNING TIME
// =========================

if ($machine['running_until'] !== null) {

    if ((int) $machine['seconds_remaining'] > 0) {

        echo json_encode([
            'success' => false,
            'message' => 'เครื่องยังทำงานอยู่'
        ]);

        exit;
    }
}


// =========================
// FINISH MACHINE
// =========================

$stmt = $db->prepare("
    UPDATE machines

    SET
        status = 'available',
        running_until = NULL

    WHERE id = :machine_id
      AND status = 'washing'
      AND (running_until IS NULL OR running_until <= CURRENT_TIMESTAMP)
");

$stmt->execute([
    ':machine_id' => $order['machine_id']
]);


// =========================
// CHECK UPDATE
// =========================

if ($stmt->rowCount() === 1) {


    // =========================
    // ACTIVITY LOG
    // =========================

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
        ':action' => 'machine_finished',

        ':description' => sprintf(
            'เครื่อง %s ทำงานเสร็จแล้ว สำหรับ Order %s',
            $order['machine_code'],
            $order['transaction_id']
        ),

        ':entity_type' => 'machine',

        ':entity_id' => (int) $order['machine_id']
    ]);


    // =========================
    // SUCCESS
    // =========================

    echo json_encode([
        'success' => true,
        'message' => 'เครื่องซักเสร็จแล้ว'
    ]);

    exit;
}


// =========================
// ALREADY FINISHED
// =========================

echo json_encode([
    'success' => true,
    'message' => 'เครื่องทำงานเสร็จแล้ว'
]);

exit;
