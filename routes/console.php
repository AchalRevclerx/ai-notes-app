<?php

use App\Jobs\GenerateNoteEmbedding;
use App\Models\Note;
use Illuminate\Support\Facades\Artisan;

Artisan::command('notes:reindex {--all : Re-embed every note, not only notes without an embedding}', function () {
    $queued = 0;

    Note::query()
        ->select('id')
        ->when(! $this->option('all'), fn ($query) => $query->whereNull('embedded_at'))
        ->chunkById(200, function ($notes) use (&$queued) {
            foreach ($notes as $note) {
                GenerateNoteEmbedding::dispatch($note);
                $queued++;
            }
        });

    $this->info("Queued {$queued} note(s) for embedding.");
})->purpose('Queue embedding generation, e.g. after changing the AI provider or model');
