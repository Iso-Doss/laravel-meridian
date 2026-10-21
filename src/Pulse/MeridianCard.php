<?php

namespace IsoDoss\Meridian\Pulse;

use Illuminate\Contracts\Support\Renderable;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use Laravel\Pulse\Livewire\Card;

/**
 * A Laravel Pulse card showing the database queues monitored by Meridian:
 * add <livewire:meridian cols="4" /> to resources/views/vendor/pulse/dashboard.blade.php.
 */
class MeridianCard extends Card
{
    public function render(): Renderable
    {
        return view('meridian::pulse.card', [
            'stats' => app(DashboardController::class)->stats(),
            'queues' => app(QueueRepository::class)->workload(),
            'errors' => app(ErrorGroupRepository::class)->groups(limit: 5),
            'url' => route('meridian.dashboard'),
        ]);
    }
}
