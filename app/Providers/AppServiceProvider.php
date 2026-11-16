<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Embedder;
use App\Services\Ai\LocalAiProvider;
use App\Services\Ai\OpenAiCompatibleClient;
use App\Services\Ai\Summarizer;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAiCompatibleClient::class, fn () => new OpenAiCompatibleClient(
            baseUrl: (string) config('ai.base_url'),
            apiKey: config('ai.api_key'),
            chatModel: (string) config('ai.chat_model'),
            embeddingModel: (string) config('ai.embedding_model'),
            dimensions: (int) config('ai.embedding_dimensions'),
            timeout: (int) config('ai.timeout'),
        ));

        $this->app->singleton(LocalAiProvider::class, fn () => new LocalAiProvider(
            (int) config('ai.embedding_dimensions'),
        ));

        $driver = fn (Application $app) => match (config('ai.driver')) {
            'openai' => $app->make(OpenAiCompatibleClient::class),
            'local' => $app->make(LocalAiProvider::class),
            default => throw new InvalidArgumentException('AI_DRIVER must be "openai" or "local".'),
        };

        $this->app->bind(Embedder::class, $driver);
        $this->app->bind(Summarizer::class, $driver);
    }

    public function boot(): void
    {
        $this->configureRateLimits();

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    private function configureRateLimits(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));

        // AI calls cost money: semantic search + summaries get a tighter budget.
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute(20)->by($byUserOrIp($request)));

        // Brute-force protection: per email+IP, plus an overall per-IP cap.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower(is_string($request->input('email')) ? $request->input('email') : '').'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
