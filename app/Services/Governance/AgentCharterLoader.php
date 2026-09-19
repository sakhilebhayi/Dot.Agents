<?php

namespace App\Services\Governance;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads an agent's charter straight off the Dot.Brain filesystem.
 *
 * Dot.Brain's charters are for the ecosystem-wide "colony agents" (governance,
 * finance, documentation, ...) that maintain Dot.Brain's own knowledge graph —
 * not for Dot.Agents' commercial marketplace catalog. It is expected and
 * correct for every current Dot.Agents deployment to come back uncharted.
 */
class AgentCharterLoader
{
    private const UNCHARTERED = [
        'chartered' => false,
        'status' => null,
        'trust_score_floor' => null,
        'human_approver' => null,
    ];

    /**
     * @return array{chartered: bool, status: ?string, trust_score_floor: ?float, human_approver: ?string}
     */
    public function charterFor(string $key): array
    {
        $path = rtrim((string) config('services.dot_brain.charters_path'), '/')."/{$key}.charter.md";

        if (! is_file($path)) {
            return self::UNCHARTERED;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            Log::warning('AgentCharterLoader: unable to read charter file', [
                'key' => $key,
                'path' => $path,
            ]);

            return self::UNCHARTERED;
        }

        $frontmatter = $this->extractFrontmatter($contents);

        if ($frontmatter === null) {
            Log::warning('AgentCharterLoader: charter file has no parsable frontmatter block', [
                'key' => $key,
                'path' => $path,
            ]);

            return self::UNCHARTERED;
        }

        try {
            $parsed = Yaml::parse($frontmatter);
        } catch (ParseException $e) {
            Log::warning('AgentCharterLoader: malformed charter frontmatter', [
                'key' => $key,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return self::UNCHARTERED;
        }

        if (! is_array($parsed)) {
            Log::warning('AgentCharterLoader: charter frontmatter did not parse to a map', [
                'key' => $key,
                'path' => $path,
            ]);

            return self::UNCHARTERED;
        }

        // Per Dot.Brain's own charter template: "An agent without a complete,
        // approved charter may not act." A non-active charter (draft, retired,
        // ...) confers no authority, so it is reported identically to no charter.
        if (($parsed['status'] ?? null) !== 'active') {
            return self::UNCHARTERED;
        }

        return [
            'chartered' => true,
            'status' => $parsed['status'],
            'trust_score_floor' => isset($parsed['trust-score-floor']) ? (float) $parsed['trust-score-floor'] : null,
            'human_approver' => isset($parsed['human-approver']) ? (string) $parsed['human-approver'] : null,
        ];
    }

    private function extractFrontmatter(string $contents): ?string
    {
        if (! preg_match('/^---\n(.*?)\n---\n/s', $contents, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
