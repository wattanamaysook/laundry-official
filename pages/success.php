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
// GET ORDER
// =========================

$stmt = $db->prepare("
    SELECT
        o.id,
        o.transaction_id,
        o.branch_id,
        o.machine_id,
        o.mode_id,
        o.price,
        o.duration_minutes,
        o.total_amount,
        o.payment_status,
        o.created_at,
        o.paid_at,

        b.name AS branch_name,
        b.status AS branch_status,

        m.machine_code,
        m.name AS machine_name,
        m.capacity_kg,
        m.status AS machine_status,
        m.running_until,

        wm.name AS mode_name,
        wm.code AS mode_code,
        wm.billing_unit

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
// PAYMENT + START MACHINE
// =========================

if ($order['payment_status'] === 'pending') {

    $duration_minutes = (int) $order['duration_minutes'];


    // ตอนนี้รองรับโหมดแบบ per_cycle ก่อน
    // Spin Dry จะทำระบบเลือกเวลาแยกภายหลัง

    if (
        $order['billing_unit'] === 'per_cycle' &&
        $duration_minutes <= 0
    ) {
        die('ไม่พบระยะเวลาการทำงานของรายการนี้');
    }


    try {

        // เริ่ม Transaction
        $db->beginTransaction();


        // =========================
        // CHECK MACHINE
        // =========================

        $stmt = $db->prepare("
            SELECT
                id,
                status,
                running_until,
                running_until > CURRENT_TIMESTAMP AS is_running
            FROM machines
            WHERE id = :machine_id
            FOR UPDATE
        ");

        $stmt->execute([
            ':machine_id' => $order['machine_id']
        ]);

        $machine = $stmt->fetch();


        if (!$machine) {

            throw new RuntimeException(
                'ไม่พบเครื่องซักผ้า'
            );

        }


        // ต้องเป็น available ก่อนเริ่มงาน

        // Use MariaDB's clock for the lock check too. A future running_until
        // means the machine is still occupied even if its status drifted.
        if (
            $machine['status'] !== 'available' ||
            (bool) $machine['is_running']
        ) {

            throw new RuntimeException(
                'เครื่องนี้ไม่พร้อมใช้งาน'
            );

        }


        // =========================
        // CALCULATE RUNNING UNTIL
        // =========================

        // Calculate from the database clock so comparisons using
        // CURRENT_TIMESTAMP cannot expire the machine early due to timezone drift.
        $runningUntil = $db->query(
            'SELECT DATE_ADD(CURRENT_TIMESTAMP, INTERVAL ' . $duration_minutes . ' MINUTE)'
        )->fetchColumn();


        // =========================
        // UPDATE ORDER
        // =========================

        $stmt = $db->prepare("
            UPDATE orders

            SET
                payment_status = 'paid',
                paid_at = CURRENT_TIMESTAMP

            WHERE id = :order_id
              AND payment_status = 'pending'
        ");

        $stmt->execute([
            ':order_id' => $order_id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('รายการนี้ถูกดำเนินการไปแล้ว');
        }


        // =========================
        // UPDATE MACHINE
        // =========================

        $stmt = $db->prepare("
            UPDATE machines

            SET
                status = 'washing',
                running_until = :running_until

            WHERE id = :machine_id
              AND status = 'available'
              AND (running_until IS NULL OR running_until <= CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            ':machine_id' => $order['machine_id'],
            ':running_until' => $runningUntil
        ]);


        // ตรวจสอบว่าเครื่องถูกเปลี่ยนสถานะจริง

        if ($stmt->rowCount() !== 1) {

            throw new RuntimeException(
                'ไม่สามารถเริ่มเครื่องได้'
            );

        }


        // Commit
        $db->commit();


    } catch (Throwable $e) {

        // Rollback ถ้ามีปัญหา

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        die(
            'ไม่สามารถเริ่มการทำงานของเครื่องได้: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
        );

    }

}


// =========================
// IF ORDER ALREADY PAID
// =========================

// ถ้า refresh success.php
// จะไม่สร้างเวลาใหม่
// และจะไม่ reset countdown


if ($order['payment_status'] === 'paid') {

    /*
     * ไม่ต้องทำอะไร
     *
     * ระบบจะใช้ running_until
     * ที่บันทึกไว้ใน Database
     */

}


// =========================
// REDIRECT TO RUNNING PAGE
// =========================

header('Location: running.php');

exit;
