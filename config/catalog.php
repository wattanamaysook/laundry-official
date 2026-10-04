<?php

/** Shared local catalog until the MariaDB integration is ready. */
$branches = [
    1 => 'สาขามหาวิทยาลัยศรีปทุม',
    2 => 'สาขาบางเขน',
    3 => 'สาขารัชดา',
];

$branchAddresses = [
    1 => 'กรุงเทพมหานคร',
    2 => 'กรุงเทพมหานคร',
    3 => 'กรุงเทพมหานคร',
];

$modes = [
    'normal' => 'โหมดปกติ',
    'quick' => 'โหมดซักด่วน',
    'delicate' => 'โหมดถนอมผ้า',
    'bedding' => 'โหมดชุดเครื่องนอน',
    'wool' => 'โหมดผ้าขนสัตว์',
    'rinse_spin' => 'โหมดปั่นแห้ง',
];

$modePrices = [
    10 => ['normal' => 40, 'quick' => 40, 'delicate' => 45, 'bedding' => 50, 'wool' => 45, 'rinse_spin' => 20],
    15 => ['normal' => 50, 'quick' => 50, 'delicate' => 55, 'bedding' => 60, 'wool' => 55, 'rinse_spin' => 25],
    20 => ['normal' => 60, 'quick' => 60, 'delicate' => 65, 'bedding' => 70, 'wool' => 65, 'rinse_spin' => 30],
];

$modePriceUnits = [
    'normal' => 'บาท / ครั้ง',
    'quick' => 'บาท / ครั้ง',
    'delicate' => 'บาท / ครั้ง',
    'bedding' => 'บาท / ครั้ง',
    'wool' => 'บาท / ครั้ง',
    'rinse_spin' => 'บาท / ชั่วโมง',
];

$machines = [];
$machineId = 1;
foreach ($branches as $branchId => $branchName) {
    $branchMachineNumber = 1;
    foreach ([10, 15, 20] as $capacity) {
        for ($unit = 1; $unit <= 2; $unit++) {
            $machines[$machineId] = [
                'branch_id' => $branchId,
                'machine_code' => sprintf('M%02d', $branchMachineNumber),
                'name' => "เครื่องซักผ้า {$capacity} kg",
                'capacity_kg' => $capacity,
                'unit_number' => $branchMachineNumber,
                'starting_price' => $modePrices[$capacity]['normal'],
                'available' => true,
            ];
            $machineId++;
            $branchMachineNumber++;
        }
    }
}
