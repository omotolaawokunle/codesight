<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource transformer for the Repository model.
 *
 * The git_token is NEVER included in the API response — it is hidden at
 * the model level and encrypted at rest in the database. Decryption
 * happens only in GitManager::clone() at indexing time.
 */
class RepositoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'git_url'               => $this->git_url,
            'branch'                => $this->branch,
            'indexing_status'       => $this->indexing_status,
            'progress_percentage'   => $this->computeProgress(),
            'total_files'           => $this->total_files,
            'indexed_files'         => $this->indexed_files,
            'total_chunks'          => $this->total_chunks,
            'last_indexed_commit'   => $this->last_indexed_commit,
            'indexing_started_at'   => $this->indexing_started_at?->toIso8601String(),
            'indexing_completed_at' => $this->indexing_completed_at?->toIso8601String(),
            'indexing_error'        => $this->indexing_error,
            'created_at'            => $this->created_at->toIso8601String(),
            'updated_at'            => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Compute a 0–100 progress percentage for the indexing pipeline.
     *
     * Returns 100 when completed, 0 when no file counts are available yet.
     */
    private function computeProgress(): float
    {
        if ($this->indexing_status === 'completed') {
            return 100.0;
        }

        if ($this->total_files && $this->indexed_files !== null) {
            return round(($this->indexed_files / $this->total_files) * 100, 1);
        }

        return 0.0;
    }
}
