<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\User\CreateManagedUserData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateUserRequest;
use App\Http\Requests\Admin\DeactivateUserRequest;
use App\Http\Requests\Admin\ResetManagedUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserAdministrationService
            $userAdministrationService
    ) {
    }

    public function index(): Response
    {
        $users = $this->users
            ->paginateForAdministration();

        return $this->noStoreView(
            'admin.users.index',
            [
                'users' => $users,
                'roleLabels' =>
                    $this->roleLabels(),
            ]
        );
    }

    public function create(): Response
    {
        return $this->noStoreView(
            'admin.users.create',
            [
                'roles' => array_map(
                    static fn (
                        RoleName $role
                    ): array => [
                        'value' => $role->value,
                        'label' => $role->label(),
                    ],
                    RoleName::cases()
                ),
            ]
        );
    }

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        $result = $this
            ->userAdministrationService
            ->createUser(
                CreateManagedUserData::fromValidated(
                    $request->validated()
                ),
                $request->user()
            );

        return redirect()
            ->route('admin.users.index')
            ->with([
                'status' =>
                    'The user account was created successfully.',

                /*
                 * The temporary password is displayed once on the next
                 * response and is never written to logs or audit records.
                 */
                'temporary_password' =>
                    $result->temporaryPassword,

                'temporary_password_email' =>
                    $result->user->email,
            ]);
    }

    public function activate(
        ActivateUserRequest $request,
        User $user
    ): RedirectResponse {
        $this->userAdministrationService
            ->activateUser(
                $user,
                $request->user()
            );

        return redirect()
            ->route('admin.users.index')
            ->with(
                'status',
                'The user account was activated.'
            );
    }

    public function deactivate(
        DeactivateUserRequest $request,
        User $user
    ): RedirectResponse {
        $this->userAdministrationService
            ->deactivateUser(
                $user,
                $request->user()
            );

        return redirect()
            ->route('admin.users.index')
            ->with(
                'status',
                'The user account was deactivated.'
            );
    }

    public function resetPassword(
        ResetManagedUserPasswordRequest $request,
        User $user
    ): RedirectResponse {
        $result = $this
            ->userAdministrationService
            ->resetTemporaryPassword(
                $user,
                $request->user()
            );

        return redirect()
            ->route('admin.users.index')
            ->with([
                'status' =>
                    'A new temporary password was generated.',

                'temporary_password' =>
                    $result->temporaryPassword,

                'temporary_password_email' =>
                    $result->user->email,
            ]);
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
            ->view($view, $data)
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        $labels = [];

        foreach (RoleName::cases() as $role) {
            $labels[$role->value] =
                $role->label();
        }

        return $labels;
    }
}