<?php

namespace App\Services\AI;

use App\Models\ExecutiveCouncilSession;
use App\Models\ExecutiveRecommendation;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

/**
 * Executive Council Service
 *
 * Orchestrates multi-agent deliberation sessions where specialized AI executives
 * each bring their domain perspective to major organizational decisions.
 *
 * Executive Roles:
 *   CEO  — Strategic direction, mission alignment
 *   CFO  — Financial impact, ROI, budget risk
 *   COO  — Operational feasibility, resource capacity
 *   CTO  — Technical architecture, integration risk
 *   CIO  — Data governance, information security
 *   CHRO — People impact, culture, change management
 *   CMO  — Customer / market impact, brand alignment
 *   CISO — Security, threat modeling, compliance risk
 *   CSO  — Sustainability, long-term strategic risk
 */
class ExecutiveCouncilService
{
    private const AI_MODEL = 'gpt-4o-mini';

    private const EXECUTIVE_ROLES = [
        'ceo' => 'Chief Executive Officer',
        'cfo' => 'Chief Financial Officer',
        'coo' => 'Chief Operating Officer',
        'cto' => 'Chief Technology Officer',
        'cio' => 'Chief Information Officer',
        'chro' => 'Chief Human Resources Officer',
        'cmo' => 'Chief Marketing Officer',
        'ciso' => 'Chief Information Security Officer',
        'cso' => 'Chief Strategy Officer',
    ];

    private const DOMAIN_FOCUS = [
        'ceo' => 'strategic alignment, competitive positioning, mission advancement, stakeholder value',
        'cfo' => 'financial risk, ROI, budget impact, cash flow, cost-benefit analysis',
        'coo' => 'operational feasibility, resource requirements, process impact, scalability',
        'cto' => 'technical architecture, integration complexity, engineering effort, technical debt',
        'cio' => 'data governance, information architecture, system integration, data quality',
        'chro' => 'people impact, skill requirements, change management, cultural alignment, hiring',
        'cmo' => 'customer experience, brand reputation, market positioning, growth potential',
        'ciso' => 'security threats, compliance requirements, data protection, attack surface',
        'cso' => 'long-term strategic fit, sustainability, competitive advantage, market trends',
    ];

    public function __construct(
        private readonly AiInputSanitizer $sanitizer,
    ) {}

    /**
     * Convene a full executive council session.
     *
     * @param  string  $sessionType  procurement|strategic_initiative|ai_deployment|risk_assessment|budget|other
     * @param  string  $context  Full description of the decision to evaluate
     * @param  array  $inputData  Structured context data (budget amounts, timelines, etc.)
     * @param  int|null  $triggeredBy  User ID
     */
    public function conveneSession(
        int $organizationId,
        string $sessionType,
        string $title,
        string $context,
        array $inputData = [],
        ?int $triggeredBy = null
    ): ExecutiveCouncilSession {
        // SECURITY: sanitize user-supplied context/title before passing to AI models.
        $this->sanitizer->assertSafe($context);
        $this->sanitizer->assertSafe($title);

        $session = ExecutiveCouncilSession::create([
            'organization_id' => $organizationId,
            'triggered_by' => $triggeredBy,
            'session_type' => $sessionType,
            'title' => $title,
            'context' => $context,
            'status' => 'deliberating',
            'input_data' => $inputData,
            'deliberation_started_at' => now(),
        ]);

        $recommendations = [];
        foreach (self::EXECUTIVE_ROLES as $role => $roleTitle) {
            $recommendation = $this->getExecutiveRecommendation($session, $role, $roleTitle, $context, $inputData);
            if ($recommendation) {
                $recommendations[] = $recommendation;
            }
        }

        $this->buildConsensus($session, $recommendations);

        return $session->fresh(['recommendations']);
    }

    /**
     * Deliberate on a single executive role's perspective.
     */
    private function getExecutiveRecommendation(
        ExecutiveCouncilSession $session,
        string $role,
        string $roleTitle,
        string $context,
        array $inputData
    ): ?ExecutiveRecommendation {
        $systemPrompt = "You are a {$roleTitle} at an enterprise company using an AI platform. ".
            'Your domain focus is: '.self::DOMAIN_FOCUS[$role].'. '.
            'You must provide a specific, data-grounded recommendation with clear reasoning. '.
            'Respond in JSON with keys: recommendation (string), impact_analysis (object), '.
            'confidence_score (0-100), risk_score (0-100), evidence (array), '.
            'alternatives (array), vote (for|against|abstain|conditional), vote_rationale (string).';

        $userPrompt = "Decision context: {$context}\n\nAdditional data: ".json_encode($inputData);

        try {
            $response = OpenAI::chat()->create([
                'model' => self::AI_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 1000,
                'temperature' => 0.3,
            ]);

            $content = $response->choices[0]->message->content ?? '{}';
            $parsed = json_decode($content, true);

            if (! $parsed || json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return ExecutiveRecommendation::create([
                'session_id' => $session->id,
                'organization_id' => $session->organization_id,
                'agent_role' => $role,
                'domain' => self::DOMAIN_FOCUS[$role],
                'recommendation' => $parsed['recommendation'] ?? '',
                'impact_analysis' => $parsed['impact_analysis'] ?? [],
                'confidence_score' => min(100, max(0, $parsed['confidence_score'] ?? 70)),
                'risk_score' => min(100, max(0, $parsed['risk_score'] ?? 50)),
                'evidence' => $parsed['evidence'] ?? [],
                'alternatives' => $parsed['alternatives'] ?? [],
                'vote' => in_array($parsed['vote'] ?? '', ['for', 'against', 'abstain', 'conditional'])
                    ? $parsed['vote']
                    : 'abstain',
                'vote_rationale' => $parsed['vote_rationale'] ?? '',
            ]);
        } catch (Throwable $e) {
            Log::warning('[ExecutiveCouncilService] Executive recommendation failed', [
                'role' => $role,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Calculate consensus and finalize the session.
     */
    private function buildConsensus(ExecutiveCouncilSession $session, array $recommendations): void
    {
        $votesFor = 0;
        $votesAgainst = 0;
        $totalConfidence = 0;
        $dominantRecommendations = [];

        foreach ($recommendations as $rec) {
            if ($rec->vote === 'for' || $rec->vote === 'conditional') {
                $votesFor++;
            } elseif ($rec->vote === 'against') {
                $votesAgainst++;
            }
            $totalConfidence += $rec->confidence_score;
            $dominantRecommendations[] = "[{$rec->agent_role}]: {$rec->recommendation}";
        }

        $avgConfidence = count($recommendations) > 0 ? $totalConfidence / count($recommendations) : 0;

        $majorityVote = $votesFor > $votesAgainst ? 'approve' : ($votesAgainst > $votesFor ? 'reject' : 'defer');

        $session->update([
            'status' => 'completed',
            'votes_for' => $votesFor,
            'votes_against' => $votesAgainst,
            'votes_cast' => count($recommendations),
            'agents_consulted' => count($recommendations),
            'consensus_confidence' => round($avgConfidence, 2),
            'consensus_recommendation' => [
                'decision' => $majorityVote,
                'confidence' => round($avgConfidence, 2),
                'for_count' => $votesFor,
                'against_count' => $votesAgainst,
                'key_points' => array_slice($dominantRecommendations, 0, 5),
            ],
            'final_decision' => $majorityVote,
            'completed_at' => now(),
            'deliberation_duration_seconds' => (int) round(now()->diffInSeconds($session->deliberation_started_at, absolute: true)),
        ]);
    }
}
