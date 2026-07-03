<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquadPlayer extends Model
{
    protected $fillable = [
        'squad_id',
        'player_id',
        'slot',
    ];

    public function squad(): BelongsTo
    {
        return $this->belongsTo(Squad::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
