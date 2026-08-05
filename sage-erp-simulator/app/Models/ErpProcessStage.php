<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpProcessStage extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_process_stages';

    protected $fillable = [
        'code',
        'name',
        'sequence_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}