<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use App\Support\RunTaint;

/**
 * A final answer can carry data out without any tool: a remote picture loads by itself, and its address can hold
 * anything. So agents never put remote pictures in their answers, and a run that has read outside content cannot leave
 * a link to click either, only the address in plain sight.
 */
class OutputChannels
{
    public function close(AgentRun $run, string $text): string
    {
        $text = (string) preg_replace('/!\[([^\]]*)\]\(\s*[^)]*\)/', '$1', $text);
        $text = (string) preg_replace('/<img\b[^>]*>/i', '', $text);

        if (! RunTaint::has($run, RunTaint::UNTRUSTED)) {
            return $text;
        }

        return (string) preg_replace('/\[([^\]]*)\]\(\s*([^)\s]+)[^)]*\)/', '$1 ($2)', $text);
    }
}
