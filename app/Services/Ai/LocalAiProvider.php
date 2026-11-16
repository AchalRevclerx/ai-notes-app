<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Offline fallback: no network, no API key, fully deterministic.
 *
 * ponytail: lexical, not semantic. Embeddings are hashed words + character
 * trigrams, so "investing" matches "investment" but "car" won't match
 * "vehicle". Summaries are extractive (top sentences). Set AI_API_KEY to get
 * real semantic search and abstractive summaries.
 */
final class LocalAiProvider implements Embedder, Summarizer
{
    private const SUMMARY_SENTENCES = 3;

    private const TRIGRAM_WEIGHT = 0.3;

    private const TITLE_BOOST = 2;

    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'from', 'has', 'have',
        'i', 'in', 'is', 'it', 'its', 'me', 'my', 'of', 'on', 'or', 'our', 'so', 'that', 'the',
        'their', 'then', 'there', 'this', 'to', 'was', 'we', 'were', 'will', 'with', 'you', 'your',
    ];

    public function __construct(private readonly int $dimensions) {}

    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach ($this->tokens($text) as $word) {
            $this->addFeature($vector, $word, 1.0);

            $padded = " {$word} ";
            for ($i = 0, $n = mb_strlen($padded) - 2; $i < $n; $i++) {
                $this->addFeature($vector, mb_substr($padded, $i, 3), self::TRIGRAM_WEIGHT);
            }
        }

        $norm = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));

        return $norm > 0 ? array_map(fn (float $v) => $v / $norm, $vector) : $vector;
    }

    public function summarize(string $title, string $content): string
    {
        $sentences = array_values(array_filter(array_map(
            fn (string $s) => trim((string) preg_replace('/\s+/u', ' ', $s)),
            preg_split('/(?<=[.!?।])\s+|\R+/u', $content) ?: [],
        )));

        if (count($sentences) <= self::SUMMARY_SENTENCES) {
            return implode(' ', $sentences);
        }

        // Score sentences by how many of the note's frequent words they contain.
        $frequency = array_count_values($this->tokens($content));
        foreach ($this->tokens($title) as $word) {
            $frequency[$word] = ($frequency[$word] ?? 0) + self::TITLE_BOOST;
        }

        $scores = [];
        foreach ($sentences as $i => $sentence) {
            $tokens = $this->tokens($sentence);
            $scores[$i] = $tokens === []
                ? 0
                : array_sum(array_map(fn (string $t) => $frequency[$t] ?? 0, $tokens)) / count($tokens);
        }

        arsort($scores);
        $best = array_keys(array_slice($scores, 0, self::SUMMARY_SENTENCES, true));
        sort($best);

        return implode(' ', array_map(fn (int $i) => $sentences[$i], $best));
    }

    /** @return list<string> lowercase words; keeps Devanagari matras (\p{M}) inside words */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) > 1 && ! in_array($w, self::STOPWORDS, true),
        ));
    }

    /** Signed feature hashing: the sign bit keeps colliding features from only ever adding up. */
    private function addFeature(array &$vector, string $feature, float $weight): void
    {
        $hash = crc32($feature);
        $sign = (crc32('±'.$feature) & 1) === 1 ? 1.0 : -1.0;
        $vector[$hash % $this->dimensions] += $sign * $weight;
    }
}
