@use('Illuminate\Support\Str')
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Meridian" details="{{ $stats['status'] }} · {{ number_format($stats['jobsPerMinute'], 1) }} jobs/min">
        <x-slot:icon>
            <x-pulse::icons.queue-list />
        </x-slot:icon>
        <x-slot:actions>
            <a href="{{ $url }}" class="text-xs font-medium text-gray-600 dark:text-gray-400 hover:underline">Open Meridian →</a>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
            <strong class="text-gray-700 dark:text-gray-300">{{ number_format($stats['pendingJobs']) }}</strong> pending
            · <strong class="text-gray-700 dark:text-gray-300">{{ number_format($stats['processing']) }}</strong> processing
            · <strong class="text-gray-700 dark:text-gray-300">{{ number_format($stats['failedJobs']) }}</strong> failed (7 days)
            · <strong class="text-gray-700 dark:text-gray-300">{{ $stats['processes'] }}</strong> workers
        </p>

        @if ($queues->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Queue</x-pulse::th>
                        <x-pulse::th class="text-right">Pending</x-pulse::th>
                        <x-pulse::th class="text-right">Processes</x-pulse::th>
                        <x-pulse::th class="text-right">Wait</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($queues as $queue)
                        <tr wire:key="meridian-queue-{{ $queue['connection'] }}-{{ $queue['queue'] }}" class="h-2 first:h-0"></tr>
                        <tr wire:key="meridian-queue-row-{{ $queue['connection'] }}-{{ $queue['queue'] }}">
                            <x-pulse::td><code class="text-xs text-gray-900 dark:text-gray-100">{{ $queue['connection'] }}:{{ $queue['queue'] }}</code></x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">{{ number_format($queue['pending']) }}</x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ $queue['processes'] }}</x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ $queue['wait'] }} s</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif

        @if ($errors !== [])
            <x-pulse::table class="mt-4">
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Error</x-pulse::th>
                        <x-pulse::th class="text-right">Failed jobs</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($errors as $error)
                        <tr wire:key="meridian-error-{{ $error['fingerprint'] }}" class="h-2 first:h-0"></tr>
                        <tr wire:key="meridian-error-row-{{ $error['fingerprint'] }}">
                            <x-pulse::td class="max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $error['class'] }}: {{ $error['message'] }}">{{ class_basename($error['class']) }}: {{ Str::limit($error['message'], 120) }}</code>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">{{ number_format($error['count']) }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
