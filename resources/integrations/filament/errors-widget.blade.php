<x-filament-widgets::widget>
    <x-filament::section heading="Errors of the failed jobs" description="Grouped by cause, by Meridian">
        <x-slot name="afterHeader">
            <x-filament::link :href="$url" target="_blank" size="sm">Open Meridian</x-filament::link>
        </x-slot>

        @if ($groups === [])
            <p style="font-size: .875rem; opacity: .7">No failed job.</p>
        @else
            <div style="display: grid; gap: .75rem">
                @foreach ($groups as $group)
                    <div style="display: flex; justify-content: space-between; gap: 1rem; align-items: center">
                        <div style="min-width: 0">
                            <x-filament::link :href="$group['url'] ?? $url" target="_blank" weight="semibold">{{ class_basename($group['class']) }}</x-filament::link>
                            <div style="font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap" title="{{ $group['message'] }}">{{ $group['message'] }}</div>
                            <div style="font-size: .75rem; opacity: .7">{{ implode(', ', array_map('class_basename', $group['jobs'])) }}</div>
                        </div>
                        <x-filament::badge color="danger">{{ number_format($group['count']) }}</x-filament::badge>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
