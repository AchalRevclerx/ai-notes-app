<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchNotesRequest;
use App\Http\Resources\NoteResource;
use App\Services\Ai\Embedder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NoteSearchController extends Controller
{
    private const QUERY_EMBEDDING_TTL_SECONDS = 86400;

    /**
     * Semantic search: embed the query and rank the user's notes by cosine
     * similarity (pgvector HNSW index). Matches meaning, not just keywords.
     */
    public function __invoke(SearchNotesRequest $request, Embedder $embedder): AnonymousResourceCollection
    {
        $query = $request->string('q')->squish()->value();
        $minSimilarity = (float) config('ai.search_min_similarity');

        // Repeated searches skip the embedding API call (Redis in Docker).
        $cacheKey = 'query-embedding:'.sha1(config('ai.driver').'|'.config('ai.embedding_model').'|'.mb_strtolower($query));
        $vector = Cache::remember($cacheKey, self::QUERY_EMBEDDING_TTL_SECONDS, fn () => $embedder->embed($query));

        $notes = DB::transaction(function () use ($request, $vector, $minSimilarity) {
            // The HNSW index returns the global nearest neighbours and the user_id filter
            // runs afterwards, so a user could get too few hits on a big table. Iterative
            // scan (pgvector >= 0.8) keeps walking the graph until enough rows pass.
            DB::statement('SET LOCAL hnsw.iterative_scan = strict_order');

            return $request->user()->notes()
                ->publicColumns()
                ->selectVectorDistance('embedding', $vector, as: 'distance')
                ->whereVectorSimilarTo('embedding', $vector, minSimilarity: $minSimilarity)
                ->limit($request->integer('limit', SearchNotesRequest::DEFAULT_LIMIT))
                ->get();
        });

        return NoteResource::collection($notes)->additional(['meta' => [
            'query' => $query,
            'count' => $notes->count(),
            'min_similarity' => $minSimilarity,
            /** Notes still waiting for an embedding are not searchable yet. */
            'pending_index' => $request->user()->notes()->whereNull('embedded_at')->count(),
        ]]);
    }
}
