<?php

namespace App\Models\Rights;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RightsStatementI18n extends Model
{
    protected $table = 'rights_statement_i18n';
    public $timestamps = false;

    protected $fillable = [
        'rights_statement_id',
        'culture',
        'name',
        'definition',
        'scope_note',
    ];

    public function rightsStatement(): BelongsTo
    {
        return $this->belongsTo(RightsStatement::class);
    }
}
