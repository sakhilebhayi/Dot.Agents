@php
    $agent = $this->deployment;
    $level = \App\Services\Agents\AgentConfig::AUTONOMY[(int) $agent->autonomy_level] ?? null;
    $thread = $this->chatMessages;
    $lastReply = $thread->where('role', 'assistant')->last();
    $department = $agent->department?->name ?? $agent->agent?->agentDepartment?->name;
@endphp

<div x-data="{ showInfo: window.matchMedia('(min-width: 961px)').matches, alert: '' }" x-on:security-alert.window="alert = $event.detail.message">
    <x-sig.page :title="$agent->display_name" :sub="$agent->role ?: $agent->agent?->name">
        <x-slot:actions>
            <button type="button" class="btn quiet" :aria-expanded="showInfo" @click="showInfo = !showInfo">Details</button>
            <button type="button" class="btn" wire:click="newSession"><x-sig.icon name="plus" />New conversation</button>
        </x-slot:actions>

        <div class="chat">
            <section class="card chat-panel" aria-label="Conversation">
                <div class="chat-log" id="chat-messages" role="log" aria-live="polite"
                    x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true })">
                    @if($thread->isEmpty())
                        <div class="empty chat-empty">
                            <b>Start a conversation</b>
                            Ask {{ $agent->display_name }} anything related to {{ strtolower($department ?? 'your department') }}.
                        </div>
                    @else
                        @foreach($thread as $msg)
                            <div @class(['msg', 'person' => $msg->role === 'user']) wire:key="message-{{ $msg->id }}">
                                <p class="msg-meta">
                                    <b>{{ $msg->role === 'user' ? 'You' : $agent->display_name }}</b>
                                    <time datetime="{{ $msg->created_at->toIso8601String() }}">{{ $msg->created_at->diffForHumans() }}</time>
                                    @if($msg->token_count)
                                        <span><span class="num">{{ number_format($msg->token_count) }}</span> tokens</span>
                                    @endif
                                    @if($msg->flagged)
                                        <span class="chip stop">Flagged</span>
                                    @endif
                                </p>
                                <div class="msg-text">{!! nl2br(e($msg->content)) !!}</div>
                            </div>
                        @endforeach

                        @if($waitingForPerson)
                            <p wire:poll.5s="pollForReply" class="notice hold chat-held" role="status">
                                This agent has stopped and is waiting for a person. <a href="{{ route('governance.approvals') }}">See what it is asking</a>
                            </p>
                        @elseif($isTyping)
                            <div wire:poll.1500ms="pollForReply" class="chat-working" role="status">
                                <span class="lamp running"><i aria-hidden="true"></i>Working on a reply</span>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="chat-compose">
                    <div class="notice stop chat-alert" role="alert" x-show="alert" x-text="alert" x-cloak></div>
                    <form wire:submit="sendMessage" class="chat-form" @submit="alert = ''">
                        <textarea class="textarea" aria-label="Message"
                            wire:model="message"
                            rows="1"
                            placeholder="Message {{ $agent->display_name }}"
                            x-on:keydown.enter.prevent.exact="alert = ''; $wire.sendMessage(); $el.style.height = 'auto'"
                            x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                        ></textarea>
                        <button type="submit" class="btn primary" wire:loading.attr="disabled" wire:target="sendMessage">Send</button>
                    </form>
                    @error('message')<p class="error">{{ $message }}</p>@enderror
                    <p class="hint">Enter sends. Shift and Enter starts a new line.</p>
                </div>
            </section>

            <aside class="chat-side" x-show="showInfo" x-cloak aria-label="About this conversation">
                <section class="card" aria-label="This agent">
                    <div class="card-head"><h2>This agent</h2></div>
                    <div class="card-body">
                        <dl class="defs">
                            <dt>Autonomy</dt>
                            <dd>{{ $level ? "{$level[0]}. {$level[1]}" : 'Not set' }}</dd>
                            <dt>Department</dt>
                            <dd>{{ $department ?? 'None' }}</dd>
                            <dt>Model used</dt>
                            <dd @class(['mono' => $lastReply?->model_used])>{{ $lastReply?->model_used ?? 'No reply yet' }}</dd>
                        </dl>
                    </div>
                </section>

                @if($sessionId)
                    @php
                        $sess = $this->session;
                        $cost = max((float) $sess?->cost, (float) $thread->sum('cost'));
                    @endphp
                    <section class="card" aria-label="This conversation">
                        <div class="card-head"><h2>This conversation</h2></div>
                        <div class="row"><div class="row-main">Messages</div><span class="row-end num">{{ number_format($thread->count()) }}</span></div>
                        <div class="row"><div class="row-main">Tokens used</div><span class="row-end num">{{ number_format($sess?->token_count ?? 0) }}</span></div>
                        <div class="row">
                            <div class="row-main">Cost</div>
                            @if($cost > 0)
                                <span class="row-end num">{{ number_format($cost, 4) }}</span>
                            @else
                                <span class="row-end">{{ $lastReply ? 'No price on file' : 'Nothing yet' }}</span>
                            @endif
                        </div>
                        <div class="card-body chat-end">
                            <button type="button" class="btn danger small" wire:click="endSession">End conversation</button>
                        </div>
                    </section>
                @endif
            </aside>
        </div>
    </x-sig.page>
</div>
