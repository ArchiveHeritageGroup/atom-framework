<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExtendedRights extends Model
{
    protected $table = 'extended_rights';

    protected $fillable = [
        'object_id',
        'rights_statement_id',
        'creative_commons_license_id',
        'rights_date',
        'expiry_date',
        'rights_holder',
        'rights_holder_uri',
        'is_primary',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rights_date' => 'date',
        'expiry_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public function rightsStatement(): BelongsTo
    {
        return $this->belongsTo(RightsStatement::class);
    }

    public function creativeCommonsLicense(): BelongsTo
    {
        return $this->belongsTo(CreativeCommonsLicense::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ExtendedRightsI18n::class);
    }

    public function tkLabels(): BelongsToMany
    {
        return $this->belongsToMany(TkLabel::class, 'extended_rights_tk_label')
            ->withPivot(['community_id', 'community_note', 'assigned_date'])
            ->withTimestamps();
    }

    public function getRightsNoteAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->rights_note;
    }

    public function getCopyrightNoticeAttribute(): ?string
    {
        $culture = sfContext::getInstance()->user->getCulture() ?? 'en';
        $translation = $this->translations->where('culture', $culture)->first()
            ?? $this->translations->where('culture', 'en')->first();
        return $translation?->copyright_notice;
    }
}
