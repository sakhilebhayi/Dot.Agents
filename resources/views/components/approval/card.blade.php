@props(['approval', 'reviewers', 'changing' => false])

@php
    $card = $approval->proposed_action ?? [];
    $isAction = isset($card['tool']);
    $canReview = auth()->user()->can('review', $approval) && $approval->status === 'pending';
@endphp

<article class="card desk-card" aria-labelledby="approval-title">
    <div class="card-head">
        <button type="button" class="btn quiet small desk-back" wire:click="closeApproval">Back</button>
        <x-sig.lamp :state="$approval->status === 'pending' ? 'waiting_approval' : $approval->status" />
        <x-approval.risk :level="$approval->risk_level" />
    </div>

    <div class="card-body plate">
        <header>
            <h2 id="approval-title">{{ $approval->title }}</h2>
            <p class="muted">{{ $card['agent'] ?? $approval->deployment?->name }}@if($approval->status === 'pending' && $approval->expires_at), answer by {{ $approval->expires_at->format('j M, H:i') }}@endif</p>
        </header>

        @if($isAction)
            <section aria-label="Exact values">
                <h3>{{ $changing ? 'Change the values' : 'It will use exactly this' }}</h3>
                <dl class="pairs">
                    @forelse($card['values'] as $name => $value)
                        <div>
                            <dt>{{ str_replace('_', ' ', $name) }}</dt>
                            @if($changing && is_scalar($value))
                                <dd><textarea class="textarea value-edit" rows="1" aria-label="{{ str_replace('_', ' ', $name) }}" wire:model="values.{{ $name }}"></textarea></dd>
                            @else
                                <dd>{{ is_scalar($value) ? $value : json_encode($value) }}@if($name === 'user_id' && filled($card['person'] ?? null)) ({{ $card['person'] }})@endif</dd>
                            @endif
                        </div>
                    @empty
                        <div><dd class="muted">No values.</dd></div>
                    @endforelse
                </dl>
                @error('values')<p class="error">{{ $message }}</p>@enderror
            </section>

            <section aria-label="What this means">
                <h3>What this means</h3>
                <dl class="pairs wide">
                    <div><dt>Result</dt><dd>{{ $card['consequence'] }}</dd></div>
                    <div><dt>Reaches</dt><dd>{{ $card['permissions'] }}</dd></div>
                    <div><dt>Stopped because</dt><dd>{{ $card['held_because'] }}</dd></div>
                    <div><dt>If you say no</dt><dd>{{ $card['if_rejected'] }}</dd></div>
                </dl>
            </section>

            @if($card['outside_content'] || $card['agent_says'])
                <section aria-label="What the agent said">
                    @if($card['outside_content'])
                        <p class="notice hold">This work read content from outside the business. Anything the agent says may have been put there by someone else.</p>
                    @endif
                    @if($card['agent_says'])
                        <h3>The agent's own words</h3>
                        <blockquote class="said">{{ $card['agent_says'] }}</blockquote>
                    @endif
                </section>
            @endif
        @else
            <p>{{ $approval->description }}</p>
        @endif

        @if($canReview)
            <section aria-label="Your answer" class="answer">
                @error('approval')<p class="notice stop" role="alert">{{ $message }}</p>@enderror
                <label class="field">
                    <span>Note</span>
                    <textarea class="textarea" rows="2" wire:model="responseForm.notes" placeholder="The agent reads this if you say no or ask for changes."></textarea>
                    @error('responseForm.rejection_reason')<p class="error">Say why, so the agent knows what to do next.</p>@enderror
                    @error('notes')<p class="error">{{ $message }}</p>@enderror
                </label>

                <div class="answer-main">
                    <button type="button" class="btn primary" wire:click="approve" wire:loading.attr="disabled">{{ $changing ? 'Approve with these values' : 'Approve' }}</button>
                    <button type="button" class="btn danger" wire:click="reject" wire:loading.attr="disabled">Reject</button>
                    <button type="button" class="btn" wire:click="requestChanges" wire:loading.attr="disabled">Ask for changes</button>
                    @if($isAction && ! $changing)
                        <button type="button" class="btn quiet" wire:click="$set('changing', true)">Change the values</button>
                    @endif
                </div>

                <details class="answer-more">
                    <summary>Pass this on</summary>
                    <div class="answer-pass">
                        <button type="button" class="btn" wire:click="escalate">Send to the owner</button>
                        @if($reviewers->isNotEmpty())
                            <select class="select" aria-label="Hand to" wire:model="delegateTo">
                                <option value="">Choose a person</option>
                                @foreach($reviewers as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn" wire:click="delegate">Hand over</button>
                        @endif
                    </div>
                </details>
            </section>
        @endif

        @if($approval->events->isNotEmpty())
            <section aria-label="History">
                <h3>History</h3>
                <ol class="history">
                    @foreach($approval->events as $event)
                        <li>
                            <time class="num" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('j M H:i') }}</time>
                            <span>{{ $event->user?->name ?? 'The platform' }}: {{ str_replace('_', ' ', $event->action) }}@if($event->note). {{ $event->note }}@endif</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</article>
