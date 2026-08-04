<?php

namespace App\Console\Commands;

use App\Services\User\AdministratorProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAdministratorCommand extends Command
{
    /**
     * No password option is provided because command-line options may
     * remain visible in shell history or operating-system process lists.
     */
    protected $signature = 'smartfactory:admin:create';

    protected $description =
        'Securely create the initial SmartFactory DSS administrator';

    /**
     * Execute the console command.
     */
    public function handle(
        AdministratorProvisioningService $provisioningService
    ): int {
        $this->newLine();

        $this->info(
            'SmartFactory DSS — Secure Administrator Provisioning'
        );

        $this->newLine();

        $name = trim(
            (string) $this->ask('Administrator full name')
        );

        $email = trim(
            (string) $this->ask('Administrator email address')
        );

        /*
         * secret() prevents the password from being displayed while typed.
         */
        $password = (string) $this->secret(
            'Temporary password'
        );

        $passwordConfirmation = (string) $this->secret(
            'Confirm temporary password'
        );

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:120',
                ],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                ],
                'password' => [
                    'required',
                    'string',
                    'confirmed',
                    Password::default(),
                ],
            ]
        );

        if ($validator->fails()) {
            $this->displayValidationErrors(
                $validator->errors()->toArray()
            );

            return self::INVALID;
        }

        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $name],
                ['Email', mb_strtolower($email)],
                ['Role', 'Administrator'],
                ['Status', 'Active'],
                ['Password change', 'Required at first login'],
            ]
        );

        if (
            ! $this->confirm(
                'Create this administrator account?',
                false
            )
        ) {
            $this->warn(
                'Administrator creation was cancelled.'
            );

            return self::SUCCESS;
        }

        try {
            $administrator = $provisioningService->provision(
                $name,
                $email,
                $password
            );
        } catch (ValidationException $exception) {
            $this->displayValidationErrors(
                $exception->errors()
            );

            return self::INVALID;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'The administrator account could not be created. '
                .'Review storage/logs/laravel.log for technical details.'
            );

            return self::FAILURE;
        }

        $this->newLine();

        $this->info(
            'Administrator account created successfully.'
        );

        $this->table(
            ['Field', 'Value'],
            [
                ['User ID', (string) $administrator->getKey()],
                ['Email', $administrator->email],
                ['Role', 'Administrator'],
                ['Must change password', 'Yes'],
            ]
        );

        $this->warn(
            'Do not share or record the temporary password in source files, '
            .'screenshots, chat messages, or Git.'
        );

        return self::SUCCESS;
    }

    /**
     * Display validation failures without exposing sensitive values.
     *
     * @param array<string, list<string>> $errors
     */
    private function displayValidationErrors(
        array $errors
    ): void {
        $this->newLine();

        $this->error(
            'Administrator account validation failed.'
        );

        foreach ($errors as $messages) {
            foreach ($messages as $message) {
                $this->line(' - '.$message);
            }
        }
    }
}