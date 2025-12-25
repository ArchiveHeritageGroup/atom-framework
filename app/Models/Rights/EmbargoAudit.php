<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbargoAudit extends Model
{
    protected $table = 'embargo_audit';

    protected $fillable = [
        'embargo_id',
        'action',
        'user_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function embargo(): BelongsTo
    {
        return $this->belongsTo(Embargo::class);
    }
}
