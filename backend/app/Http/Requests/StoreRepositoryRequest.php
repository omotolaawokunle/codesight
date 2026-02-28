<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * The authenticated user is always allowed to attempt repository creation.
     * The 10-repo limit is enforced in the controller after validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating a repository.
     *
     * git_url must point to a supported hosting provider (GitHub, GitLab,
     * or Bitbucket) and must use HTTPS.
     */
    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'git_url'   => [
                'required',
                'string',
                'url',
                // Accept GitHub, GitLab, Bitbucket HTTPS URLs optionally ending in .git
                'regex:#^https://(github\.com|gitlab\.com|bitbucket\.org)/.+#i',
            ],
            'branch'    => ['nullable', 'string', 'max:100'],
            'git_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Human-readable validation error messages.
     */
    public function messages(): array
    {
        return [
            'git_url.required' => 'A Git repository URL is required.',
            'git_url.url'      => 'The Git URL must be a valid URL (e.g. https://github.com/owner/repo).',
            'git_url.regex'    => 'Only GitHub (github.com), GitLab (gitlab.com), and Bitbucket (bitbucket.org) HTTPS URLs are supported.',
        ];
    }
}
