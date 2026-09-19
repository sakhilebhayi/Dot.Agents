<?php

namespace App\Skills\Platform;

use App\Skills\BaseSkill;

/**
 * VideoScriptHelper — shared computation for video content skills.
 *
 * Extracted from VideoScriptingSkill to eliminate duplication between
 * VideoScriptWriterSkill and VideoProductionSkill. Contains pure helpers
 * for script structure blueprints, storyboard visuals, and camera angles.
 */
abstract class VideoScriptHelper extends BaseSkill
{
    /** Default video duration targets in seconds by format. */
    protected const FORMAT_DURATIONS = [
        'short' => 60,
        'standard' => 180,
        'long' => 600,
        'reel' => 30,
        'explainer' => 120,
    ];

    /**
     * Per-section tone guidance, independent of format — the same section name
     * plays the same role in every format's pacing, so its delivery style stays
     * consistent while the requested overall $tone still colors every section.
     */
    private const SECTION_TONE_MODIFIERS = [
        'Intro' => 'welcoming, sets context',
        'Hook' => 'high-energy, attention-grabbing',
        'Problem Agitation' => 'empathetic but urgent',
        'Core Message' => 'clear and confident',
        'Main Content' => 'clear and confident',
        'Solution' => 'clear and confident',
        'Social Proof' => 'credible, trust-building',
        'Summary' => 'concise, reassuring',
        'CTA' => 'direct and motivating',
    ];

    /**
     * Return the section blueprint for the given format and tone.
     *
     * @return array<int, array{name: string, weight: float, cue: string, visual: string, tone_note: string}>
     */
    protected function getScriptStructure(string $format, string $tone): array
    {
        $structures = [
            'reel' => [
                ['name' => 'Hook', 'weight' => 0.20, 'cue' => 'Grab attention instantly', 'visual' => 'Bold text / striking visual'],
                ['name' => 'Core Message', 'weight' => 0.50, 'cue' => 'Deliver value fast', 'visual' => 'Demo / B-roll'],
                ['name' => 'CTA', 'weight' => 0.30, 'cue' => 'Drive one clear action', 'visual' => 'Logo + link'],
            ],
            'explainer' => [
                ['name' => 'Hook', 'weight' => 0.10, 'cue' => 'State the problem', 'visual' => 'Pain-point illustration'],
                ['name' => 'Problem Agitation', 'weight' => 0.20, 'cue' => 'Amplify the pain', 'visual' => 'Problem scenario'],
                ['name' => 'Solution', 'weight' => 0.40, 'cue' => 'Introduce the solution', 'visual' => 'Product / process demo'],
                ['name' => 'Social Proof', 'weight' => 0.15, 'cue' => 'Build credibility', 'visual' => 'Testimonials / stats'],
                ['name' => 'CTA', 'weight' => 0.15, 'cue' => 'Drive next action', 'visual' => 'Logo + URL'],
            ],
            'standard' => [
                ['name' => 'Intro', 'weight' => 0.10, 'cue' => 'Brand intro and context', 'visual' => 'Logo animation'],
                ['name' => 'Hook', 'weight' => 0.10, 'cue' => 'State the key promise', 'visual' => 'Key stat or headline'],
                ['name' => 'Main Content', 'weight' => 0.55, 'cue' => 'Deliver core value', 'visual' => 'Primary footage / demo'],
                ['name' => 'Summary', 'weight' => 0.10, 'cue' => 'Recap key points', 'visual' => 'Key takeaways card'],
                ['name' => 'CTA', 'weight' => 0.15, 'cue' => 'Clear next step', 'visual' => 'Contact / URL overlay'],
            ],
        ];

        $sections = $structures[$format] ?? $structures['standard'];

        return array_map(function (array $section) use ($tone) {
            $modifier = self::SECTION_TONE_MODIFIERS[$section['name']] ?? null;
            $section['tone_note'] = $modifier ? "{$tone}, {$modifier}" : $tone;

            return $section;
        }, $sections);
    }

    /** Generate a descriptive storyboard visual for the given frame position. */
    protected function storyboardVisual(int $frameNum, int $total, string $topic): string
    {
        $position = $frameNum / $total;

        if ($position <= 0.15) {
            return "Opening shot: Brand logo / title card with \"{$topic}\" text overlay";
        }
        if ($position <= 0.3) {
            return "Wide establishing shot introducing the context of {$topic}";
        }
        if ($position <= 0.7) {
            return "Medium shot: core demonstration / key information about {$topic}";
        }
        if ($position <= 0.85) {
            return 'Close-up or data graphic reinforcing the key message';
        }

        return 'Closing shot: Logo, tagline, and call-to-action overlay';
    }

    /** Pick a camera angle from a rotating palette. */
    protected function cameraAngle(int $frameNum, int $total): string
    {
        $angles = ['wide', 'medium', 'close_up', 'over_the_shoulder', 'aerial', 'medium'];

        return $angles[($frameNum - 1) % count($angles)];
    }
}
