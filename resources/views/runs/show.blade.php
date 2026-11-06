<x-layouts.platform>
    <x-slot:header>Run</x-slot:header>
    @livewire('runs.run-detail', ['runId' => $run->id])
</x-layouts.platform>
