<?php

use Filament\Panel;
use Filament\Widgets\StatsOverviewWidget\Stat;
use IsoDoss\Meridian\Filament\MeridianErrorsWidget;
use IsoDoss\Meridian\Filament\MeridianPlugin;
use IsoDoss\Meridian\Filament\MeridianStatsWidget;
use IsoDoss\Meridian\MeridianServiceProvider;
use IsoDoss\Meridian\Pulse\MeridianCard;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use Laravel\Pulse\PulseServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

it('shows Meridian in a Laravel Pulse card', function () {
    $this->app->register(LivewireServiceProvider::class);
    $this->app->register(PulseServiceProvider::class);
    (fn () => $this->registerPulseCard())->call(app()->getProvider(MeridianServiceProvider::class));

    FailingJob::dispatch();
    $this->work();
    SuccessfulJob::dispatch()->onQueue('emails');

    Livewire::test(MeridianCard::class)
        ->assertSee('Meridian')
        ->assertSee('database:emails')
        ->assertSee('RuntimeException: Boom from FailingJob')
        ->assertSee(route('meridian.dashboard'));

    expect(Livewire::new('meridian'))->toBeInstanceOf(MeridianCard::class);
});

it('adds the widgets and a navigation item to a Filament panel', function () {
    $panel = Panel::make()->id('admin')->plugin(
        MeridianPlugin::make()->navigationGroup('Monitoring')->navigationLabel('Background jobs')->navigationSort(3)
    );

    expect($panel->getWidgets())->toContain(MeridianStatsWidget::class, MeridianErrorsWidget::class);

    $item = collect($panel->getNavigationItems())->sole();

    expect($item->getLabel())->toBe('Background jobs')
        ->and($item->getGroup())->toBe('Monitoring')
        ->and($item->getSort())->toBe(3)
        ->and($item->getUrl())->toBe(route('meridian.dashboard'))
        ->and($item->shouldOpenUrlInNewTab())->toBeTrue();

    $bare = Panel::make()->id('other')->plugin(MeridianPlugin::make()->withoutNavigation()->withoutWidgets());

    expect($bare->getWidgets())->toBe([])
        ->and($bare->getNavigationItems())->toBe([]);
});

it('computes the figures of the Filament widgets', function () {
    FailingJob::dispatch();
    $this->work();
    SuccessfulJob::dispatch();

    $stats = (fn () => $this->getStats())->call(new MeridianStatsWidget);
    $values = collect($stats)->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => (string) $stat->getValue()]);

    expect($values->all())->toMatchArray(['Pending jobs' => '1', 'Failed jobs' => '1', 'Processing' => '0']);

    $data = (fn () => $this->getViewData())->call(new MeridianErrorsWidget);

    expect($data['groups'][0]['count'])->toBe(1)
        ->and($data['url'])->toBe(route('meridian.errors.index'));
});
