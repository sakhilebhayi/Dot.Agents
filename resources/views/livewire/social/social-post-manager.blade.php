<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Social Post Manager</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Schedule, approve, and publish across all channels</p>
        </div>
        <div class="flex items-center gap-3">
            @if($this->pendingCount > 0)
                <span class="px-3 py-1.5 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-sm font-semibold">
                    {{ $this->pendingCount }} pending approval
                </span>
            @endif
            <button wire:click="$toggle('showCompose')"
                class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition">
                + Compose Post
            </button>
        </div>
    </div>

    {{-- Compose Panel --}}
    @if($showCompose)
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Compose Post</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Post Type</label>
                    <select wire:model="composingPostType"
                        class="w-full text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-purple-light">
                        <option value="post">Standard Post</option>
                        <option value="reel">Reel / Short Video</option>
                        <option value="story">Story</option>
                        <option value="article">Article (LinkedIn)</option>
                        <option value="tweet">Tweet (X)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Content</label>
                    <textarea wire:model="composingContent"
                        rows="4"
                        placeholder="Write your post content..."
                        class="w-full text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-brand-purple-light resize-none"></textarea>
                    @error('composingContent') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Schedule (optional)</label>
                    <input type="datetime-local" wire:model="composingScheduledAt"
                        class="text-sm border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-purple-light">
                    @error('composingScheduledAt') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center gap-3 pt-2">
                    <button wire:click="schedulePost"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-lg transition">
                        <span wire:loading.remove wire:target="schedulePost">Submit for Approval</span>
                        <span wire:loading wire:target="schedulePost">Submitting…</span>
                    </button>
                    <button wire:click="$set('showCompose', false)"
                        class="px-5 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Filter Tabs --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach(['pending' => 'Pending Approval', 'scheduled' => 'Scheduled', 'published' => 'Published', 'draft' => 'Drafts', 'all' => 'All'] as $val => $lbl)
            <button wire:click="$set('filter', '{{ $val }}')"
                class="px-3.5 py-1.5 text-xs font-medium rounded-full transition {{ $filter === $val ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                {{ $lbl }}
            </button>
        @endforeach
    </div>

    {{-- Posts Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @forelse($this->posts as $post)
            <div class="bg-white dark:bg-gray-900 rounded-xl border {{ $post->approval_status === 'pending' ? 'border-yellow-300 dark:border-yellow-700' : 'border-gray-200 dark:border-gray-800' }} p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 capitalize">
                            {{ $post->socialPage->socialAccount->platform ?? '—' }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-xs font-medium capitalize
                            {{ match($post->status) {
                                'published' => 'bg-green-100 text-green-700',
                                'scheduled' => 'bg-blue-100 text-blue-700',
                                'draft' => 'bg-gray-100 text-gray-600',
                                'failed' => 'bg-red-100 text-red-700',
                                default => 'bg-gray-100 text-gray-600',
                            } }}">
                            {{ ucfirst($post->status) }}
                        </span>
                        @if($post->approval_status === 'pending')
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Awaiting Approval</span>
                        @endif
                    </div>
                    @if($post->agent_deployment_id)
                        <span class="text-xs text-brand-purple-light">AI Generated</span>
                    @endif
                </div>

                <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed line-clamp-3">{{ $post->content }}</p>

                @if($post->scheduled_at)
                    <p class="text-xs text-gray-500 mt-3">Scheduled: {{ $post->scheduled_at->format('M j, Y H:i') }}</p>
                @endif

                @if($post->isPublished())
                    <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500">
                        <span>👍 {{ number_format($post->like_count) }}</span>
                        <span>💬 {{ number_format($post->comment_count) }}</span>
                        <span>🔁 {{ number_format($post->share_count) }}</span>
                        <span>👁️ {{ number_format($post->view_count) }}</span>
                    </div>
                @endif

                @if($post->isPendingApproval())
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t border-yellow-200 dark:border-yellow-800">
                        <button wire:click="approvePost({{ $post->id }})"
                            wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold rounded-lg transition text-center">
                            ✓ Approve & Schedule
                        </button>
                        <button class="flex-1 px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold rounded-lg transition text-center">
                            ✕ Reject
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
                <p class="text-gray-400 text-sm">No posts found. Schedule your first post or deploy a Social Media Agent.</p>
            </div>
        @endforelse
    </div>

    <div class="flex justify-center">
        {{ $this->posts->links() }}
    </div>
</div>
