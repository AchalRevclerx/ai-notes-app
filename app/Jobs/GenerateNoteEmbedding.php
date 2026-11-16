<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Note;
use App\Services\Ai\Embedder;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds a note's title + content for semantic search. Runs on the queue so
 * create/update requests never wait on (or fail because of) the AI provider.
 */
class GenerateNoteEmbedding implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds between attempts */
    public array $backoff = [10, 30, 60];

    /** The note may be deleted before the worker picks the job up. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Note $note) {}

    // ponytail: no overlap lock. The model is re-fetched when the job runs, so it
    // always embeds the latest text; with a single worker jobs can't race. Add
    // a WithoutOverlapping($this->note->id) middleware when scaling to N workers.
    public function handle(Embedder $embedder): void
    {
        $this->note->forceFill([
            'embedding' => $embedder->embed($this->note->embeddingText()),
            'embedded_at' => now(),
        ]);

        // Indexing is not a user edit: no events, no updated_at bump.
        Note::withoutTimestamps(fn () => $this->note->saveQuietly());
    }
}
