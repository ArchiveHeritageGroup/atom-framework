<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TkLabelCategory extends Model
{
    protected $table = 'tk_label_category';

    protected $fillable = [
        'code',
        'color',
        'sort_order',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(TkLabelCategoryI18n::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(TkLabel::class);
    }

    public function getNameAttribute(): string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->name ?? $this->code;
    }
}
