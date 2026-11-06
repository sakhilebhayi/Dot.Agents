<?php

declare(strict_types=1);

namespace App\DTOs\Organizations;

use App\Models\User;
use Illuminate\Http\UploadedFile;

readonly class AddKnowledgeSourceData
{
    public function __construct(
        public User $actor,
        public int $knowledgeBaseId,
        public string $title,
        public ?UploadedFile $file = null,
        public ?string $url = null,
    ) {}
}
