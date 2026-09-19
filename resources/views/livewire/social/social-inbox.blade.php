<div class="flex h-[calc(100vh-10rem)] overflow-hidden gap-0">

    {{-- Conversation List --}}
    <div class="w-80 flex-shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 overflow-y-auto">
        {{-- Filters --}}
        <div class="p-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900 z-10">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Social Inbox</h2>
            <div class="flex flex-wrap gap-1">
                @foreach(['open' => 'Open', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'all' => 'All'] as $val => $lbl)
                    <button wire:click="$set('filter', '{{ $val }}')"
                        class="px-2.5 py-1 text-xs rounded-full transition {{ $filter === $val ? 'bg-brand-purple-mid text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                        {{ $lbl }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Conversation Items --}}
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($this->conversations as $conv)
                <button wire:click="openConversation({{ $conv->id }})"
                    class="w-full text-left flex items-start gap-3 px-4 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition {{ $activeConversationId === $conv->id ? 'bg-brand-purple-pale dark:bg-brand-purple-deeper/20 border-r-2 border-brand-purple-mid' : '' }}">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-purple-light to-brand-purple-mid flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                        {{ strtoupper(substr($conv->contact_name ?? $conv->contact_handle ?? '?', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $conv->contact_name ?? $conv->contact_handle }}</p>
                            <span class="text-xs text-gray-400 flex-shrink-0 ml-1">{{ $conv->last_message_at?->diffForHumans(short: true) }}</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate">{{ $conv->platform }} · {{ ucfirst($conv->status) }}</p>
                        @if($conv->sentiment === 'angry')
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Angry</span>
                        @elseif($conv->sentiment === 'frustrated')
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-xs bg-orange-100 text-orange-700">Frustrated</span>
                        @elseif($conv->is_lead)
                            <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Lead</span>
                        @endif
                    </div>
                </button>
            @empty
                <div class="p-8 text-center text-sm text-gray-400">No conversations</div>
            @endforelse
        </div>

        {{ $this->conversations->links() }}
    </div>

    {{-- Conversation View --}}
    <div class="flex-1 flex flex-col bg-gray-50 dark:bg-gray-950 overflow-hidden">
        @if($this->activeConversation)
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-purple-light to-brand-purple-mid flex items-center justify-center text-white text-sm font-semibold">
                        {{ strtoupper(substr($this->activeConversation->contact_name ?? '?', 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $this->activeConversation->contact_name ?? $this->activeConversation->contact_handle }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($this->activeConversation->platform) }} · {{ ucfirst($this->activeConversation->channel_type) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if(!$this->activeConversation->is_escalated)
                        <button wire:click="escalate({{ $this->activeConversation->id }})"
                            class="px-3 py-1.5 text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 rounded-lg hover:bg-orange-200 transition"
                            wire:loading.attr="disabled">
                            Escalate
                        </button>
                    @else
                        <span class="px-3 py-1.5 text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg">Escalated</span>
                    @endif
                    @if($this->activeConversation->intent_score > 0)
                        <div class="text-right">
                            <p class="text-xs text-gray-500">Intent</p>
                            <p class="text-sm font-bold text-green-600">{{ round($this->activeConversation->intent_score) }}%</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                @foreach($this->activeConversation->messages as $msg)
                    <div class="flex {{ $msg->isOutbound() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%]">
                            <div class="rounded-2xl px-4 py-2.5 {{ $msg->isOutbound() ? 'bg-brand-purple-mid text-white' : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700' }}">
                                <p class="text-sm leading-relaxed">{{ $msg->content }}</p>
                            </div>
                            <div class="flex items-center gap-2 mt-1 {{ $msg->isOutbound() ? 'justify-end' : '' }}">
                                <p class="text-xs text-gray-400">{{ $msg->created_at->format('H:i') }}</p>
                                @if($msg->is_ai_generated)
                                    <span class="text-xs text-brand-purple-light">AI</span>
                                    @if($msg->was_disclosed_as_ai)
                                        <span class="text-xs text-gray-400" title="AI disclosure included">✓ Disclosed</span>
                                    @endif
                                @endif
                                @if($msg->isPendingApproval())
                                    <span class="px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Pending Approval</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Reply Box --}}
            <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-end gap-3">
                    <textarea wire:model="replyContent"
                        rows="2"
                        placeholder="Type a reply..."
                        class="flex-1 resize-none rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white px-4 py-3 focus:ring-2 focus:ring-brand-purple-light focus:border-transparent transition"
                        wire:keydown.cmd.enter="sendReply"></textarea>
                    <button wire:click="sendReply"
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendReply">Send</span>
                        <span wire:loading wire:target="sendReply">Sending…</span>
                    </button>
                </div>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Select a conversation to view messages</p>
                </div>
            </div>
        @endif
    </div>
</div>
