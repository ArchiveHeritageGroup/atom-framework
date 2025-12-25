<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbargoI18n extends Model
{
    protected $table = 'embargo_i18n';
    public $timestamps = false;

    protected $fillable = [
        'embargo_id',
        'culture',
        'reason',
        'notes',
        'public_message',
    ];

    public function embargo(): BelongsTo
    {
        return $this->belongsTo(Embargo::class);
    }
}
