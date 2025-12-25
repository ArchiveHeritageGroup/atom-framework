<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkLabelI18n extends Model
{
    protected $table = 'tk_label_i18n';
    public $timestamps = false;

    protected $fillable = [
        'tk_label_id',
        'culture',
        'name',
        'description',
        'usage_guide',
    ];

    public function label(): BelongsTo
    {
        return $this->belongsTo(TkLabel::class, 'tk_label_id');
    }
}
