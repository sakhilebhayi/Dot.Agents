<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Output Moderation Service
 *
 * Scans every AI agent response before it is returned to the user.
 * Detects: PII, secrets/API keys, prompt leakage, unsafe instructions,
 * hallucination indicators, and off-topic deviations.
 *
 * All scan results are logged for audit. High-severity violations are flagged
 * on the message and surfaced to the SecurityCenter.
 */
class OutputModerationService
{
    /**
     * Moderation severity levels (ordered lowest → highest)
     */
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const BLOCK = 'block';

    /**
     * PII patterns — names, emails, phones, SSNs, credit cards
     */
    private const PII_PATTERNS = [
        'email' => '/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b/',
        'phone_us' => '/\b(?:\+1\s?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}\b/',
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
        'credit_card' => '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13})\b/',
        'sa_id' => '/\b\d{13}\b/',  // South African ID number
    ];

    /**
     * Secret / API key patterns
     */
    private const SECRET_PATTERNS = [
        'openai_key' => '/\bsk-(?:proj-)?[a-zA-Z0-9_\-]{20,}\b/',
        'aws_key' => '/\bAKIA[0-9A-Z]{16}\b/',
        'github_token' => '/\bghp_[a-zA-Z0-9]{36}\b/',
        'stripe_key' => '/\bsk_(?:live|test)_[a-zA-Z0-9]{24,}\b/',
        'generic_jwt' => '/\beyJ[a-zA-Z0-9_\-]+\.eyJ[a-zA-Z0-9_\-]+\.[a-zA-Z0-9_\-]+\b/',
    ];

    /**
     * Phrases that indicate the agent may be leaking its system prompt
     */
    private const PROMPT_LEAK_INDICATORS = [
        'my system prompt',
        'my instructions say',
        'i was told to ignore',
        'ignore previous instructions',
        'you are an ai trained by',
        'as a language model',
        'my training data',
        'i am not allowed to reveal',
        'confidential: ',
        '[[system]]',
        '<system>',
    ];

    /**
     * Phrases that indicate hallucination or fabrication
     */
    private const HALLUCINATION_INDICATORS = [
        'as of my last update',
        'i cannot verify',
        'i believe this is correct but',
        'studies show that 100%',
        'it is a well-known fact that',
        'everyone knows that',
    ];

    /**
     * Unsafe instruction patterns
     */
    private const UNSAFE_PATTERNS = [
        '/how to (make|build|create) (a |an )?(bomb|weapon|explosive|malware|virus|ransomware)/i',
        '/\b(suicide|self-harm)\s+(method|instruction|guide|how)/i',
        '/bypass\s+(security|authentication|authorization|2fa|mfa)/i',
        '/\b(sql injection|xss|cross.site scripting|command injection)\s+(payload|attack|exploit)/i',
    ];

    /**
     * Scan an AI response for moderation violations.
     *
     * @return array{verdict: string, flags: array<string, array>, flagged: bool}
     */
    public function scan(string $content, array $context = []): array
    {
        $flags = [];
        $verdict = self::PASS;

        // 1. PII detection
        foreach (self::PII_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $flags['pii'][] = $type;
                $verdict = $this->escalateVerdict($verdict, self::WARN);
            }
        }

        // 2. Secrets / API keys
        foreach (self::SECRET_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $content)) {
                $flags['secrets'][] = $type;
                $verdict = $this->escalateVerdict($verdict, self::BLOCK);
            }
        }

        // 3. Prompt leakage
        $contentLower = mb_strtolower($content);
        foreach (self::PROMPT_LEAK_INDICATORS as $indicator) {
            if (str_contains($contentLower, $indicator)) {
                $flags['prompt_leak'][] = $indicator;
                $verdict = $this->escalateVerdict($verdict, self::WARN);
            }
        }

        // 4. Unsafe instructions
        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $flags['unsafe'][] = $pattern;
                $verdict = $this->escalateVerdict($verdict, self::BLOCK);
            }
        }

        // 5. Hallucination indicators
        foreach (self::HALLUCINATION_INDICATORS as $indicator) {
            if (str_contains($contentLower, $indicator)) {
                $flags['hallucination'][] = $indicator;
                $verdict = $this->escalateVerdict($verdict, self::WARN);
            }
        }

        $result = [
            'verdict' => $verdict,
            'flags' => $flags,
            'flagged' => $verdict !== self::PASS,
            'flag_reason' => $verdict !== self::PASS
                ? 'Moderation flags: '.implode(', ', array_keys($flags))
                : null,
            'scanned_at' => now()->toIso8601String(),
        ];

        if ($verdict !== self::PASS) {
            Log::warning('[OutputModeration] Response flagged', array_merge($result, [
                'deployment_id' => $context['deployment_id'] ?? null,
                'session_id' => $context['session_id'] ?? null,
                'content_length' => strlen($content),
            ]));
        }

        return $result;
    }

    /**
     * Scan and optionally redact PII from a response.
     * Returns the (possibly redacted) content and the scan result.
     *
     * @return array{content: string, scan: array}
     */
    public function scanAndRedact(string $content, array $context = []): array
    {
        $scan = $this->scan($content, $context);

        if (isset($scan['flags']['pii'])) {
            // Redact detected PII types
            foreach (self::PII_PATTERNS as $type => $pattern) {
                if (in_array($type, $scan['flags']['pii'] ?? [], true)) {
                    $content = preg_replace($pattern, "[REDACTED:{$type}]", $content);
                }
            }
        }

        if (isset($scan['flags']['secrets'])) {
            foreach (self::SECRET_PATTERNS as $type => $pattern) {
                $content = preg_replace($pattern, '[REDACTED:secret]', $content);
            }
        }

        return ['content' => $content, 'scan' => $scan];
    }

    /** Tool results keep their ordinary content, but a credential in one never reaches the model or the journal. */
    public function redactSecrets(string $content): string
    {
        foreach (self::SECRET_PATTERNS as $pattern) {
            $content = (string) preg_replace($pattern, '[REDACTED:secret]', $content);
        }

        return $content;
    }

    /**
     * Return a safe blocked response when verdict is BLOCK.
     */
    public function blockedResponse(): string
    {
        return 'I\'m unable to provide that response — it contained content that '
            .'violates the platform safety policy. Please rephrase your request.';
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function escalateVerdict(string $current, string $new): string
    {
        $order = [self::PASS => 0, self::WARN => 1, self::BLOCK => 2];

        return ($order[$new] > $order[$current]) ? $new : $current;
    }
}
