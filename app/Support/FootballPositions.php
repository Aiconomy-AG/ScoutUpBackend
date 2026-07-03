<?php

namespace App\Support;

class FootballPositions
{
    public const POSITIONS = [
        'GK',

        'RB',
        'RWB',
        'CB',
        'LB',
        'LWB',

        'CDM',
        'CM',
        'CAM',
        'RM',
        'LM',

        'RW',
        'LW',
        'CF',
        'ST',

        'MANAGER',
    ];

    public static function allowed(): array
    {
        return self::POSITIONS;
    }

    public static function normalize(?string $position): ?string
    {
        if ($position === null || trim($position) === '') {
            return null;
        }

        $key = strtolower(trim($position));
        $key = str_replace(['-', '_'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);

        $aliases = [
            'gk' => 'GK',
            'goalkeeper' => 'GK',
            'keeper' => 'GK',

            'rb' => 'RB',
            'right back' => 'RB',
            'rightback' => 'RB',

            'rwb' => 'RWB',
            'right wing back' => 'RWB',
            'right wingback' => 'RWB',

            'cb' => 'CB',
            'centre back' => 'CB',
            'center back' => 'CB',
            'central defender' => 'CB',
            'defender' => 'CB',

            'lb' => 'LB',
            'left back' => 'LB',
            'leftback' => 'LB',

            'lwb' => 'LWB',
            'left wing back' => 'LWB',
            'left wingback' => 'LWB',

            'cdm' => 'CDM',
            'dm' => 'CDM',
            'defensive midfielder' => 'CDM',

            'cm' => 'CM',
            'midfielder' => 'CM',
            'central midfielder' => 'CM',
            'centre midfielder' => 'CM',
            'center midfielder' => 'CM',

            'cam' => 'CAM',
            'am' => 'CAM',
            'attacking midfielder' => 'CAM',

            'rm' => 'RM',
            'right midfielder' => 'RM',
            'right mid' => 'RM',

            'lm' => 'LM',
            'left midfielder' => 'LM',
            'left mid' => 'LM',

            'rw' => 'RW',
            'right winger' => 'RW',
            'right wing' => 'RW',

            'lw' => 'LW',
            'left winger' => 'LW',
            'left wing' => 'LW',

            'cf' => 'CF',
            'centre forward' => 'CF',
            'center forward' => 'CF',

            'st' => 'ST',
            'striker' => 'ST',
            'forward' => 'ST',
            'attacker' => 'ST',

            'manager' => 'MANAGER',
            'coach' => 'MANAGER',
            'head coach' => 'MANAGER',
        ];

        return $aliases[$key] ?? strtoupper(trim($position));
    }

    public static function normalizeOrDefault(?string $position, string $default = 'CM'): string
    {
        $normalizedPosition = self::normalize($position);

        if (in_array($normalizedPosition, self::POSITIONS, true)) {
            return $normalizedPosition;
        }

        return $default;
    }
}
