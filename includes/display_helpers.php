<?php

function laundryMachineLabel(int $capacityKg, string $machineCode): string
{
    $parts = explode('-', $machineCode);
    $number = ltrim((string) end($parts), '0');
    $number = $number !== '' ? $number : '0';

    return sprintf('%d kg #%s · %s', $capacityKg, $number, $machineCode);
}

function laundryModeLabel(string $code, string $databaseName = ''): string
{
    $labels = [
        'normal' => ['โหมดปกติ', 'Normal'],
        'quick' => ['โหมดซักด่วน', 'Quick Wash'],
        'delicate' => ['โหมดถนอมผ้า', 'Delicate'],
        'bedding' => ['โหมดชุดเครื่องนอน', 'Bedding'],
        'wool' => ['โหมดผ้าขนสัตว์', 'Wool'],
        'rinse_spin' => ['โหมดปั่นแห้ง', 'Spin Dry'],
        'spin_dry' => ['โหมดปั่นแห้ง', 'Spin Dry'],
    ];

    if (isset($labels[$code])) {
        return $labels[$code][0] . ' (' . $labels[$code][1] . ')';
    }

    return $databaseName !== '' ? $databaseName : $code;
}
