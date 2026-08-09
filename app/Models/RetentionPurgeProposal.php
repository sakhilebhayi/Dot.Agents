<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPurgeProposal extends Model
{
    protected $fillable = [
        'model_class', 'eligible_count', 'retention_summary', 'status',
        'reviewed_by', 'reviewed_at', 'reviewer_notes', 'deleted_count',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
