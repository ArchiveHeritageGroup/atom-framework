<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreativeCommonsLicense extends Model
{
    protected $table = 'creative_commons_license';

    protected $fillable = [
        'uri',
        'code',
        'version',
        'allows_adaptation',
        'allows_commercial',
        'requires_attribution',
        'requires_sharealike',
        'icon_filename',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'allows_adaptation' => 'boolean',
        'allows_commercial' => 'boolean',
        'requires_attribution' => 'boolean',
        'requires_sharealike' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CreativeCommonsLicenseI18n::class);
    }

    public function getNameAttribute(): string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    public function getIconUrlAttribute(): string
    {
        return '/plugins/arExtendedRightsPlugin/images/creative-commons/' . ($this->icon_filename ?? 'cc-logo.png');
    }

    public function getBadgeHtmlAttribute(): string
    {
        $icons = [];
        if ($this->requires_attribution) $icons[] = 'by';
        if (!$this->allows_commercial) $icons[] = 'nc';
        if (!$this->allows_adaptation) $icons[] = 'nd';
        if ($this->requires_sharealike) $icons[] = 'sa';
        
        $html = '<span class="cc-icons">';
        $html .= '<img src="/plugins/arExtendedRightsPlugin/images/creative-commons/cc.png" alt="CC" class="cc-icon">';
        foreach ($icons as $icon) {
            $html .= '<img src="/plugins/arExtendedRightsPlugin/images/creative-commons/' . $icon . '.png" alt="' . strtoupper($icon) . '" class="cc-icon">';
        }
        $html .= '</span>';
        return $html;
    }
}
