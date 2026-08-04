<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdministratorOperationsService;
use Illuminate\Http\Response;

final class AdministratorOperationsController extends Controller
{
    public function __invoke(
        AdministratorOperationsService $operations
    ): Response {
        return response()
            ->view(
                'admin.dashboard',
                [
                    'operations' =>
                        $operations->build(),
                ]
            )
            ->withHeaders([
                'Cache-Control' =>
                    'no-store, no-cache, must-revalidate, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }
}
