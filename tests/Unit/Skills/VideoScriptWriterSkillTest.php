<?php

namespace Tests\Unit\Skills;

use App\Skills\Platform\VideoScriptWriterSkill;
use Tests\TestCase;

class VideoScriptWriterSkillTest extends TestCase
{
    private VideoScriptWriterSkill $skill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skill = new VideoScriptWriterSkill;
    }

    public function test_key_and_layer_are_correct(): void
    {
        $this->assertSame('video-script-writer', $this->skill->key());
        $this->assertSame('platform', $this->skill->layer());
    }

    public function test_script_generates_timed_segments(): void
    {
        $result = $this->skill->execute([
            'action' => 'script',
            'topic' => 'Introducing our AI workforce platform',
            'format' => 'explainer',
        ]);

        $this->assertSame('completed', $result->status);
        $this->assertArrayHasKey('sections', $result->output);
        $this->assertArrayHasKey('total_duration_sec', $result->output);
    }

    public function test_script_sections_get_a_specific_tone_note_not_just_the_flat_tone(): void
    {
        $result = $this->skill->execute([
            'action' => 'script',
            'topic' => 'Introducing our AI workforce platform',
            'format' => 'explainer',
            'tone' => 'playful',
        ]);

        $sections = collect($result->output['sections'])->keyBy('section');

        $this->assertSame('playful, high-energy, attention-grabbing', $sections['Hook']['tone_note']);
        $this->assertSame('playful, direct and motivating', $sections['CTA']['tone_note']);
        $this->assertNotSame($sections['Hook']['tone_note'], $sections['CTA']['tone_note']);
    }

    public function test_script_fails_without_topic(): void
    {
        $result = $this->skill->execute(['action' => 'script', 'topic' => '']);

        $this->assertSame('failed', $result->status);
    }

    public function test_storyboard_returns_visual_frames(): void
    {
        $result = $this->skill->execute([
            'action' => 'storyboard',
            'topic' => 'Enterprise AI agent demo',
        ]);

        $this->assertSame('completed', $result->status);
        $this->assertArrayHasKey('frames', $result->output);
    }

    public function test_unknown_action_returns_failed(): void
    {
        $result = $this->skill->execute(['action' => 'nonexistent']);

        $this->assertSame('failed', $result->status);
    }
}
