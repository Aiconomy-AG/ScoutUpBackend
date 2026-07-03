<?php

namespace App\Support;

class FootballFormations
{
    public const FORMATIONS = [
        '4-3-3' => [
            'LW',
            'ST',
            'RW',

            'CM1',
            'CM2',
            'CM3',

            'LB',
            'CB1',
            'CB2',
            'RB',

            'GK',

            'MANAGER',
        ],
    ];

    public static function allowedFormations(): array
    {
        return array_keys(self::FORMATIONS);
    }

    public static function slotsFor(string $formation): array
    {
        return self::FORMATIONS[$formation] ?? self::FORMATIONS['4-3-3'];
    }
}
