<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EndOperatorAssignmentRequest;
use App\Http\Requests\Admin\LinkOperatorAccountRequest;
use App\Http\Requests\Admin\StoreOperatorAssignmentRequest;
use App\Http\Requests\Admin\UpdateOperatorAssignmentRequest;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\User\OperatorAdministrationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class OperatorAdministrationController extends Controller
{
    public function __construct(
        private readonly OperatorAdministrationService $administration
    ) {
    }

    public function index(
        Request $request
    ): Response {
        $search = trim(
            (string) $request->query(
                'q',
                ''
            )
        );

        $operators = Operator::query()
            ->with([
                'user.roles',
            ])
            ->withCount('assignments')
            ->withCount([
                'assignments as current_assignments_count' =>
                    fn (Builder $query): Builder =>
                        $query->current(),
            ])
            ->when(
                $search !== '',
                function (
                    Builder $query
                ) use (
                    $search
                ): void {
                    $like = '%'
                        .addcslashes(
                            $search,
                            '\\%_'
                        )
                        .'%';

                    $query->where(
                        function (
                            Builder $scope
                        ) use (
                            $like
                        ): void {
                            $scope
                                ->where(
                                    'employee_code',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'first_name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    $like
                                )
                                ->orWhereHas(
                                    'user',
                                    function (
                                        Builder $userQuery
                                    ) use (
                                        $like
                                    ): void {
                                        $userQuery
                                            ->where(
                                                'name',
                                                'like',
                                                $like
                                            )
                                            ->orWhere(
                                                'email',
                                                'like',
                                                $like
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return $this->noStoreView(
            'admin.operators.index',
            [
                'operators' => $operators,
                'search' => $search,
            ]
        );
    }

    public function show(
        Operator $operator
    ): Response {
        $operator->load([
            'user.roles',

            /*
             * Laravel passes a HasMany relationship object to an
             * eager-loading callback, not an Eloquent Builder.
             */
            'assignments' => fn ($query) =>
                $query
                    ->with([
                        'productionLine',
                        'shift',
                        'assignedBy',
                    ])
                    ->orderByDesc(
                        'is_active'
                    )
                    ->orderByDesc(
                        'starts_on'
                    )
                    ->orderByDesc('id'),
        ]);

        $linkedUserIds = Operator::query()
            ->whereNotNull('user_id')
            ->where(
                'id',
                '!=',
                $operator->getKey()
            )
            ->pluck('user_id');

        $accountOptions = User::query()
            ->role(
                RoleName::Operator->value
            )
            ->where(
                'is_active',
                true
            )
            ->whereNotIn(
                'id',
                $linkedUserIds
            )
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        $productionLines = ProductionLine::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('code')
            ->get();

        $shifts = Shift::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();

        return $this->noStoreView(
            'admin.operators.show',
            [
                'operator' => $operator,
                'accountOptions' => $accountOptions,
                'productionLines' => $productionLines,
                'shifts' => $shifts,
                'today' => now()->toDateString(),
            ]
        );
    }

    public function linkAccount(
        LinkOperatorAccountRequest $request,
        Operator $operator
    ): RedirectResponse {
        $account = User::query()->findOrFail(
            (int) $request->validated(
                'user_id'
            )
        );

        $this->administration->linkAccount(
            operator: $operator,
            account: $account,
            actor: $request->user()
        );

        return redirect()
            ->route(
                'admin.operator-administration.show',
                $operator
            )
            ->with(
                'status',
                'The Operator account was linked successfully.'
            );
    }

    public function unlinkAccount(
        LinkOperatorAccountRequest $request,
        Operator $operator
    ): RedirectResponse {
        $this->administration->unlinkAccount(
            operator: $operator,
            actor: $request->user()
        );

        return redirect()
            ->route(
                'admin.operator-administration.show',
                $operator
            )
            ->with(
                'status',
                'The Operator account was unlinked.'
            );
    }

    public function storeAssignment(
        StoreOperatorAssignmentRequest $request,
        Operator $operator
    ): RedirectResponse {
        $this->administration->createAssignment(
            operator: $operator,
            data: $request->validated(),
            actor: $request->user()
        );

        return redirect()
            ->route(
                'admin.operator-administration.show',
                $operator
            )
            ->with(
                'status',
                'The production-line assignment was created.'
            );
    }

    public function updateAssignment(
        UpdateOperatorAssignmentRequest $request,
        Operator $operator,
        OperatorAssignment $operatorAssignment
    ): RedirectResponse {
        $this->administration->updateAssignment(
            operator: $operator,
            assignment: $operatorAssignment,
            data: $request->validated(),
            actor: $request->user()
        );

        return redirect()
            ->route(
                'admin.operator-administration.show',
                $operator
            )
            ->with(
                'status',
                'The assignment was updated.'
            );
    }

    public function endAssignment(
        EndOperatorAssignmentRequest $request,
        Operator $operator,
        OperatorAssignment $operatorAssignment
    ): RedirectResponse {
        $data = $request->validated();

        $endsOn = CarbonImmutable::parse(
            $data['ends_on']
                ?? now()->toDateString()
        )->startOfDay();

        $this->administration->endAssignment(
            operator: $operator,
            assignment: $operatorAssignment,
            endsOn: $endsOn,
            actor: $request->user()
        );

        return redirect()
            ->route(
                'admin.operator-administration.show',
                $operator
            )
            ->with(
                'status',
                'The assignment was ended and deactivated.'
            );
    }

    /**
     * Prevent sensitive administration pages from being cached.
     *
     * @param array<string, mixed> $data
     */
    private function noStoreView(
        string $view,
        array $data
    ): Response {
        return response()
            ->view(
                $view,
                $data
            )
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header(
                'Pragma',
                'no-cache'
            )
            ->header(
                'Expires',
                '0'
            );
    }
}