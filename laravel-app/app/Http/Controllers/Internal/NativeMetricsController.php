<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Observability\NativeMetricsStore;
use Symfony\Component\HttpFoundation\Response;

final class NativeMetricsController extends Controller
{
    public function __invoke(): Response
    {
        return response(
            content: NativeMetricsStore::fromConfig()->render(),
            status: 200,
            headers: [
                'Content-Type' =>
                    'text/plain; version=0.0.4; charset=utf-8',
                'Cache-Control' => 'no-store, private, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
