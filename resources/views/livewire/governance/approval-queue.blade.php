<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-wrap gap-3 items-center">
        <select wire:model.live="filterStatus"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="escalated">Escalated</option>
        </select>
        <select wire:model.live="filterRisk"
            class="text-sm rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid">
            <option value="">All Risk Levels</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
        <div class="ml-auto text-sm text-gray-500 dark:text-gray-400">
            {{ $this->approvals->total() }} approval requests
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Queue List --}}
        <div class="lg:col-span-2 space-y-3">
            @forelse($this->approvals as $approval)
                @php
                    $riskColors = ['critical' => 'red', 'high' => 'orange', 'medium' => 'yellow', 'low' => 'green'];
                    $color = $riskColors[$approval->risk_level] ?? 'gray';
                @endphp
                <div wire:click="selectApproval({{ $approval->id }})"
                    class="bg-white dark:bg-gray-900 rounded-2xl border {{ $selectedApproval?->id === $approval->id ? 'border-brand-purple-light ring-2 ring-brand-purple-pale dark:ring-brand-purple-dark' : 'border-gray-200 dark:border-gray-700' }} p-5 cursor-pointer hover:border-brand-purple-light dark:hover:border-brand-purple-mid transition-all">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700 dark:bg-{{ $color }}-900/30 dark:text-{{ $color }}-400">
                                    {{ ucfirst($approval->risk_level) }} Risk
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $approval->status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' :
                                      ($approval->status === 'approved' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                                      ($approval->status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                                      'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400')) }}">
                                    {{ ucfirst($approval->status) }}
                                </span>
                                @if($approval->isExpired())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">Expired</span>
                                @endif
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">
                                {{ $approval->task?->title ?? 'Agent Action Approval' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ is_array($approval->proposed_action) ? ($approval->proposed_action['description'] ?? json_encode($approval->proposed_action)) : $approval->proposed_action }}
                            </p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs text-gray-400">{{ $approval->deployment?->display_name }}</span>
                                <span class="text-xs text-gray-400">&middot;</span>
                                <span class="text-xs text-gray-400">{{ $approval->created_at->diffForHumans() }}</span>
                                @if($approval->expires_at && $approval->status === 'pending')
                                    <span class="text-xs {{ $approval->isExpired() ? 'text-red-500' : 'text-orange-500' }}">
                                        {{ $approval->isExpired() ? 'Expired' : 'Expires ' . $approval->expires_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs font-mono bg-gray-100 dark:bg-gray-800 rounded-lg px-2 py-1 text-gray-600 dark:text-gray-400">
                                #{{ $approval->id }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">All clear!</p>
                    <p class="text-xs text-gray-400 mt-1">No approvals matching your filters.</p>
                </div>
            @endforelse

            <div class="mt-4">
                {{ $this->approvals->links() }}
            </div>
        </div>

        {{-- Detail Panel --}}
        <div class="space-y-4">
            @if($selectedApproval)
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-4">Review Approval</h3>

                    <div class="space-y-3 mb-4 text-xs">
                        <div>
                            <dt class="text-gray-500 mb-0.5">Agent</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ $selectedApproval->deployment?->display_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 mb-0.5">Approval Type</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">{{ ucwords(str_replace('_', ' ', $selectedApproval->approval_type ?? 'action')) }}</dd>
                        </div>
                        @if($selectedApproval->confidence_score)
                        <div>
                            <dt class="text-gray-500 mb-0.5">AI Confidence</dt>
                            <dd class="font-medium {{ $selectedApproval->confidence_score < 75 ? 'text-orange-600' : 'text-emerald-600' }}">
                                {{ round($selectedApproval->confidence_score, 1) }}%
                            </dd>
                        </div>
                        @endif
                    </div>

                    @if($selectedApproval->proposed_action && is_array($selectedApproval->proposed_action))
                    <div class="mb-4">
                        <p class="text-xs text-gray-500 mb-1">Proposed Action</p>
                        <pre class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-xs overflow-auto max-h-32 text-gray-700 dark:text-gray-300">{{ json_encode($selectedApproval->proposed_action, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif

                    @if($selectedApproval->status === 'pending' && !$selectedApproval->isExpired())
                        <div class="mb-4">
                            <label class="block text-xs text-gray-500 mb-1">Reviewer Notes</label>
                            <textarea wire:model="reviewerNotes" rows="3"
                                placeholder="Add notes or reasoning..."
                                class="w-full text-xs rounded-lg border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 focus:ring-brand-purple-mid"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="approve" wire:loading.attr="disabled"
                                class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors">
                                ✓ Approve
                            </button>
                            <button wire:click="reject" wire:loading.attr="disabled"
                                class="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition-colors">
                                ✕ Reject
                            </button>
                        </div>
                        <button wire:click="escalate" wire:loading.attr="disabled"
                            class="w-full mt-2 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-medium rounded-lg transition-colors">
                            ↑ Escalate
                        </button>
                    @else
                        <div class="text-center py-3 text-xs text-gray-400">
                            {{ $selectedApproval->isExpired() ? 'This approval request has expired.' : 'This request has already been ' . $selectedApproval->status . '.' }}
                        </div>
                    @endif
                </div>

                @if($selectedApproval->impact_assessment)
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-5">
                    <h4 class="font-semibold text-gray-900 dark:text-white text-xs mb-3">Impact Assessment</h4>
                    <pre class="text-xs text-gray-600 dark:text-gray-400 overflow-auto max-h-24">{{ json_encode($selectedApproval->impact_assessment, JSON_PRETTY_PRINT) }}</pre>
                </div>
                @endif
            @else
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                    <p class="text-sm text-gray-400">Select an approval request to review it.</p>
                </div>
            @endif
        </div>
    </div>
</div>
