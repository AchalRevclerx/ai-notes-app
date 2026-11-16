<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The AI provider failed or returned something unusable. The message is safe
 * to show to API clients; provider details stay in the logged previous exception.
 */
class AiException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}
