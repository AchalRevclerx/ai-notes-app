<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotePolicy
{
    /**
     * Owners can do everything with their notes. Everyone else gets a 404,
     * so the API never confirms that someone else's note id exists.
     */
    public function manage(User $user, Note $note): Response
    {
        return $user->id === $note->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
