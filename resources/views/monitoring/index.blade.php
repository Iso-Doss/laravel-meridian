@extends('meridian::layout', ['title' => $t('Monitoring'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>{{ $t('Monitoring') }}</h1>

    @unless ($ready)
        <div class="flash error">{{ $t('Run the migrations of Meridian to monitor tags (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).') }}</div>
    @endunless

    @if ($canManage)
    <div class="panel">
        <div class="panel-head">
            <h2>{{ $t('Monitor a tag') }}</h2>
            <span class="muted">{{ $t('The jobs carrying a monitored tag are listed here and kept longer (trim.monitored).') }}</span>
        </div>
        <div class="panel-body">
            <form method="post" action="{{ route('meridian.monitoring.store') }}" class="filters">
                @csrf
                <input type="search" name="tag" class="mono" placeholder="App\Models\User:42" aria-label="{{ $t('Tag to monitor') }}" required @disabled(! $ready)>
                <button class="btn primary" @disabled(! $ready)>{{ $t('Monitor') }}</button>
            </form>
            @error('tag')<p class="muted">{{ $message }}</p>@enderror
        </div>
    </div>
    @endif

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Tag') }}</th><th class="num">{{ $t('Jobs') }}</th><th class="num">{{ $t('Pending') }}</th><th class="num">{{ $t('Failed') }}</th>@if ($canManage)<th class="num">{{ $t('Actions') }}</th>@endif</tr></thead>
                <tbody>
                @forelse ($tags as $tag)
                    <tr>
                        <td><a class="mono" href="{{ route('meridian.jobs.index', ['tag' => $tag['tag']]) }}">{{ $tag['tag'] }}</a></td>
                        <td class="num">{{ Format::integer($tag['jobs'], $locale) }}</td>
                        <td class="num">{{ Format::integer($tag['pending'], $locale) }}</td>
                        <td class="num">{{ Format::integer($tag['failed'], $locale) }}</td>
                        @if ($canManage)
                        <td class="num">
                            <form class="inline" method="post" action="{{ route('meridian.monitoring.destroy') }}" data-confirm="{{ $t('Stop monitoring :tag?', ['tag' => $tag['tag']]) }}">
                                @csrf @method('DELETE')
                                <input type="hidden" name="tag" value="{{ $tag['tag'] }}">
                                <button class="btn danger">{{ $t('Stop monitoring') }}</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManage ? 5 : 4 }}" class="empty">{{ $t('No tag is monitored.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
