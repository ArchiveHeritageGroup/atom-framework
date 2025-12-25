<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbargoException extends Model
{
    protected $table = 'embargo_exception';

    protected $fillable = [
        'embargo_id',
        'exception_type',
        'exception_id',
        'ip_range_start',
        'ip_range_end',
        'valid_from',
        'valid_until',
        'notes',
        'granted_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function embargo(): BelongsTo
    {
        return $this->belongsTo(Embargo::class);
    }
}
