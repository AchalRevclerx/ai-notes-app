<?php

declare(strict_types=1);

namespace App\Services\Ai;

interface Summarizer
{
    /**
     * @throws AiException when the provider fails
     */
    public function summarize(string $title, string $content): string;
}
