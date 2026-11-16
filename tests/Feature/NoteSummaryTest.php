<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\Summarizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** Counts calls so tests can prove cached summaries skip the AI provider. */
    private object $summarizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Sanctum::actingAs(User::factory()->create());

        $this->summarizer = new class implements Summarizer
        {
            public int $calls = 0;

            public function summarize(string $title, string $content): string
            {
                $this->calls++;

                return "Summary #{$this->calls} of {$title}";
            }
        };
        $this->app->instance(Summarizer::class, $this->summarizer);
    }

    public function test_generates_and_stores_summary(): void
    {
        $note = Note::factory()->for($this->user)->create(['title' => 'Standup']);

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.note_id', $note->id)
            ->assertJsonPath('data.summary', 'Summary #1 of Standup')
            ->assertJsonPath('data.cached', false);

        $this->assertSame('Summary #1 of Standup', $note->refresh()->summary);
        $this->getJson("/api/notes/{$note->id}")->assertJsonPath('data.summary', 'Summary #1 of Standup');
    }

    public function test_second_call_returns_cached_summary_without_calling_ai(): void
    {
        $note = Note::factory()->for($this->user)->create(['title' => 'Standup']);

        $this->postJson("/api/notes/{$note->id}/summary")->assertOk();
        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.summary', 'Summary #1 of Standup')
            ->assertJsonPath('data.cached', true);

        $this->assertSame(1, $this->summarizer->calls);
    }

    public function test_refresh_forces_a_new_summary(): void
    {
        $note = Note::factory()->for($this->user)->create(['title' => 'Standup']);

        $this->postJson("/api/notes/{$note->id}/summary")->assertOk();
        $this->postJson("/api/notes/{$note->id}/summary", ['refresh' => true])
            ->assertOk()
            ->assertJsonPath('data.summary', 'Summary #2 of Standup')
            ->assertJsonPath('data.cached', false);
    }

    public function test_summary_does_not_change_updated_at(): void
    {
        $note = Note::factory()->for($this->user)->create(['updated_at' => now()->subDay()]);
        $before = $note->refresh()->updated_at;

        $this->postJson("/api/notes/{$note->id}/summary")->assertOk();

        $this->assertTrue($before->equalTo($note->refresh()->updated_at));
    }

    public function test_summary_is_not_stored_if_note_was_edited_meanwhile(): void
    {
        $note = Note::factory()->for($this->user)->create(['content' => 'Original text']);

        // Simulate the user saving an edit while the AI call is in flight.
        $this->app->instance(Summarizer::class, new class($note) implements Summarizer
        {
            public function __construct(private Note $note) {}

            public function summarize(string $title, string $content): string
            {
                Note::find($this->note->id)->update(['content' => 'Edited text']);

                return "Summary of {$content}";
            }
        });

        $this->postJson("/api/notes/{$note->id}/summary")->assertConflict();

        $this->assertNull($note->refresh()->summary);
        $this->assertSame('Edited text', $note->content);
    }

    public function test_ai_failure_returns_503_and_keeps_note_unchanged(): void
    {
        $this->app->instance(Summarizer::class, new class implements Summarizer
        {
            public function summarize(string $title, string $content): string
            {
                throw new AiException('The AI provider is unavailable right now. Please try again shortly.');
            }
        });
        $note = Note::factory()->for($this->user)->create();

        $this->postJson("/api/notes/{$note->id}/summary")
            ->assertServiceUnavailable()
            ->assertJsonStructure(['message']);

        $this->assertNull($note->refresh()->summary);
    }
}
