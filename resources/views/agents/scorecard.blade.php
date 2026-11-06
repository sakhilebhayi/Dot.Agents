<x-layouts.platform>
    <x-slot:header>Agent scorecard</x-slot:header>
    @livewire('agents.scorecard-viewer', ['deploymentId' => $deployment->id])
</x-layouts.platform>
