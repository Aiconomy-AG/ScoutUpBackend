<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Squad extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'formation',
        'chemistry_score',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function squadPlayers(): HasMany
    {
        return $this->hasMany(SquadPlayer::class);
    }
}
