<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Members</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage who has access to your organization.</p>
        </div>
        <button wire:click="$set('showInviteForm', true)"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Member
        </button>
    </div>

    @if(session('member_success'))
    <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('member_success') }}
    </div>
    @endif

    @if(session('member_error'))
    <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm">
        {{ session('member_error') }}
    </div>
    @endif

    {{-- Invite Form --}}
    @if($showInviteForm)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-brand-purple-pale dark:border-brand-purple-dark p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Invite a Member</h3>
        <form wire:submit="invite" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email Address</label>
                <input wire:model="inviteEmail" type="email" placeholder="colleague@company.com"
                    class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                @error('inviteEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">Role</label>
                <select wire:model="inviteRole" class="w-full text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-brand-purple-mid">
                    <option value="viewer">Viewer</option>
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                    <option value="owner">Owner</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-brand-purple-mid hover:bg-brand-purple disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">
                    Add Member
                </button>
                <button type="button" wire:click="$set('showInviteForm', false)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Remove Confirmation --}}
    @if($removingUserId)
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-red-200 dark:border-red-800 p-5 flex items-center justify-between gap-4">
        <p class="text-sm text-gray-700 dark:text-gray-300">Are you sure you want to remove this member?</p>
        <div class="flex gap-2">
            <button wire:click="removeMember" wire:loading.attr="disabled" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-75 text-white text-sm font-medium rounded-xl transition-colors">Remove</button>
            <button wire:click="$set('removingUserId', null)" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl transition-colors">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Members Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Member</th>
                    <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                    <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Joined</th>
                    <th class="px-6 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->members as $member)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <img src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}" class="w-8 h-8 rounded-full object-cover">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $member->name }}</p>
                                <p class="text-xs text-gray-500">{{ $member->email }}</p>
                            </div>
                            @if($member->id === $this->organization->owner_id)
                                <span class="px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs rounded-full font-medium">Owner</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($member->id !== $this->organization->owner_id && auth()->id() !== $member->id)
                            <select wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                class="text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid py-1">
                                @foreach(['viewer','member','admin','owner'] as $r)
                                    <option value="{{ $r }}" {{ $member->pivot->role === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs rounded-lg">{{ ucfirst($member->pivot->role ?? 'member') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-xs">
                        {{ $member->pivot->joined_at ? \Carbon\Carbon::parse($member->pivot->joined_at)->format('d M Y') : '—' }}
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($member->id !== $this->organization->owner_id && $member->id !== auth()->id())
                            <button wire:click="confirmRemove({{ $member->id }})"
                                class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors">
                                Remove
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No members found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
