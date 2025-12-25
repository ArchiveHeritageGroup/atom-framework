<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeCommonsLicenseI18n extends Model
{
    protected $table = 'creative_commons_license_i18n';
    public $timestamps = false;

    protected $fillable = [
        'creative_commons_license_id',
        'culture',
        'name',
        'description',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(CreativeCommonsLicense::class, 'creative_commons_license_id');
    }
}
