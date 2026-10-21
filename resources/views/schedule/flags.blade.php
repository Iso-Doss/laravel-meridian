<code class="tag">{{ $task['expression'] }}</code>
@if ($task['repeat_seconds'])<span class="muted">· {{ $t('every :seconds s', ['seconds' => $task['repeat_seconds']]) }}</span>@endif
@if ($task['background'])<span class="muted">· {{ $t('in background') }}</span>@endif
@if ($task['without_overlapping'])<span class="muted">· {{ $t('without overlapping') }}</span>@endif
@if ($task['on_one_server'])<span class="muted">· {{ $t('on one server') }}</span>@endif
@if ($task['timezone'])<span class="muted">· {{ $task['timezone'] }}</span>@endif
