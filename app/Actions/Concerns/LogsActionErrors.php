<?php

namespace App\Actions\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provides structured exception logging for Action classes.
 *
 * Usage — wrap your execute() body:
 *
 *   use App\Actions\Concerns\LogsActionErrors;
 *
 *   class DeployAgentAction
 *   {
 *       use LogsActionErrors;
 *
 *       public function execute(DeployAgentData $data): AgentDeployment
 *       {
 *           return $this->runAction(fn () => ..., context: ['data' => $data]);
 *       }
 *   }
 *
 * The exception is re-thrown after logging so the caller (Livewire, Job, etc.)
 * retains full exception handling. This trait only adds observability context.
 */
trait LogsActionErrors
{
    /**
     * Execute a callable, catching any exception to add structured context
     * before re-throwing. The Sentry log channel (when configured) will
     * capture the enriched log entry automatically.
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $context Additional context to include in the log entry
     * @return T
     *
     * @throws Throwable
     */
    protected function runAction(callable $callback, array $context = []): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::error('[Action] Unhandled exception in ' . static::class, [
                'action' => static::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                ...$context,
            ]);

            throw $e;
        }
    }
}
