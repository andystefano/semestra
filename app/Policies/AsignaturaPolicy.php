<?php

namespace App\Policies;

use App\Models\Asignatura;
use App\Models\User;

class AsignaturaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asignatura $asignatura): bool
    {
        return $asignatura->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Asignatura $asignatura): bool
    {
        return $asignatura->user_id === $user->id;
    }

    public function delete(User $user, Asignatura $asignatura): bool
    {
        return $asignatura->user_id === $user->id;
    }
}
