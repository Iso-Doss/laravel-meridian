<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\Migrations;

class ScheduleController extends Controller
{
    use RendersPages;

    public function __construct(protected ScheduleRepository $schedule) {}

    public function index(): View|Response
    {
        $tasks = array_map(fn (array $task) => $task + [
            'url' => route('meridian.schedule.show', $task['id']),
        ], $this->schedule->tasks());

        return $this->page('schedule.index', 'Schedule/Index', [
            'tasks' => $tasks,
            'ready' => Migrations::hasSchedule(),
        ], fn (array $data) => $data);
    }

    public function show(int $id): View|Response
    {
        $task = $this->schedule->task($id);

        abort_if($task === null, 404);

        return $this->page('schedule.show', 'Schedule/Show', [
            'task' => $task,
            'runs' => $this->schedule->runs($task['name'])->withQueryString(),
        ], fn (array $data) => [
            'task' => $data['task'],
            'runs' => Present::paginated($data['runs'], fn (array $run) => $run),
            'back_url' => route('meridian.schedule.index'),
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json($this->schedule->tasks());
    }
}
