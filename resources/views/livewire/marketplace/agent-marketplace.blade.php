<div class="flex bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden min-h-[600px]">

    {{-- ── Filter Sidebar ─────────────────────────────────────────── --}}
    <aside class="w-64 flex-shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 overflow-y-auto">
        <div class="p-4 space-y-6">

            {{-- Search --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Search</h3>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search agents…" aria-label="Search agents"
                           class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 dark:text-gray-200">
                </div>
            </div>

            {{-- Sort --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Sort By</h3>
                <select wire:model.live="sortBy" aria-label="Sort agents by"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 dark:text-gray-200">
                    <option value="featured">Featured</option>
                    <option value="rating">Top Rated</option>
                    <option value="popular">Most Deployed</option>
                    <option value="trust">Highest Trust</option>
                    <option value="performance">Best Performance</option>
                    <option value="price_asc">Price: Low → High</option>
                    <option value="price_desc">Price: High → Low</option>
                </select>
            </div>

            {{-- Status --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Status</h3>
                <div class="space-y-1">
                    @foreach(['active' => 'Available', 'deployed' => 'Deployed', 'draft' => 'Draft', 'disabled' => 'Disabled'] as $val => $label)
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                        <input type="radio" wire:model.live="statusFilter" value="{{ $val }}" class="text-purple-600 focus:ring-purple-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Departments --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Department</h3>
                <div class="space-y-1">
                    <button wire:click="setDepartment('')"
                            class="w-full text-left px-3 py-2 text-sm rounded-lg transition {{ !$selectedDepartment ? 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        All Departments
                    </button>
                    @foreach($this->departments as $dept)
                    <button wire:click="setDepartment('{{ $dept->slug }}')"
                            class="w-full text-left px-3 py-2 text-sm rounded-lg transition {{ $selectedDepartment === $dept->slug ? 'bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        {{ $dept->name }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Cost Tier --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Cost</h3>
                <div class="space-y-1">
                    @foreach(['' => 'Any Cost', 'free' => 'Free', 'low' => 'Low ($1–$99)', 'medium' => 'Medium ($100–$249)', 'high' => 'High ($250+)'] as $val => $label)
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                        <input type="radio" wire:model.live="costTier" value="{{ $val }}" class="text-purple-600 focus:ring-purple-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Trust Score --}}
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                    Min. Trust Score <span class="font-bold text-purple-600">{{ $trustScoreMin }}+</span>
                </h3>
                <input type="range" wire:model.live="trustScoreMin" min="0" max="100" step="5"
                       aria-label="Minimum trust score filter" class="w-full accent-purple-600">
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>0</span><span>50</span><span>100</span>
                </div>
            </div>

            {{-- Skills --}}
            @if(count($this->availableSkills) > 0)
            <div>
                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Skill</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach(array_slice($this->availableSkills, 0, 15) as $skill)
                    <button wire:click="setSkill('{{ $skill }}')"
                            class="px-2 py-1 text-xs rounded-full border transition {{ $selectedSkill === $skill ? 'bg-purple-600 text-white border-purple-600' : 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-purple-400' }}">
                        {{ $skill }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Clear Filters --}}
            <button wire:click="clearFilters"
                    class="w-full py-2 text-sm text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 border border-gray-200 dark:border-gray-700 rounded-lg transition">
                Clear All Filters
            </button>

        </div>
    </aside>

    {{-- ── Main Content ────────────────────────────────────────────── --}}
    <main class="flex-1 overflow-y-auto p-6">

        @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-300 text-sm flex items-center gap-2" role="alert">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
        @endif

        {{-- Header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Agent Marketplace</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $this->agents->total() }} agent{{ $this->agents->total() !== 1 ? 's' : '' }} available
                    @if($search || $selectedDepartment || $costTier || $trustScoreMin > 0 || $selectedSkill)
                    <span class="ml-1 text-purple-600 dark:text-purple-400">(filtered)</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($this->categories as $cat)
                <button wire:click="$set('selectedCategory', '{{ $selectedCategory === $cat->slug ? '' : $cat->slug }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition {{ $selectedCategory === $cat->slug ? 'bg-purple-600 text-white border-purple-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-purple-400' }}">
                    {{ $cat->name }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Agent Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5" wire:loading.class="opacity-60">
            @forelse($this->agents as $agent)
            <article class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden hover:shadow-lg hover:border-purple-200 dark:hover:border-purple-800 transition-all flex flex-col"
                     aria-label="{{ $agent->name }}">

                <div class="bg-gradient-to-br from-purple-700 to-purple-900 p-4 relative">
                    <div class="absolute top-3 right-3 flex flex-col items-end gap-1">
                        @if($agent->is_featured)<span class="px-2 py-0.5 bg-yellow-400 text-yellow-900 text-xs font-bold rounded-full">⭐ Featured</span>@endif
                        @if($agent->is_beta)<span class="px-2 py-0.5 bg-blue-400 text-blue-900 text-xs font-bold rounded-full">Beta</span>@endif
                        @if($agent->is_verified)<span class="px-2 py-0.5 bg-green-400 text-green-900 text-xs font-bold rounded-full">✓ Verified</span>@endif
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-white font-bold text-lg">
                        {{ strtoupper(substr($agent->name, 0, 2)) }}
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        @if($agent->trust_score)
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $agent->trust_score >= 80 ? 'bg-green-400/90 text-green-900' : ($agent->trust_score >= 60 ? 'bg-yellow-400/90 text-yellow-900' : 'bg-red-400/90 text-red-900') }}">
                            Trust {{ $agent->trust_score }}
                        </span>
                        @endif
                        @if($agent->cost_tier)
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-white/20 text-white capitalize">
                            {{ $agent->cost_tier === 'free' ? '🆓 Free' : ucfirst($agent->cost_tier) }}
                        </span>
                        @endif
                    </div>
                </div>

                <div class="p-5 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ $agent->name }}</h3>
                            <p class="text-xs text-purple-600 dark:text-purple-400">{{ $agent->agentDepartment?->name }}</p>
                        </div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white whitespace-nowrap flex-shrink-0">{{ $agent->formatted_price }}</p>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">{{ $agent->tagline ?? $agent->description }}</p>

                    @if($agent->performance_score)
                    <div class="mb-3">
                        <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                            <span>Performance</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($agent->performance_score, 0) }}/100</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-purple-700" :style="{ width: '{{ min(100, $agent->performance_score) }}%' }"></div>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center gap-3 mb-3 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format($agent->avg_rating, 1) }}</span>
                            <span>({{ $agent->review_count }})</span>
                        </span>
                        <span class="text-gray-300 dark:text-gray-700">·</span>
                        <span>{{ number_format($agent->total_deployments) }} deploys</span>
                    </div>

                    @if($agent->skills && count($agent->skills) > 0)
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        @foreach(array_slice($agent->skills, 0, 3) as $skill)
                        <span class="px-2 py-0.5 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-xs rounded-full">{{ $skill }}</span>
                        @endforeach
                        @if(count($agent->skills) > 3)
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-500 text-xs rounded-full">+{{ count($agent->skills) - 3 }}</span>
                        @endif
                    </div>
                    @elseif($agent->capabilities && count($agent->capabilities) > 0)
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        @foreach(array_slice($agent->capabilities, 0, 3) as $cap)
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs rounded-full">{{ $cap }}</span>
                        @endforeach
                    </div>
                    @endif

                    <div class="mt-auto flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button wire:click="openPreview({{ $agent->id }})" aria-label="Preview {{ $agent->name }}"
                                class="flex-1 px-3 py-2 text-sm font-medium text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800 rounded-lg hover:bg-purple-50 dark:hover:bg-purple-900/20 transition">
                            Preview
                        </button>
                        <button wire:click="startDeploy({{ $agent->id }})" aria-label="Deploy {{ $agent->name }}"
                                class="flex-1 px-3 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition">
                            Deploy
                        </button>
                    </div>
                </div>
            </article>

            @empty
            <div class="col-span-full flex flex-col items-center justify-center py-24 text-center" role="status">
                <div class="w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No agents found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs mb-4">Try adjusting your filters or search query.</p>
                <button wire:click="clearFilters" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                    Clear Filters
                </button>
            </div>
            @endforelse
        </div>

        @if($this->agents->hasPages())
        <div class="mt-8">{{ $this->agents->links() }}</div>
        @endif

    </main>

    {{-- ── Preview Modal ───────────────────────────────────────────── --}}
    @if($previewAgent)
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="preview-title" wire:click.self="closePreview">
        <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <div class="bg-gradient-to-br from-purple-700 to-purple-900 p-6 rounded-t-2xl">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center text-white font-bold text-2xl">
                            {{ strtoupper(substr($previewAgent->name, 0, 2)) }}
                        </div>
                        <div class="text-white">
                            <h2 id="preview-title" class="text-xl font-bold">{{ $previewAgent->name }}</h2>
                            <p class="text-purple-200 text-sm">{{ $previewAgent->agentDepartment?->name }} · {{ $previewAgent->category?->name }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                @if($previewAgent->is_verified)<span class="px-2 py-0.5 bg-green-400/90 text-green-900 text-xs font-bold rounded-full">✓ Verified</span>@endif
                                @if($previewAgent->is_featured)<span class="px-2 py-0.5 bg-yellow-400/90 text-yellow-900 text-xs font-bold rounded-full">⭐ Featured</span>@endif
                                @if($previewAgent->is_beta)<span class="px-2 py-0.5 bg-blue-400/90 text-blue-900 text-xs font-bold rounded-full">Beta</span>@endif
                            </div>
                        </div>
                    </div>
                    <button wire:click="closePreview" aria-label="Close" class="text-white/60 hover:text-white transition p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $previewAgent->description }}</p>

                {{-- Metrics --}}
                <div class="grid grid-cols-4 gap-3">
                    @foreach([
                        ['Rating', number_format($previewAgent->avg_rating, 1), '/5'],
                        ['Accuracy', number_format($previewAgent->accuracy_score ?? 0, 0), '%'],
                        ['Reliability', number_format($previewAgent->reliability_score ?? 0, 0), '%'],
                        ['Performance', number_format($previewAgent->performance_score ?? 0, 0), ''],
                    ] as [$label, $val, $suffix])
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center">
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $val }}<span class="text-sm text-gray-400">{{ $suffix }}</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $label }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Governance --}}
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4">
                    <h4 class="font-semibold text-purple-900 dark:text-purple-300 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Governance &amp; Trust
                    </h4>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Trust Score</p>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex-1 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ ($previewAgent->trust_score ?? 0) >= 80 ? 'bg-green-500' : (($previewAgent->trust_score ?? 0) >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                         :style="{ width: '{{ min(100, $previewAgent->trust_score ?? 0) }}%' }"></div>
                                </div>
                                <span class="text-xs font-semibold text-gray-900 dark:text-white">{{ $previewAgent->trust_score ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Default Mode</p>
                            <p class="font-medium text-gray-900 dark:text-white capitalize mt-1">{{ str_replace(['-','_'],' ', $previewAgent->default_deployment_mode ?? 'advisory') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Trust Tier</p>
                            <p class="font-medium text-gray-900 dark:text-white mt-1">{{ ucfirst($previewAgent->trust_tier ?? 'standard') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Certification Score</p>
                            <p class="font-medium text-gray-900 dark:text-white mt-1">{{ $previewAgent->certification_score ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                @if($previewAgent->skills && count($previewAgent->skills) > 0)
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Skills</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($previewAgent->skills as $skill)
                        <span class="px-3 py-1 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-sm rounded-full">{{ $skill }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($previewAgent->competencies && count($previewAgent->competencies) > 0)
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Competencies</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($previewAgent->competencies as $comp)
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg text-sm">
                            <span class="text-gray-700 dark:text-gray-300">{{ is_array($comp) ? ($comp['area'] ?? $comp) : $comp }}</span>
                            @if(is_array($comp) && !empty($comp['level']))
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $comp['level'] === 'expert' ? 'bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300' : ($comp['level'] === 'advanced' ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400') }}">
                                {{ ucfirst($comp['level']) }}
                            </span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($previewAgent->capabilities && count($previewAgent->capabilities) > 0)
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Capabilities</h4>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                        @foreach($previewAgent->capabilities as $cap)
                        <li class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <span class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-1.5 flex-shrink-0"></span>{{ $cap }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-800">
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $previewAgent->formatted_price }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($previewAgent->total_deployments) }} active deployments</p>
                    </div>
                    <button wire:click="startDeploy({{ $previewAgent->id }})"
                            class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl transition shadow-md shadow-purple-500/20">
                        Deploy Agent →
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Deploy Modal ────────────────────────────────────────────── --}}
    @if($showDeployModal)
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="deploy-title">
        <div class="bg-white dark:bg-gray-900 rounded-2xl w-full max-w-lg shadow-2xl">

            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h3 id="deploy-title" class="text-lg font-bold text-gray-900 dark:text-white">Deploy Agent</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Configure this agent for your organization</p>
                </div>
                <button wire:click="closeDeploy" aria-label="Close deploy dialog" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 space-y-5">

                <div>
                    <label for="deploy-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Deployment Name <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input id="deploy-name" wire:model="deployForm.deployment_name" type="text"
                           placeholder="e.g. Finance Agent – Q4 Reporting"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-gray-800 dark:text-gray-200">
                    @error('deployForm.deployment_name')<p class="text-red-500 dark:text-red-400 text-xs mt-1" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deploy-mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Deployment Mode <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <select id="deploy-mode" wire:model="deployForm.deployment_mode"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 bg-white dark:bg-gray-800 dark:text-gray-200">
                        <option value="advisory">Advisory — Recommends only, no actions</option>
                        <option value="semi-autonomous">Semi-Autonomous — Actions need approval</option>
                        <option value="autonomous">Autonomous — Acts independently</option>
                        <option value="executive_approval">Executive Approval Required</option>
                    </select>
                    @error('deployForm.deployment_mode')<p class="text-red-500 dark:text-red-400 text-xs mt-1" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deploy-dept" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Department <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <select id="deploy-dept" wire:model="deployForm.department_id"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 bg-white dark:bg-gray-800 dark:text-gray-200">
                        <option value="">— No specific department —</option>
                        @foreach($this->orgDepartments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="deploy-confidence" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Confidence Threshold: <span class="font-bold text-purple-600">{{ $deployForm->confidence_threshold ?? 75 }}%</span>
                        <span class="text-xs text-gray-400 font-normal">— below this, human approval required</span>
                    </label>
                    <input id="deploy-confidence" wire:model.live="deployForm.confidence_threshold"
                           type="range" min="0" max="100" step="5"
                           aria-label="Confidence threshold" class="w-full accent-purple-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1"><span>0%</span><span>50%</span><span>100%</span></div>
                </div>

                <div>
                    <label for="deploy-instructions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Custom Instructions <span class="text-gray-400 font-normal">(Optional)</span>
                    </label>
                    <textarea id="deploy-instructions" wire:model="deployForm.custom_instructions" rows="3"
                              placeholder="Add specific instructions or constraints for this deployment…"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 bg-white dark:bg-gray-800 dark:text-gray-200 resize-none"></textarea>
                    @error('deployForm.custom_instructions')<p class="text-red-500 dark:text-red-400 text-xs mt-1" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="px-6 pb-6 flex items-center gap-3 justify-end border-t border-gray-100 dark:border-gray-800 pt-5">
                <button wire:click="closeDeploy"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    Cancel
                </button>
                <button wire:click="deploy" wire:loading.attr="disabled" wire:loading.class="opacity-75 cursor-not-allowed"
                        class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold rounded-lg transition shadow-md shadow-purple-500/20">
                    <span wire:loading.remove wire:target="deploy">Deploy Agent</span>
                    <span wire:loading wire:target="deploy" aria-live="polite">Deploying…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
