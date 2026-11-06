<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\Http\SafeHttp;
use App\Services\Http\UnsafeUrl;
use App\Services\Triggers\BusinessEvents;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Reads a document and replaces its passages. A failure is recorded on the document for its owner to see. */
class KnowledgeIndexer
{
    public function __construct(
        private readonly TextExtractor $extractor,
        private readonly Chunker $chunker,
        private readonly SafeHttp $http,
        private readonly BusinessEvents $events,
    ) {}

    public function index(KnowledgeArticle $article): void
    {
        try {
            $text = $this->text($article);
        } catch (RuntimeException $e) {
            $article->update(['index_status' => 'failed', 'index_error' => mb_substr($e->getMessage(), 0, 250)]);

            return;
        }

        DB::transaction(function () use ($article, $text) {
            KnowledgeChunk::where('knowledge_article_id', $article->id)->delete();

            foreach ($this->chunker->split($text) as $position => $chunk) {
                KnowledgeChunk::create([
                    'organization_id' => $article->organization_id,
                    'knowledge_base_id' => $article->knowledge_base_id,
                    'knowledge_article_id' => $article->id,
                    'position' => $position,
                    'content' => $chunk,
                    'search_text' => mb_strtolower($article->title."\n".$chunk),
                ]);
            }

            $article->update(['index_status' => 'indexed', 'index_error' => null, 'indexed_at' => now()]);
        });

        $this->events->raise($article->organization_id, 'knowledge.added', "{$article->id}-{$article->indexed_at?->timestamp}", "The document {$article->title} was added.");
    }

    private function text(KnowledgeArticle $article): string
    {
        return match ($article->source_type) {
            'upload' => $this->extractor->text(Storage::disk('local')->path($article->file_path), $article->file_type),
            'website' => $this->website((string) $article->source_url),
            default => (string) $article->content,
        };
    }

    private function website(string $url): string
    {
        try {
            $response = $this->http->send('GET', $url, ['Accept' => 'text/html,text/plain'], null, 15);
        } catch (UnsafeUrl $e) {
            throw new RuntimeException($e->getMessage());
        } catch (ConnectionException) {
            throw new RuntimeException('That website did not answer in time.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("That website answered with {$response->status()}.");
        }

        $html = (string) preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $response->body());
        $html = (string) preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
