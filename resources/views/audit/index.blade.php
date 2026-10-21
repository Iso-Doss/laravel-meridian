@extends('meridian::layout', ['title' => $t('Audit log'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')
@use('Illuminate\Support\Carbon')

@section('content')
    <h1>{{ $t('Audit log') }}</h1>

    @unless ($ready)
        <div class="flash error">{{ $t('Run the migrations of Meridian to record the audit log (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).') }}</div>
    @endunless

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $t('Who did what, and when') }}</h2>
                <span class="muted">{{ $t('Actions taken from the dashboard and the Artisan commands of Meridian.') }}</span>
            </div>
            <form method="get" class="filters">
                <select name="action" data-autosubmit aria-label="{{ $t('Filter by action') }}">
                    <option value="">{{ $t('All actions') }}</option>
                    @foreach ($actions as $value => $label)
                        <option value="{{ $value }}" @selected($action === $value)>{{ $t($label) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Date') }}</th><th>{{ $t('User') }}</th><th>{{ $t('Action') }}</th><th>{{ $t('Subject') }}</th><th>{{ $t('Details') }}</th></tr></thead>
                <tbody>
                @forelse ($entries ?? [] as $entry)
                    <tr>
                        <td title="{{ Format::date(Carbon::createFromTimestamp($entry['created_at']), $locale) }}">{{ Format::ago(Carbon::createFromTimestamp($entry['created_at']), $locale) }}</td>
                        <td>
                            @if ($entry['user'] === 'console')<span class="tag">{{ $t('Console') }}</span>@else{{ $entry['user'] ?? $t('Guest') }}@endif
                            @if ($entry['ip'])<div class="muted">{{ $entry['ip'] }}</div>@endif
                        </td>
                        <td><strong>{{ $t($entry['label']) }}</strong></td>
                        <td class="mono">{{ $entry['subject'] ?? '—' }}</td>
                        <td class="wrap">@foreach ($entry['details'] as $key => $value)<span class="tag">{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>@endforeach</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ $t('No action recorded yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries)
            @include('meridian::partials.pagination', ['paginator' => $entries])
        @endif
    </div>
@endsection
