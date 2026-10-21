<?php

namespace IsoDoss\Meridian\Filament;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use IsoDoss\Meridian\Meridian;

/**
 * Shows Meridian in a Filament panel: its stats and errors on the
 * dashboard, and a link to Meridian in the navigation.
 *
 *     $panel->plugin(MeridianPlugin::make()->navigationGroup('Monitoring'));
 */
class MeridianPlugin implements Plugin
{
    protected bool $hasNavigation = true;

    protected bool $hasWidgets = true;

    protected string $navigationLabel = 'Queues';

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected string $navigationIcon = 'heroicon-o-queue-list';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'meridian';
    }

    public function register(Panel $panel): void
    {
        if ($this->hasWidgets) {
            $panel->widgets([MeridianStatsWidget::class, MeridianErrorsWidget::class]);
        }

        if ($this->hasNavigation) {
            $panel->navigationItems([
                NavigationItem::make($this->navigationLabel)
                    ->url(fn () => route('meridian.dashboard'), shouldOpenInNewTab: true)
                    ->icon($this->navigationIcon)
                    ->group($this->navigationGroup)
                    ->sort($this->navigationSort)
                    ->visible(fn () => Meridian::check(request())),
            ]);
        }
    }

    public function boot(Panel $panel): void {}

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function withoutNavigation(): static
    {
        $this->hasNavigation = false;

        return $this;
    }

    public function withoutWidgets(): static
    {
        $this->hasWidgets = false;

        return $this;
    }
}
