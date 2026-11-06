@php
    $canManage = auth()->user()->can('update', $this->organization);
    $actorIsOwner = auth()->user()->roleInOrganization($this->organization->id) === \App\Support\OrgRoles::OWNER;
    // Only an owner can make another owner, so nobody else is offered it.
    $roles = $actorIsOwner ? \App\Support\OrgRoles::LABELS : \Illuminate\Support\Arr::except(\App\Support\OrgRoles::LABELS, \App\Support\OrgRoles::OWNER);
@endphp

<x-sig.page title="People" sub="Who can work in this business, and what each person may do.">
    <x-slot:actions>
        @writable
            @if($canManage && ! $showInviteForm)
                <button type="button" class="btn primary" wire:click="$set('showInviteForm', true)"><x-sig.icon name="plus" />Invite a person</button>
            @endif
        @endwritable
    </x-slot:actions>

    @if(session('member_success'))
        <div class="notice clear" role="status">{{ session('member_success') }}</div>
    @endif
    @if(session('member_error'))
        <div class="notice stop" role="alert">{{ session('member_error') }}</div>
    @endif
    @if(session('member_link'))
        <div class="notice hold" role="alert">
            <div>
                <p>The email could not be sent. Give them this link yourself. It will not be shown again.</p>
                <code class="org-token">{{ session('member_link') }}</code>
            </div>
        </div>
    @endif

    <div class="grid two">
        <div class="grid org-stack">
            @if($showInviteForm)
                @writable
                    <form class="card" wire:submit="invite">
                        <div class="card-head"><h2>Invite a person</h2></div>
                        <div class="card-body">
                            <label class="field">
                                <span>Email address</span>
                                <input class="input" type="email" wire:model="inviteEmail" placeholder="colleague@company.com" autocomplete="off">
                                <small>We email them a link. They join when they accept it, with the account that uses this address.</small>
                                @error('inviteEmail')<p class="error">{{ $message }}</p>@enderror
                            </label>

                            <fieldset class="flow-after">
                                <legend class="label">Role</legend>
                                @foreach($roles as $value => $label)
                                    <label class="row check bare" wire:key="invite-role-{{ $value }}">
                                        <input type="radio" value="{{ $value }}" wire:model="inviteRole">
                                        <span class="row-main"><b>{{ $label }}</b><span>{{ \App\Support\OrgRoles::DESCRIPTIONS[$value] }}</span></span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <div class="answer-main">
                                <button type="submit" class="btn primary" wire:loading.attr="disabled">Send invitation</button>
                                <button type="button" class="btn quiet" wire:click="$set('showInviteForm', false)">Cancel</button>
                            </div>
                        </div>
                    </form>
                @endwritable
            @endif

            @if($this->waiting->isNotEmpty())
                <section class="card" aria-label="Invitations waiting">
                    <div class="card-head"><h2>Invitations waiting</h2></div>
                    @foreach($this->waiting as $invitation)
                        <div class="row org-row" wire:key="invitation-{{ $invitation->id }}">
                            <div class="row-main">
                                <b>{{ $invitation->email }}</b>
                                <span>{{ \App\Support\OrgRoles::LABELS[$invitation->role] ?? 'Member' }}, until <time class="num">{{ $invitation->expires_at->format('d M Y') }}</time></span>
                            </div>
                            @if($canManage)
                                @writable
                                    <button type="button" class="btn quiet small" aria-label="Cancel the invitation for {{ $invitation->email }}" wire:click="cancelInvitation({{ $invitation->id }})">Cancel</button>
                                @endwritable
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="card" aria-label="People in this business">
                @forelse($this->members as $member)
                    @php
                        $role = \App\Support\OrgRoles::normalise($member->pivot->role) ?? \App\Support\OrgRoles::VIEWER;
                        $isOwner = $member->id === $this->organization->owner_id;
                        $isSelf = auth()->id() === $member->id;
                        $changeable = $canManage && ! $isOwner && ! $isSelf && ($role !== \App\Support\OrgRoles::OWNER || $actorIsOwner);
                        $roleLabel = \App\Support\OrgRoles::LABELS[$isOwner ? \App\Support\OrgRoles::OWNER : $role];
                    @endphp
                    <div class="row org-row" wire:key="member-{{ $member->id }}">
                        @if($member->profile_photo_path)
                            <img class="org-face" src="{{ $member->profile_photo_url }}" alt="">
                        @endif
                        <div class="row-main">
                            <b>{{ $member->name }}</b>
                            <span>{{ $member->email }}</span>
                            <span>Joined <time class="num">{{ $member->pivot->joined_at ? \Carbon\Carbon::parse($member->pivot->joined_at)->format('d M Y') : '-' }}</time></span>
                        </div>
                        <div class="org-role">
                            @if($changeable)
                                @writable
                                    <select class="select" aria-label="Role for {{ $member->name }}" title="{{ \App\Support\OrgRoles::DESCRIPTIONS[$role] }}" wire:change="updateRole({{ $member->id }}, $event.target.value)">
                                        @foreach($roles as $value => $label)
                                            <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="chip">{{ $roleLabel }}</span>
                                @endwritable
                            @else
                                <span class="chip">{{ $roleLabel }}</span>
                            @endif
                        </div>
                        @if($canManage)
                            <div class="org-end org-slot">
                                @if(! $isOwner && ! $isSelf)
                                    @writable
                                        <button type="button" class="btn quiet small" aria-label="Remove {{ $member->name }}" wire:click="confirmRemove({{ $member->id }})">Remove</button>
                                    @endwritable
                                @endif
                            </div>
                        @endif
                        @if($removingUserId === $member->id)
                            <div class="org-confirm" role="group" aria-label="Confirm removing {{ $member->name }}">
                                <p>Remove {{ $member->name }} from this business?</p>
                                <div class="answer-main">
                                    <button type="button" class="btn danger small" wire:click="removeMember" wire:loading.attr="disabled">Remove</button>
                                    <button type="button" class="btn quiet small" wire:click="$set('removingUserId', null)">Cancel</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty"><b>No people yet.</b></div>
                @endforelse
            </section>
        </div>

        <aside class="card" aria-label="What each role can do">
            <div class="card-head"><h2>What each role can do</h2></div>
            <div class="card-body">
                <dl class="defs">
                    @foreach(\App\Support\OrgRoles::LABELS as $value => $label)
                        <dt>{{ $label }}</dt>
                        <dd>{{ \App\Support\OrgRoles::DESCRIPTIONS[$value] }}</dd>
                    @endforeach
                </dl>
            </div>
        </aside>
    </div>
</x-sig.page>
