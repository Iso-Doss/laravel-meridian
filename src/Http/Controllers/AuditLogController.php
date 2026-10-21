<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Support\Migrations;

class AuditLogController extends Controller
{
    use RendersPages;

    public function __construct(protected AuditLogRepository $audit) {}

    public function index(Request $request): View|Response
    {
        $ready = Migrations::hasAuditLog();
        $action = $this->action($request);

        return $this->page('audit.index', 'Audit/Index', [
            'entries' => $ready ? $this->audit->paginate($action)->withQueryString() : null,
            'ready' => $ready,
            'action' => $action,
            'actions' => AuditLogRepository::ACTIONS,
        ], fn (array $data) => [
            'entries' => $data['entries'] ? Present::paginated($data['entries'], fn (array $entry) => $entry) : null,
            'ready' => $data['ready'],
            'filters' => ['action' => (string) $data['action']],
            'actions' => collect($data['actions'])->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        abort_unless(Migrations::hasAuditLog(), 404);

        return response()->json($this->audit->paginate($this->action($request)));
    }

    protected function action(Request $request): ?string
    {
        $action = $request->query('action');

        return is_string($action) && isset(AuditLogRepository::ACTIONS[$action]) ? $action : null;
    }
}
