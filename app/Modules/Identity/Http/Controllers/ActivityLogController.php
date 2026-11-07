<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\ActivityLogService;
use App\Modules\Identity\Http\Requests\SearchActivityLogRequest;
use App\Modules\Identity\IdentityAccountService;
use Illuminate\View\View;

final class ActivityLogController
{
    public function __construct(
        private ActivityLogService $logs,
        private IdentityAccountService $accounts,
    ) {}

    public function getActivityLogs(SearchActivityLogRequest $request): View
    {
        $input = $request->validated();
        $page = $this->logs->getActivityLogs(
            $input['q'] ?? null,
            isset($input['id_usuario']) ? (int) $input['id_usuario'] : null,
            $input['accion'] ?? null,
            $input['desde'] ?? null,
            $input['hasta'] ?? null,
            isset($input['pagina']) ? (int) $input['pagina'] : 1,
        );

        return view('identity.activity-log.index', [
            'entries' => $page['entries'],
            'total' => $page['total'],
            'page' => $page['page'],
            'pages' => $page['pages'],
            'accounts' => $this->accounts->getAllAccounts(null),
            'actions' => ActivityLogService::ACTIONS,
            'q' => $input['q'] ?? '',
            'id_usuario' => isset($input['id_usuario']) ? (string) $input['id_usuario'] : '',
            'accion' => $input['accion'] ?? '',
            'desde' => $page['from'] ?? '',
            'hasta' => $page['to'] ?? '',
        ]);
    }
}
