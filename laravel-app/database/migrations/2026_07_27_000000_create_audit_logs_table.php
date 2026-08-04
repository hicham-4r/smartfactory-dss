<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the append-oriented security audit log.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')
                ->unique();

            $table->uuid('request_id')
                ->nullable()
                ->index();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 150)
                ->index();

            $table->string('auditable_type')
                ->nullable()
                ->index();

            $table->string('auditable_id', 64)
                ->nullable()
                ->index();

            $table->json('old_values')
                ->nullable();

            $table->json('new_values')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamp('occurred_at')
                ->index();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->index([
                'actor_id',
                'occurred_at',
            ]);

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);
        });
    }

    /**
     * Remove the audit-log table.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};