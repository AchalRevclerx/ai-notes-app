<?php

declare(strict_types=1);

namespace App\Services\Ai;

interface Embedder
{
    /**
     * Turn text into a fixed-size vector (config('ai.embedding_dimensions')).
     *
     * @return list<float>
     *
     * @throws AiException when the provider fails
     */
    public function embed(string $text): array;
}
