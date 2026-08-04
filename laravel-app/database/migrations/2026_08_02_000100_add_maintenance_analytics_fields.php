<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'production_events',
            function (
                Blueprint $table
            ): void {
                $table
                    ->string(
                        'category',
                        50
                    )
                    ->nullable()
                    ->after('severity');

                $table
                    ->string(
                        'downtime_type',
                        80
                    )
                    ->nullable()
                    ->after('category');

                $table
                    ->string(
                        'reason_code',
                        80
                    )
                    ->nullable()
                    ->after('downtime_type');

                $table
                    ->text('reason')
                    ->nullable()
                    ->after('reason_code');

                $table->index(
                    [
                        'event_type',
                        'category',
                        'started_at',
                    ],
                    'prod_events_downtime_category_idx'
                );

                $table->index(
                    [
                        'downtime_type',
                        'started_at',
                    ],
                    'prod_events_downtime_type_idx'
                );
            }
        );

        Schema::table(
            'machine_status_events',
            function (
                Blueprint $table
            ): void {
                $table
                    ->unsignedInteger(
                        'duration_minutes'
                    )
                    ->nullable()
                    ->after('ended_at');

                $table->index(
                    [
                        'status',
                        'occurred_at',
                        'duration_minutes',
                    ],
                    'machine_status_duration_idx'
                );
            }
        );

        /*
         * Existing synchronized rows have unchanged source checksums. Nulling
         * the checksums once forces the next full ERP synchronization to
         * repersist the newly supported source fields.
         */
        DB::table('production_events')
            ->where(
                'event_type',
                'downtime'
            )
            ->whereNotNull('external_id')
            ->update([
                'source_checksum' => null,
            ]);

        DB::table('machine_status_events')
            ->whereNotNull('external_id')
            ->update([
                'source_checksum' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'machine_status_events',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex(
                    'machine_status_duration_idx'
                );

                $table->dropColumn(
                    'duration_minutes'
                );
            }
        );

        Schema::table(
            'production_events',
            function (
                Blueprint $table
            ): void {
                $table->dropIndex(
                    'prod_events_downtime_category_idx'
                );

                $table->dropIndex(
                    'prod_events_downtime_type_idx'
                );

                $table->dropColumn([
                    'category',
                    'downtime_type',
                    'reason_code',
                    'reason',
                ]);
            }
        );
    }
};
