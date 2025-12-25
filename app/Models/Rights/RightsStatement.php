<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RightsStatement extends Model
{
    protected $table = 'rights_statement';

    protected $fillable = [
        'uri',
        'code',
        'category',
        'icon_filename',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(RightsStatementI18n::class);
    }

    public function extendedRights(): HasMany
    {
        return $this->hasMany(ExtendedRights::class);
    }

    public function getNameAttribute(): string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    public function getDefinitionAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->definition;
    }

    public function getIconUrlAttribute(): string
    {
        return '/plugins/arExtendedRightsPlugin/images/rights-statements/' . ($this->icon_filename ?? 'default.png');
    }
}
