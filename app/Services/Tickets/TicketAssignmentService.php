<?php

namespace App\Services\Tickets;

use App\Models\SupportTeam;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketAssignmentService
{
    public function teamFor(?TicketCategory $category): ?SupportTeam
    {
        return $category?->supportTeam?->is_active ? $category->supportTeam : null;
    }

    public function autoAssign(?TicketCategory $category): ?User
    {
        $team = $this->teamFor($category);

        if (! $team || $category?->assignment_strategy !== TicketCategory::ASSIGNMENT_ROUND_ROBIN) {
            return null;
        }

        /** @var Collection<int, User> $agents */
        $agents = $team->activeUsers()->get();
        if ($agents->isEmpty()) {
            return null;
        }

        $lastUserId = $team->round_robin_user_id;
        $next = $agents->first();

        if ($lastUserId) {
            $lastIndex = $agents->search(fn (User $agent): bool => (int) $agent->id === (int) $lastUserId);
            if ($lastIndex !== false) {
                $next = $agents->get(((int) $lastIndex + 1) % $agents->count()) ?? $next;
            }
        }

        $team->forceFill(['round_robin_user_id' => $next?->id])->save();

        return $next;
    }
}
