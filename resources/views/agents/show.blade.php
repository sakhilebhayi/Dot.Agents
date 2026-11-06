<x-layouts.platform>
    <x-slot:header>{{ $deployment->name }}</x-slot:header>
    @livewire('agents.deployment-overview', ['deploymentId' => $deployment->id])
</x-layouts.platform>
