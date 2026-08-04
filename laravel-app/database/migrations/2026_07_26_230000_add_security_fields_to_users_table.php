<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add security and account-management fields.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')
                ->default(true)
                ->index()
                ->after('password');

            $table->timestamp('deactivated_at')
                ->nullable()
                ->after('is_active');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('deactivated_at');

            $table->string('last_login_ip', 45)
                ->nullable()
                ->after('last_login_at');

            $table->timestamp('password_changed_at')
                ->nullable()
                ->after('last_login_ip');

            $table->boolean('must_change_password')
                ->default(false)
                ->after('password_changed_at');

            $table->unsignedSmallInteger('failed_login_count')
                ->default(0)
                ->after('must_change_password');

            $table->timestamp('last_failed_login_at')
                ->nullable()
                ->after('failed_login_count');

            $table->timestamp('locked_until')
                ->nullable()
                ->index()
                ->after('last_failed_login_at');

            $table->foreignId('created_by')
                ->nullable()
                ->after('locked_until')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Remove security and account-management fields.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'deactivated_at',
                'last_login_at',
                'last_login_ip',
                'password_changed_at',
                'must_change_password',
                'failed_login_count',
                'last_failed_login_at',
                'locked_until',
                'created_by',
                'updated_by',
            ]);
        });
    }
};