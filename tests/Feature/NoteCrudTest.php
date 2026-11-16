<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoteCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Sanctum::actingAs(User::factory()->create());
    }

    public function test_create_note(): void
    {
        $response = $this->postJson('/api/notes', ['title' => '  Groceries ', 'content' => 'Milk, eggs, atta']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Groceries')
            ->assertJsonPath('data.content', 'Milk, eggs, atta')
            ->assertJsonPath('data.summary', null)
            ->assertJsonMissingPath('data.embedding');

        $this->assertDatabaseHas('notes', ['title' => 'Groceries', 'user_id' => $this->user->id]);
    }

    public function test_create_note_validates_input(): void
    {
        $this->postJson('/api/notes', ['title' => str_repeat('x', 256), 'content' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_mass_assignment_cannot_set_owner_or_summary(): void
    {
        $other = User::factory()->create();

        $this->postJson('/api/notes', [
            'title' => 'Mine', 'content' => 'Body', 'user_id' => $other->id, 'summary' => 'injected',
        ])->assertCreated()->assertJsonPath('data.summary', null);

        $this->assertDatabaseHas('notes', ['title' => 'Mine', 'user_id' => $this->user->id, 'summary' => null]);
    }

    public function test_show_note(): void
    {
        $note = Note::factory()->for($this->user)->create();

        $this->getJson("/api/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.title', $note->title);
    }

    public function test_cannot_access_another_users_note(): void
    {
        $note = Note::factory()->for(User::factory())->create();

        $this->getJson("/api/notes/{$note->id}")->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->putJson("/api/notes/{$note->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/notes/{$note->id}")->assertNotFound();
        $this->postJson("/api/notes/{$note->id}/summary")->assertNotFound();

        $this->assertDatabaseHas('notes', ['id' => $note->id, 'title' => $note->title]);
    }

    public function test_missing_note_returns_clean_404(): void
    {
        $this->getJson('/api/notes/999999')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
        $this->getJson('/api/notes/abc')->assertNotFound();
        $this->getJson('/api/notes/99999999999999999999')->assertNotFound(); // beyond bigint
    }

    public function test_update_note_partially_and_clear_stale_summary(): void
    {
        $note = Note::factory()->for($this->user)->create(['title' => 'Old', 'content' => 'Old body']);
        $note->forceFill(['summary' => 'Old summary', 'summary_generated_at' => now()])->save();

        $this->patchJson("/api/notes/{$note->id}", ['content' => 'New body'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Old')
            ->assertJsonPath('data.content', 'New body')
            ->assertJsonPath('data.summary', null);
    }

    public function test_update_requires_at_least_one_field(): void
    {
        $note = Note::factory()->for($this->user)->create();

        $this->putJson("/api/notes/{$note->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_delete_note(): void
    {
        $note = Note::factory()->for($this->user)->create();

        $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();
        $this->getJson("/api/notes/{$note->id}")->assertNotFound();
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    public function test_list_is_paginated_newest_first_and_only_shows_own_notes(): void
    {
        foreach (range(1, 15) as $i) {
            Note::factory()->for($this->user)->create(['title' => "Note {$i}", 'updated_at' => now()->addMinutes($i)]);
        }
        Note::factory()->for(User::factory())->create(['title' => 'Someone else']);

        $this->getJson('/api/notes?page=2&limit=10')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.title', 'Note 5')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->getJson('/api/notes')->assertJsonPath('data.0.title', 'Note 15')->assertJsonCount(10, 'data');
    }

    public function test_list_validates_pagination_params(): void
    {
        $this->getJson('/api/notes?limit=101')->assertUnprocessable()->assertJsonValidationErrors(['limit']);
        $this->getJson('/api/notes?page=0')->assertUnprocessable()->assertJsonValidationErrors(['page']);
    }

    public function test_sql_injection_attempts_are_treated_as_data(): void
    {
        $payload = "'); DROP TABLE notes; --";

        $this->postJson('/api/notes', ['title' => $payload, 'content' => $payload])->assertCreated();
        $this->getJson('/api/notes/search?q='.urlencode($payload))->assertOk();

        $this->assertDatabaseHas('notes', ['title' => $payload]);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->getJson('/api/notes')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
