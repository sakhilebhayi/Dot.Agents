<x-layouts.platform>
    <x-slot:header>Test {{ $deployment->name }}</x-slot:header>
    @livewire('agents.agent-test-bench', ['deploymentId' => $deployment->id])
</x-layouts.platform>
