<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ai\LocalAiProvider;
use PHPUnit\Framework\TestCase;

class LocalAiProviderTest extends TestCase
{
    private LocalAiProvider $ai;

    protected function setUp(): void
    {
        $this->ai = new LocalAiProvider(768);
    }

    public function test_embedding_is_a_deterministic_unit_vector(): void
    {
        $vector = $this->ai->embed('Invest in index funds every month');

        $this->assertCount(768, $vector);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector))), 1e-9);
        $this->assertSame($vector, $this->ai->embed('Invest in index funds every month'));
    }

    public function test_related_text_is_closer_than_unrelated_text(): void
    {
        $query = $this->ai->embed('investing in mutual funds');
        $related = $this->ai->embed('My mutual fund investment plan for 2026');
        $unrelated = $this->ai->embed('Chicken biryani recipe with saffron rice');

        $this->assertGreaterThan($this->cosine($query, $unrelated), $this->cosine($query, $related));
    }

    public function test_devanagari_words_keep_their_vowel_signs(): void
    {
        // Splitting on matras would shred "निवेश" into 1-letter fragments and yield a zero vector.
        $word = $this->ai->embed('निवेश');

        $this->assertEqualsWithDelta(1.0, $this->cosine($word, $word), 1e-9);
        $this->assertGreaterThan(
            $this->cosine($word, $this->ai->embed('खाना पकाना')),
            $this->cosine($word, $this->ai->embed('निवेश योजना')),
        );
    }

    public function test_short_note_summary_is_the_note_itself(): void
    {
        $this->assertSame('Buy milk. Call mom.', $this->ai->summarize('Todo', "Buy milk.\nCall mom."));
    }

    public function test_long_note_summary_keeps_three_key_sentences_in_order(): void
    {
        $content = 'The release is planned for Friday. Lunch was nice. The release needs QA sign-off. '
            .'Weather was sunny. Release notes must mention the API change. Someone brought cake.';

        $summary = $this->ai->summarize('Release plan', $content);

        $this->assertSame(
            'The release is planned for Friday. The release needs QA sign-off. Release notes must mention the API change.',
            $summary,
        );
    }

    /** @param list<float> $a @param list<float> $b */
    private function cosine(array $a, array $b): float
    {
        return array_sum(array_map(fn ($x, $y) => $x * $y, $a, $b));
    }
}
