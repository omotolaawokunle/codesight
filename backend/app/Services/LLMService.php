<?php

namespace App\Services;

use App\Ai\Agents\CodeAssistantAgent;
use App\Models\Conversation;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class LLMService
{
    private const MAX_CONTEXT_TOKENS = 150_000;
    private const MAX_RESPONSE_TOKENS = 4_096;
    private const MAX_RETRY_ATTEMPTS = 3;
    private const TITLE_MAX_LENGTH = 80;

    public function __construct(
        private readonly Retriever $retriever,
    ) {}

    /**
     * Perform a RAG-based chat query and return the full response.
     *
     * @return array{content: string, sources: array<int, array<string, mixed>>, usage: array<string, int>}
     */
    public function chat(
        int $conversationId,
        int $repositoryId,
        string $query,
    ): array {
        $chunks = $this->retriever->retrieveWithContext($repositoryId, $query);
        $context = $this->retriever->formatContextForLLM($chunks, self::MAX_CONTEXT_TOKENS);

        $prompt = $this->buildPrompt($context, $query);

        $response = $this->attemptWithRetry(function () use ($conversationId, $prompt) {
            $agent = new CodeAssistantAgent(conversationId: $conversationId);

            return $agent->prompt(
                $prompt,
                provider: $this->resolveProvider(),
                model: $this->resolveModel(),
            );
        });

        return [
            'content' => (string) $response,
            'sources' => $this->extractSources($chunks),
            'usage'   => $response->usage?->toArray() ?? [],
        ];
    }

    /**
     * Perform a RAG-based chat query and return a streamable SSE response.
     *
     * The caller is responsible for attaching a ->then() callback to persist
     * the completed assistant message to the database.
     */
    public function stream(
        int $conversationId,
        int $repositoryId,
        string $query,
    ): StreamableAgentResponse {
        $chunks = $this->retriever->retrieveWithContext($repositoryId, $query);
        $context = $this->retriever->formatContextForLLM($chunks, self::MAX_CONTEXT_TOKENS);

        $prompt = $this->buildPrompt($context, $query);

        $agent = new CodeAssistantAgent(conversationId: $conversationId);

        return $agent->stream(
            $prompt,
            provider: $this->resolveProvider(),
            model: $this->resolveModel(),
        );
    }

    /**
     * Analyse an error log using the error-specific agent prompt.
     *
     * @return array{content: string, sources: array<int, array<string, mixed>>, usage: array<string, int>}
     */
    public function analyzeError(int $repositoryId, string $errorLog): array
    {
        $chunks = $this->retriever->retrieveByErrorTrace($repositoryId, $errorLog);
        $context = $this->retriever->formatContextForLLM($chunks, self::MAX_CONTEXT_TOKENS);

        $prompt = $this->buildErrorPrompt($context, $errorLog);

        $response = $this->attemptWithRetry(function () use ($prompt) {
            $agent = new CodeAssistantAgent(isErrorAnalysis: true);

            return $agent->prompt(
                $prompt,
                provider: $this->resolveProvider(),
                model: $this->resolveModel(),
            );
        });

        return [
            'content' => (string) $response,
            'sources' => $this->extractSources($chunks),
            'usage'   => $response->usage?->toArray() ?? [],
        ];
    }

    /**
     * Generate a short conversation title from the first user message.
     *
     * Falls back to a simple truncation when the API call fails.
     */
    public function generateTitle(string $firstMessage): string
    {
        try {
            $agent = new CodeAssistantAgent();

            $titlePrompt = 'Generate a concise title (5 words max, no quotes, no punctuation at the end) '
                . 'for a conversation that starts with this message: ' . $firstMessage;

            $response = $agent->prompt(
                $titlePrompt,
                provider: $this->resolveProvider(),
                model: $this->resolveModel(),
            );

            $title = trim((string) $response);

            return $title !== '' ? mb_substr($title, 0, self::TITLE_MAX_LENGTH) : $this->truncateTitle($firstMessage);
        } catch (Throwable $e) {
            Log::warning('LLMService: failed to generate conversation title', ['error' => $e->getMessage()]);

            return $this->truncateTitle($firstMessage);
        }
    }

    /**
     * Build a RAG-injected prompt string for a standard code question.
     */
    private function buildPrompt(string $context, string $query): string
    {
        if ($context === '') {
            return $query;
        }

        return "Relevant code context from the repository:\n\n{$context}\n\n---\n\nQuestion: {$query}";
    }

    /**
     * Build a RAG-injected prompt string for error log analysis.
     */
    private function buildErrorPrompt(string $context, string $errorLog): string
    {
        $parts = ["Error log to analyze:\n```\n{$errorLog}\n```"];

        if ($context !== '') {
            $parts[] = "Relevant code context from the repository:\n\n{$context}";
        }

        $parts[] = 'Please analyze this error, identify the root cause, and suggest specific fixes.';

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Extract a minimal source attribution array from retrieved chunks.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function extractSources(array $chunks): array
    {
        return array_map(fn (array $chunk) => [
            'file_path'  => $chunk['file_path'] ?? null,
            'start_line' => $chunk['start_line'] ?? null,
            'end_line'   => $chunk['end_line'] ?? null,
            'name'       => $chunk['name'] ?? null,
            'chunk_type' => $chunk['chunk_type'] ?? null,
            'score'      => isset($chunk['score']) ? round((float) $chunk['score'], 4) : null,
        ], $chunks);
    }

    /**
     * Retry a callable with exponential backoff on failure.
     */
    private function attemptWithRetry(callable $callback, int $maxAttempts = self::MAX_RETRY_ATTEMPTS): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    Log::error('LLMService: all retry attempts exhausted', [
                        'attempts' => $attempt,
                        'error'    => $e->getMessage(),
                    ]);

                    throw $e;
                }

                $delayMs = (int) (500 * (2 ** ($attempt - 1)));
                Log::warning('LLMService: API call failed, retrying', [
                    'attempt'  => $attempt,
                    'delay_ms' => $delayMs,
                    'error'    => $e->getMessage(),
                ]);

                usleep($delayMs * 1_000);
            }
        }
    }

    /**
     * Resolve the AI provider from environment configuration.
     */
    private function resolveProvider(): Lab
    {
        $name = config('ai.chat_provider', 'gemini');

        return Lab::from($name);
    }

    /**
     * Resolve the AI model identifier from environment configuration.
     */
    private function resolveModel(): string
    {
        return (string) config('ai.chat_model', 'gemini-3.0-flash');
    }

    /**
     * Truncate the first message to produce a basic fallback title.
     */
    private function truncateTitle(string $message): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($message)) ?? $message;

        return mb_strlen($clean) <= self::TITLE_MAX_LENGTH
            ? $clean
            : mb_substr($clean, 0, self::TITLE_MAX_LENGTH - 3) . '...';
    }
}
