<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\Embedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Runs the real pgvector query; embeddings come from the offline LocalAiProvider. */
class NoteSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Sanctum::actingAs(User::factory()->create());
    }

    public function test_notes_are_embedded_when_created_and_updated(): void
    {
        $note = Note::factory()->for($this->user)->create();
        $this->assertNotNull($note->refresh()->embedded_at);
        $this->assertCount(768, $note->embedding);

        $before = $note->embedding;
        $note->update(['content' => 'Something completely different about mountains']);
        $this->assertNotEquals($before, $note->refresh()->embedding);
    }

    public function test_search_ranks_the_most_relevant_note_first(): void
    {
        Note::factory()->for($this->user)->create(['title' => 'Monthly SIP plan', 'content' => 'Invest 5000 rupees every month in an index mutual fund.']);
        Note::factory()->for($this->user)->create(['title' => 'Goa trip', 'content' => 'Book flights and a beach hotel for December.']);
        Note::factory()->for($this->user)->create(['title' => 'Biryani recipe', 'content' => 'Marinate chicken in curd, layer with rice and saffron.']);

        $this->getJson('/api/notes/search?q='.urlencode('mutual fund investing'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Monthly SIP plan')
            ->assertJsonPath('meta.query', 'mutual fund investing')
            ->assertJsonPath('meta.pending_index', 0)
            ->assertJsonStructure(['data' => [['id', 'title', 'score']], 'meta' => ['count', 'min_similarity']]);
    }

    public function test_search_scores_are_sorted_and_above_threshold(): void
    {
        Note::factory()->for($this->user)->count(5)->create();
        Note::factory()->for($this->user)->create(['title' => 'Kubernetes notes', 'content' => 'Kubernetes pods, deployments and services.']);

        $scores = collect($this->getJson('/api/notes/search?q=kubernetes+deployments')->json('data'))->pluck('score');

        $this->assertNotEmpty($scores);
        $this->assertSame($scores->sortDesc()->values()->all(), $scores->all());
        $this->assertTrue($scores->every(fn ($s) => $s >= config('ai.search_min_similarity')));
    }

    public function test_search_never_returns_other_users_notes(): void
    {
        Note::factory()->for(User::factory())->create(['title' => 'Secret kubernetes plan', 'content' => 'kubernetes kubernetes']);

        $this->getJson('/api/notes/search?q=kubernetes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unrelated_query_returns_no_results(): void
    {
        Note::factory()->for($this->user)->create(['title' => 'Biryani recipe', 'content' => 'Marinate chicken in curd.']);

        $this->getJson('/api/notes/search?q=quantum+entanglement')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_validates_query(): void
    {
        $this->getJson('/api/notes/search')->assertUnprocessable()->assertJsonValidationErrors(['q']);
        $this->getJson('/api/notes/search?q=a')->assertUnprocessable()->assertJsonValidationErrors(['q']);
        $this->getJson('/api/notes/search?q=ok&limit=500')->assertUnprocessable()->assertJsonValidationErrors(['limit']);
    }

    public function test_search_returns_503_when_ai_provider_fails(): void
    {
        $this->app->bind(Embedder::class, fn () => new class implements Embedder
        {
            public function embed(string $text): array
            {
                throw new AiException('The AI provider is unavailable right now. Please try again shortly.');
            }
        });

        $this->getJson('/api/notes/search?q=anything')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The AI provider is unavailable right now. Please try again shortly.');
    }
}
