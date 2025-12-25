<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtendedRightsI18n extends Model
{
    protected $table = 'extended_rights_i18n';
    public $timestamps = false;

    protected $fillable = [
        'extended_rights_id',
        'culture',
        'rights_note',
        'usage_conditions',
        'copyright_notice',
    ];

    public function extendedRights(): BelongsTo
    {
        return $this->belongsTo(ExtendedRights::class);
    }
}
