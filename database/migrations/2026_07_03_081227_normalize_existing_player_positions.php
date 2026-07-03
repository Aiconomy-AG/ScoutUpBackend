<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $players = DB::table('players')
            ->select('id', 'position')
            ->get();

        foreach ($players as $player) {
            DB::table('players')
                ->where('id', $player->id)
                ->update([
                    'position' => $this->normalizePosition($player->position),
                ]);
        }
    }

    public function down(): void
    {
        // Nu putem reconstrui valorile vechi gen "Striker" după ce le-am normalizat.
    }

    private function normalizePosition(?string $position): string
    {
        if (!$position) {
            return 'CM';
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

        return $aliases[$key] ?? 'CM';
    }
};
