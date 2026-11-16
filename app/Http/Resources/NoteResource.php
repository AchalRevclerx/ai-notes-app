<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'summary' => $this->summary,
            'summary_generated_at' => $this->summary_generated_at?->toIso8601String(),
            /** Whether the note has an embedding yet (it is generated on a background queue). */
            'is_indexed' => $this->embedded_at !== null,
            /** Cosine similarity to the query, 0..1. Only present in search results. */
            'score' => $this->whenHas('distance', fn ($distance) => round(1 - (float) $distance, 4)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
