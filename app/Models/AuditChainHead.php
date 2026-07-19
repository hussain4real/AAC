<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $team_id
 * @property int $next_sequence
 * @property string|null $last_signature
 */
#[Fillable(['team_id', 'next_sequence', 'last_signature'])]
class AuditChainHead extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'team_id';

    protected function casts(): array
    {
        return ['next_sequence' => 'integer'];
    }
}
