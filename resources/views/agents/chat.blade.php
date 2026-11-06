<x-layouts.platform>
    <x-slot:header>Agent chat</x-slot:header>
    @livewire('agents.agent-chat', ['deploymentId' => $deployment->id])
</x-layouts.platform>
