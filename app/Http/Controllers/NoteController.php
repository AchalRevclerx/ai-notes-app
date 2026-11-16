<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListNotesRequest;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Ownership of {note} is enforced by NotePolicy@manage on the routes (404 for other users). */
class NoteController extends Controller
{
    /**
     * List the user's notes, newest first, paginated with `page` and `limit`.
     */
    public function index(ListNotesRequest $request): AnonymousResourceCollection
    {
        $notes = $request->user()->notes()
            ->publicColumns()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($request->integer('limit', ListNotesRequest::DEFAULT_LIMIT))
            ->withQueryString();

        return NoteResource::collection($notes);
    }

    /**
     * Create a note. Its embedding is generated in the background.
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return NoteResource::make($note->refresh())->response()->setStatusCode(201);
    }

    /**
     * Get a single note.
     */
    public function show(Note $note): NoteResource
    {
        return NoteResource::make($note);
    }

    /**
     * Update a note's title and/or content. Clears its summary and re-indexes it.
     */
    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $note->update($request->validated());

        return NoteResource::make($note->refresh());
    }

    /**
     * Delete a note.
     */
    public function destroy(Note $note): Response
    {
        $note->delete();

        return response()->noContent();
    }
}
