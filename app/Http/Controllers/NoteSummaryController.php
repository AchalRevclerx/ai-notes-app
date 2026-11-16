<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\Ai\Summarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteSummaryController extends Controller
{
    /**
     * Generate an AI summary of the note.
     *
     * The summary is stored on the note and returned as-is on later calls
     * (`cached: true`) until the note's text changes. Pass `refresh=true`
     * to force a new one. Returns 409 if the note was edited while the summary was
     * being generated, and 503 if the AI provider is unavailable.
     */
    public function __invoke(Request $request, Note $note, Summarizer $summarizer): JsonResponse
    {
        $request->validate(['refresh' => ['sometimes', 'boolean']]);

        $cached = $note->summary !== null && ! $request->boolean('refresh');

        if (! $cached) {
            $summary = $summarizer->summarize($note->title, $note->content);
            $generatedAt = now();

            // The AI call can take seconds. Only store the summary if the note still has
            // the text it was generated from (an edit meanwhile would make it wrong).
            // Query-builder update: no model events, no updated_at bump.
            $stored = Note::whereKey($note->id)
                ->where('title', $note->title)
                ->where('content', $note->content)
                ->toBase()
                ->update(['summary' => $summary, 'summary_generated_at' => $generatedAt]);

            if ($stored === 0) {
                return response()->json([
                    'message' => 'The note changed while its summary was being generated. Please try again.',
                ], 409);
            }

            $note->forceFill(['summary' => $summary, 'summary_generated_at' => $generatedAt])->syncOriginal();
        }

        return response()->json([
            'data' => [
                'note_id' => $note->id,
                'summary' => $note->summary,
                'generated_at' => $note->summary_generated_at->toIso8601String(),
                'cached' => $cached,
                /** Which model wrote it: the chat model, or "local-extractive" for the offline driver. */
                'model' => config('ai.driver') === 'openai' ? config('ai.chat_model') : 'local-extractive',
            ],
        ]);
    }
}
