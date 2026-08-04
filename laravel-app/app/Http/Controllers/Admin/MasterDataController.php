<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrowseMasterDataRequest;
use App\Repositories\Contracts\MasterDataBrowseRepositoryInterface;
use Illuminate\Http\Response;

final class MasterDataController extends Controller
{
    public function __construct(
        private readonly MasterDataBrowseRepositoryInterface
            $masterData
    ) {
    }

    public function index(): Response
    {
        return $this->noStoreView(
            'admin.master-data.index',
            [
                'counts' =>
                    $this->masterData
                        ->overviewCounts(),
            ]
        );
    }

    public function products(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.products',
            [
                'products' =>
                    $this->masterData
                        ->products($filters),

                'families' =>
                    $this->masterData
                        ->productFamilyOptions(),

                'filters' => $filters,
            ]
        );
    }

    public function productionLines(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.production-lines',
            [
                'productionLines' =>
                    $this->masterData
                        ->productionLines(
                            $filters
                        ),

                'filters' => $filters,
            ]
        );
    }

    public function machines(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.machines',
            [
                'machines' =>
                    $this->masterData
                        ->machines($filters),

                'productionLines' =>
                    $this->masterData
                        ->productionLineOptions(),

                'filters' => $filters,
            ]
        );
    }

    public function shifts(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.shifts',
            [
                'shifts' =>
                    $this->masterData
                        ->shifts($filters),

                'filters' => $filters,
            ]
        );
    }

    public function operators(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.operators',
            [
                'operators' =>
                    $this->masterData
                        ->operators($filters),

                'filters' => $filters,
            ]
        );
    }

    public function assignments(
        BrowseMasterDataRequest $request
    ): Response {
        $filters = $request->filters();

        return $this->noStoreView(
            'admin.master-data.assignments',
            [
                'assignments' =>
                    $this->masterData
                        ->operatorAssignments(
                            $filters
                        ),

                'productionLines' =>
                    $this->masterData
                        ->productionLineOptions(),

                'shifts' =>
                    $this->masterData
                        ->shiftOptions(),

                'filters' => $filters,
            ]
        );
    }

    /**
     * Prevent authenticated master data from being cached.
     *
     * @param array<string, mixed> $data
     */
    private function noStoreView(
        string $view,
        array $data
    ): Response {
        return response()
            ->view($view, $data)
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}