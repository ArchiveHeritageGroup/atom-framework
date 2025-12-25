<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TkLabel extends Model
{
    protected $table = 'tk_label';

    protected $fillable = [
        'tk_label_category_id',
        'code',
        'uri',
        'icon_filename',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TkLabelCategory::class, 'tk_label_category_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TkLabelI18n::class);
    }

    public function getNameAttribute(): string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    public function getDescriptionAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->description;
    }

    public function getIconUrlAttribute(): string
    {
        return '/plugins/arExtendedRightsPlugin/images/tk-labels/' . ($this->icon_filename ?? 'default.png');
    }
}
