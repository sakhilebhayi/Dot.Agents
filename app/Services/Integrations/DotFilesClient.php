<?php

namespace App\Services\Integrations;

use App\Services\Tools\ToolFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The Dot.Files platform file API: a signed POST, HMAC-SHA256 over "{timestamp}.{raw body}" with this platform's
 * secret. The effect record is the outbox here: the call is written down before it runs and carries an idempotency key,
 * so a lost answer is recorded as unknown and never sent twice.
 */
class DotFilesClient
{
    public function enabled(): bool
    {
        return filled(config('services.files.url')) && filled(config('services.files.secret'));
    }

    /** @return array<string, mixed> */
    public function store(string $teamUuid, string $name, string $text, string $idempotencyKey): array
    {
        $body = (string) json_encode(['team_uuid' => $teamUuid, 'name' => $name, 'content_b64' => base64_encode($text), 'folder' => config('services.files.folder')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stamp = (string) now()->timestamp;

        try {
            $response = Http::timeout(20)->connectTimeout(5)->acceptJson()->withHeaders([
                'X-Dot-Platform' => (string) config('services.files.platform'),
                'X-Dot-Timestamp' => $stamp,
                'X-Dot-Signature' => 'sha256='.hash_hmac('sha256', "{$stamp}.{$body}", (string) config('services.files.secret')),
                'Idempotency-Key' => $idempotencyKey,
            ])->withBody($body, 'application/json')->post(rtrim((string) config('services.files.url'), '/').'/api/platform/files/store');
        } catch (ConnectionException) {
            throw new ToolFailure('Dot.Files did not answer.', transient: true, outcomeUnknown: true);
        }

        return match (true) {
            $response->successful() => (array) $response->json(),
            $response->serverError() => throw new ToolFailure('Dot.Files had an error.', transient: true, outcomeUnknown: true),
            $response->status() === 401 => throw new ToolFailure('Dot.Files did not accept this platform\'s signature.'),
            $response->status() === 404 => throw new ToolFailure('Dot.Files does not know this business.'),
            default => throw new ToolFailure("Dot.Files refused the document ({$response->status()})."),
        };
    }
}
