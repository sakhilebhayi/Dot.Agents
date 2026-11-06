<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/** An API key. It belongs to one business and can do only what it was given. */
class PersonalAccessToken extends SanctumToken
{
    public const ABILITIES = [
        'read' => 'Read agents, runs and settings',
        'write' => 'Change agents and settings',
        'run' => 'Start agents and tools',
        'approve' => 'Answer approval requests',
    ];

    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'organization_id'];

    protected function casts(): array
    {
        return [...parent::casts(), 'organization_id' => 'integer'];
    }
}
