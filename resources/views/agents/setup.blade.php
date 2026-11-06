<x-layouts.platform>
    <x-slot:header>Set up {{ $deployment->name }}</x-slot:header>
    @livewire('agents.agent-setup', ['deploymentId' => $deployment->id])
</x-layouts.platform>
