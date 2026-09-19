<?php

namespace App\Skills\Platform;

use App\Skills\DTOs\SkillResult;

/**
 * VideoScriptWriterSkill — script and storyboard generation (Layer 5 — Platform Intelligence)
 *
 * Handles the two scripting-focused video actions extracted from VideoScriptingSkill:
 *   script      – produce a timed video script from a brief
 *   storyboard  – generate a visual scene-by-scene storyboard
 *
 * Shares structure/visual/camera helpers with VideoProductionSkill via VideoScriptHelper.
 */
class VideoScriptWriterSkill extends VideoScriptHelper
{
    public function key(): string
    {
        return 'video-script-writer';
    }

    public function layer(): string
    {
        return 'platform';
    }

    public function execute(array $input, array $context = []): SkillResult
    {
        $action = $input['action'] ?? 'script';

        return match ($action) {
            'script' => $this->generateScript($input),
            'storyboard' => $this->generateStoryboard($input),
            default => SkillResult::failed("Unknown video-script-writer action: [{$action}]"),
        };
    }

    private function generateScript(array $input): SkillResult
    {
        $topic = $input['topic'] ?? '';
        $brief = $input['brief'] ?? $topic;
        $format = $input['format'] ?? 'standard';
        $tone = $input['tone'] ?? 'professional';
        $audience = $input['audience'] ?? 'general';

        if (empty($topic)) {
            return SkillResult::failed('A topic is required to generate a video script');
        }

        $totalDuration = self::FORMAT_DURATIONS[$format] ?? self::FORMAT_DURATIONS['standard'];
        $structure = $this->getScriptStructure($format, $tone);

        $sections = [];
        $timeOffset = 0;

        foreach ($structure as $section) {
            $duration = (int) round($totalDuration * $section['weight']);
            $sections[] = [
                'section' => $section['name'],
                'start_sec' => $timeOffset,
                'end_sec' => $timeOffset + $duration,
                'duration_sec' => $duration,
                'narration_cue' => $section['cue'],
                'suggested_visual' => $section['visual'],
                'tone_note' => $section['tone_note'],
                'script_placeholder' => "[Write {$duration}s of {$section['name']} content about: {$brief}]",
            ];
            $timeOffset += $duration;
        }

        return SkillResult::completed(
            [
                'topic' => $topic,
                'format' => $format,
                'total_duration_sec' => $totalDuration,
                'target_audience' => $audience,
                'tone' => $tone,
                'section_count' => count($sections),
                'sections' => $sections,
                'estimated_word_count' => (int) round($totalDuration * 2.5),
            ],
            85.0,
            [],
            ['Replace [script_placeholder] text with actual narration before production']
        );
    }

    private function generateStoryboard(array $input): SkillResult
    {
        $topic = $input['topic'] ?? '';
        $brief = $input['brief'] ?? $topic;
        $format = $input['format'] ?? 'standard';
        $sceneCount = (int) ($input['scene_count'] ?? 6);

        if (empty($topic)) {
            return SkillResult::failed('A topic is required to generate a storyboard');
        }

        $totalDuration = self::FORMAT_DURATIONS[$format] ?? self::FORMAT_DURATIONS['standard'];
        $sceneDuration = (int) round($totalDuration / $sceneCount);

        $frames = [];
        for ($i = 1; $i <= $sceneCount; $i++) {
            $frames[] = [
                'frame' => $i,
                'start_sec' => ($i - 1) * $sceneDuration,
                'end_sec' => $i * $sceneDuration,
                'duration_sec' => $sceneDuration,
                'visual_description' => $this->storyboardVisual($i, $sceneCount, $brief),
                'camera_angle' => $this->cameraAngle($i, $sceneCount),
                'text_overlay' => $i === 1 ? $topic : ($i === $sceneCount ? 'CTA + Logo' : null),
                'audio_cue' => $i === 1 ? 'Intro music fade in' : ($i === $sceneCount ? 'Outro music fade out' : 'Ambient / VO continues'),
                'transition' => $i < $sceneCount ? 'cut' : 'fade_out',
            ];
        }

        return SkillResult::completed(
            [
                'topic' => $topic,
                'format' => $format,
                'total_duration_sec' => $totalDuration,
                'frame_count' => $sceneCount,
                'frames' => $frames,
            ],
            82.0,
            [],
            ['Attach this storyboard to your creative brief for the video production team']
        );
    }
}
