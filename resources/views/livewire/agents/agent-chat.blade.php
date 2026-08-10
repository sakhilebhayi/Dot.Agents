<div class="flex h-[calc(100vh-6.5rem)] gap-4" x-data="{ showInfo: true }">
    {{-- Chat Panel --}}
    <div class="flex-1 flex flex-col bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white font-bold text-sm">
                    {{ substr($this->deployment?->agent?->name ?? 'AI', 0, 2) }}
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm">
                        {{ $this->deployment?->display_name }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ ucfirst($this->deployment?->deployment_mode) }} Mode
                        @if($this->deployment?->isAutonomous())
                            &middot; <span class="text-emerald-600 dark:text-emerald-400">Autonomous</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showInfo = !showInfo"
                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>
                <button wire:click="newSession"
                    class="p-2 text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    title="New Session">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4" id="chat-messages">
            @if($this->chatMessages->isEmpty())
                <div class="flex flex-col items-center justify-center h-full text-center py-12">
                    <div class="w-16 h-16 rounded-2xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h4 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-1">Start a conversation</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-xs">
                        Ask {{ $this->deployment?->agent?->name }} anything related to {{ strtolower($this->deployment?->agent?->agentDepartment?->name ?? 'your department') }}.
                    </p>
                </div>
            @else
                @foreach($this->chatMessages as $msg)
                    <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }} gap-3">
                        @if($msg->role !== 'user')
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 mt-1">
                                AI
                            </div>
                        @endif
                        <div class="max-w-lg">
                            <div class="{{ $msg->role === 'user' ? 'bg-purple-600 text-white rounded-2xl rounded-tr-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-2xl rounded-tl-sm' }} px-4 py-3 text-sm">
                                {!! nl2br(e($msg->content)) !!}
                            </div>
                            <div class="flex items-center gap-2 mt-1 {{ $msg->role === 'user' ? 'justify-end' : '' }}">
                                <span class="text-xs text-gray-400">{{ $msg->created_at->diffForHumans() }}</span>
                                @if($msg->token_count)
                                    <span class="text-xs text-gray-400">&middot; {{ number_format($msg->token_count) }} tokens</span>
                                @endif
                                @if($msg->flagged)
                                    <span class="text-xs text-red-500 font-medium">⚠ Flagged</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($isTyping)
                    <div wire:poll.1500ms="pollForReply" class="flex justify-start gap-3">
                        <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-700 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 mt-1">AI</div>
                        <div class="bg-gray-100 dark:bg-gray-800 rounded-2xl rounded-tl-sm px-4 py-3">
                            <div class="flex gap-1 items-center h-4">
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></div>
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></div>
                                <div class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Input --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <form wire:submit="sendMessage" class="flex gap-3 items-end">
                <div class="flex-1">
                    <textarea
                        wire:model="message"
                        rows="1"
                        placeholder="Message {{ $this->deployment?->agent?->name ?? 'Agent' }}..."
                        class="w-full resize-none rounded-xl border-0 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 text-sm px-4 py-3 focus:ring-2 focus:ring-purple-600 focus:bg-white dark:focus:bg-gray-700 transition-colors"
                        x-on:keydown.enter.prevent.exact="$wire.sendMessage()"
                        x-on:input="$el.style.height='auto'; $el.style.height=($el.scrollHeight)+'px'"
                    ></textarea>
                </div>
                <button type="submit"
                    wire:loading.attr="disabled"
                    class="flex-shrink-0 w-10 h-10 bg-purple-700 hover:bg-purple-800 disabled:opacity-50 text-white rounded-xl flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4 rotate-90" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                    </svg>
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-2 text-center">
                Confidence threshold: {{ $this->deployment?->confidence_threshold }}% &middot;
                Mode: {{ ucfirst($this->deployment?->deployment_mode) }}
            </p>
        </div>
    </div>

    {{-- Info Sidebar --}}
    <div x-show="showInfo" x-transition class="w-72 flex-shrink-0 space-y-4">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Agent Configuration</h4>
            <dl class="space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <dt class="text-gray-500">AI Model</dt>
                    <dd class="font-mono text-gray-700 dark:text-gray-300">{{ $this->deployment?->agent?->primary_model }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Department</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $this->deployment?->agent?->agentDepartment?->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Confidence Threshold</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $this->deployment?->confidence_threshold }}%</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Human Approval</dt>
                    <dd class="text-gray-700 dark:text-gray-300">
                        {{ $this->deployment?->requires_human_approval ? 'Required' : 'Not required' }}
                    </dd>
                </div>
            </dl>
        </div>

        @if($sessionId)
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-3">Session Statistics</h4>
            @php $sess = $this->session; @endphp
            <dl class="space-y-2.5 text-xs">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Messages</dt>
                    <dd class="font-semibold text-gray-700 dark:text-gray-300">{{ $sess?->message_count ?? 0 }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Tokens Used</dt>
                    <dd class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($sess?->token_count ?? 0) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Session Cost</dt>
                    <dd class="font-semibold text-gray-700 dark:text-gray-300">${{ number_format($sess?->total_cost ?? 0, 4) }}</dd>
                </div>
            </dl>
            <button wire:click="endSession"
                class="w-full mt-3 text-xs text-red-600 hover:text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-lg py-1.5 transition-colors">
                End Session
            </button>
        </div>
        @endif
    </div>
</div>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.addEventListener('livewire:updated', () => {
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
});
</script>
