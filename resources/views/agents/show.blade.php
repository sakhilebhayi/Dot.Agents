<x-layouts.platform>
    <x-slot:header>Agent Overview</x-slot:header>
    @livewire('agents.deployment-overview', ['deploymentId' => $deployment->id])
</x-layouts.platform>
