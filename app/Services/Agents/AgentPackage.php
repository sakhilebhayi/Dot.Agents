<?php

namespace App\Services\Agents;

use App\Models\AgentDeployment;
use InvalidArgumentException;

/** An agent's setup as a file, to move between businesses. Tools and knowledge are named, never carried: access is granted again here. */
final class AgentPackage
{
    public const FORMAT = 'dot-agent/1';

    /** @return array<string, mixed> */
    public static function export(AgentDeployment $deployment): array
    {
        $version = $deployment->draftVersion()->first() ?? $deployment->publishedVersion;
        $config = $version?->settings() ?? AgentConfig::defaults();
        unset($config['knowledge']);

        return [
            'format' => self::FORMAT,
            'name' => $deployment->name,
            'role' => $deployment->role,
            'responsibility' => $deployment->responsibility,
            'config' => $config,
        ];
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array{name: string, role: string, responsibility: ?string, config: array<string, mixed>}
     */
    public static function read(array $package): array
    {
        if (($package['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('This file is not a Dot.Agents export.');
        }

        if (! is_string($package['name'] ?? null) || trim($package['name']) === '') {
            throw new InvalidArgumentException('This file has no agent name.');
        }

        $config = AgentConfig::normalise(is_array($package['config'] ?? null) ? $package['config'] : []);
        $config['knowledge'] = [];

        return [
            'name' => mb_substr(trim($package['name']), 0, 100),
            'role' => mb_substr((string) ($package['role'] ?? ''), 0, 120),
            'responsibility' => isset($package['responsibility']) ? mb_substr((string) $package['responsibility'], 0, 2000) : null,
            'config' => $config,
        ];
    }
}
