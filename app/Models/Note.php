<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\GenerateNoteEmbedding;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['title', 'content'])]
#[Hidden(['embedding'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /** Route {note} constraint: digits that fit in a Postgres bigint (else the DB throws a 500). */
    public const ROUTE_ID_PATTERN = '[0-9]{1,18}';

    /** Embedding input is capped so long notes stay inside the model's token limit. */
    public const EMBEDDING_INPUT_LIMIT = 8000;

    /** Columns the API reads. Skips the ~768-float embedding on every list/search query. */
    public const PUBLIC_COLUMNS = [
        'id', 'user_id', 'title', 'content', 'summary',
        'summary_generated_at', 'embedded_at', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => AsVector::class,
            'summary_generated_at' => 'datetime',
            'embedded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A summary describes specific content; once the text changes it is stale.
        static::saving(function (Note $note) {
            if ($note->exists && $note->isDirty(['title', 'content'])) {
                $note->summary = null;
                $note->summary_generated_at = null;
            }
        });

        static::created(fn (Note $note) => GenerateNoteEmbedding::dispatch($note));

        static::updated(function (Note $note) {
            if ($note->wasChanged(['title', 'content'])) {
                GenerateNoteEmbedding::dispatch($note);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Note>  $query */
    public function scopePublicColumns(Builder $query): void
    {
        $query->select(array_map(fn ($column) => "notes.{$column}", self::PUBLIC_COLUMNS));
    }

    // ponytail: one embedding per note (first 8k chars). Chunk long notes into
    // a note_chunks table if notes regularly exceed a few pages.
    public function embeddingText(): string
    {
        return Str::limit($this->title."\n\n".$this->content, self::EMBEDDING_INPUT_LIMIT, '');
    }
}
