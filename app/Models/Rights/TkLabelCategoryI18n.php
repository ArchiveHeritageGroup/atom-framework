<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TkLabelCategoryI18n extends Model
{
    protected $table = 'tk_label_category_i18n';
    public $timestamps = false;

    protected $fillable = [
        'tk_label_category_id',
        'culture',
        'name',
        'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TkLabelCategory::class, 'tk_label_category_id');
    }
}
