<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'notifications',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string(
                    'type'
                );

                $table->morphs(
                    'notifiable'
                );

                $table->text(
                    'data'
                );

                $table->string(
                    'severity',
                    20
                )
                    ->default('information')
                    ->index();

                $table->string(
                    'category',
                    80
                )->index();

                $table->timestamp(
                    'read_at'
                )->nullable();

                /*
                 * SHA-256 of recipient identity and alert fingerprint.
                 * The unique constraint makes alert evaluation idempotent.
                 */
                $table->char(
                    'dedupe_key',
                    64
                )->unique();

                $table->timestamp(
                    'expires_at'
                )
                    ->nullable()
                    ->index();

                $table->timestamps();

                $table->index([
                    'notifiable_type',
                    'notifiable_id',
                    'read_at',
                    'created_at',
                ], 'notifications_inbox_index');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notifications'
        );
    }
};
