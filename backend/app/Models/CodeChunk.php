<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'repository_id',
        'vector_id',
        'file_path',
        'chunk_type',
        'name',
        'start_line',
        'end_line',
        'language',
        'signature',
        'docstring',
    ];

    protected function casts(): array
    {
        return [
            'start_line' => 'integer',
            'end_line' => 'integer',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
