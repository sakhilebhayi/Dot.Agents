<x-layouts.platform>
    <x-slot:header>{{ $workflow->name }}</x-slot:header>
    <livewire:workflows.workflow-builder :workflow-id="$workflow->id" />
</x-layouts.platform>
