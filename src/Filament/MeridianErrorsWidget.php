<?php

namespace IsoDoss\Meridian\Filament;

use Filament\Widgets\Widget;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;

/**
 * The most frequent errors of the failed jobs, on a Filament dashboard.
 */
class MeridianErrorsWidget extends Widget
{
    protected string $view = 'meridian::filament.errors-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Meridian::check(request());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'groups' => app(ErrorGroupRepository::class)->groups(limit: 5),
            'url' => route('meridian.errors.index'),
        ];
    }
}
