<?php

namespace App\Actions\Organizations;

use App\DTOs\Organizations\AddKnowledgeSourceData;
use App\Jobs\IndexKnowledgeArticle;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeBase;
use App\Services\Governance\AuditService;
use App\Services\Http\UnsafeUrl;
use App\Services\Http\UrlGuard;
use App\Services\Knowledge\TextExtractor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Adds a file or an approved website to a knowledge base. It is read in the background, and stored outside the web root. */
class AddKnowledgeSourceAction
{
    private const MAX_KB = 20480;

    public function __construct(private readonly UrlGuard $guard, private readonly AuditService $audit) {}

    public function execute(AddKnowledgeSourceData $data): KnowledgeArticle
    {
        $base = KnowledgeBase::findOrFail($data->knowledgeBaseId);

        if (! $data->actor->canInOrganization('knowledge.manage', $base->organization_id)) {
            throw new AuthorizationException('You cannot change knowledge in this business.');
        }

        $article = KnowledgeArticle::create([
            'knowledge_base_id' => $base->id,
            'organization_id' => $base->organization_id,
            'created_by' => $data->actor->id,
            'title' => $data->title,
            'slug' => Str::slug($data->title).'-'.Str::lower(Str::random(6)),
            'content' => '',
            'is_published' => true,
            ...($data->file !== null ? $this->file($data->file, $base->organization_id) : $this->website((string) $data->url)),
        ]);

        $this->audit->logUserAction(event: 'knowledge_source.added', description: "Added knowledge source '{$article->title}'", subject: $article);

        IndexKnowledgeArticle::dispatch($article);

        return $article;
    }

    /** @return array<string, mixed> */
    private function file(UploadedFile $file, int $organizationId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, TextExtractor::TYPES, true) || $file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages(['file' => 'Use a PDF, Word, Excel, CSV, text or Markdown file up to 20 MB.']);
        }

        return [
            'source_type' => 'upload',
            'file_path' => $file->storeAs("knowledge/{$organizationId}", Str::uuid().".{$extension}", 'local'),
            'file_type' => $extension,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 200),
        ];
    }

    /** @return array<string, mixed> */
    private function website(string $url): array
    {
        try {
            $this->guard->assertSafe($url);
        } catch (UnsafeUrl $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        return ['source_type' => 'website', 'source_url' => $url];
    }
}
