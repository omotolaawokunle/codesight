<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'git_url',
        'git_token',
        'branch',
        'indexing_status',
        'indexing_started_at',
        'indexing_completed_at',
        'indexing_error',
        'total_files',
        'indexed_files',
        'total_chunks',
        'last_indexed_commit',
    ];

    protected $hidden = [
        'git_token',
    ];

    protected function casts(): array
    {
        return [
            'indexing_started_at' => 'datetime',
            'indexing_completed_at' => 'datetime',
            'total_files' => 'integer',
            'indexed_files' => 'integer',
            'total_chunks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function codeChunks(): HasMany
    {
        return $this->hasMany(CodeChunk::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
