<?php

namespace App\Ai\Agents;

use App\Models\Conversation;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class CodeAssistantAgent implements Agent, Conversational
{
    use Promptable;

    private const MAX_HISTORY_MESSAGES = 10;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert code assistant helping developers understand and work with their codebase.

Your capabilities:
- Explain how code works
- Help debug issues
- Suggest improvements
- Answer questions about architecture and design
- Guide developers to relevant parts of the code

When answering:
- Be specific and reference exact file paths and line numbers
- Include code snippets when helpful
- Explain your reasoning
- If you're not certain, say so
- Suggest where to look for more information

Always base your answers on the provided code context. When referencing files, format them as:
📄 path/to/file.php:15-45
PROMPT;

    private const ERROR_ANALYSIS_PROMPT = <<<'PROMPT'
You are an expert debugger helping developers diagnose and fix errors in their codebase.

Your capabilities:
- Analyze error messages and stack traces
- Identify the root cause of failures
- Suggest specific fixes with code examples
- Explain why the error occurred
- Identify related code that may need changes

When analyzing errors:
- Start by summarizing what the error means
- Trace through the stack to find the root cause
- Reference the exact file paths and line numbers from the context
- Provide concrete, actionable fix suggestions with code snippets
- Mention any related code that could be affected

Always base your analysis on the provided code context and error trace.
PROMPT;

    public function __construct(
        private readonly ?int $conversationId = null,
        private readonly bool $isErrorAnalysis = false,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->isErrorAnalysis ? self::ERROR_ANALYSIS_PROMPT : self::SYSTEM_PROMPT;
    }

    /**
     * Load the last N messages from our own conversations/messages tables.
     *
     * We intentionally manage our own conversation history rather than using
     * the SDK's RemembersConversations trait, since our schema already has
     * dedicated conversations and messages tables from Batch 1.
     */
    public function messages(): iterable
    {
        if ($this->conversationId === null) {
            return [];
        }

        $conversation = Conversation::find($this->conversationId);

        if ($conversation === null) {
            return [];
        }

        return $conversation->messages()
            ->latest('created_at')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn ($msg) => new Message($msg->role, $msg->content))
            ->values()
            ->all();
    }
}
