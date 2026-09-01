<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkspaceAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly Customer $addedBy,
        private readonly string $role,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $role = match ($this->role) {
            ProjectMember::ROLE_ADMIN => 'مدیر',
            ProjectMember::ROLE_VIEWER => 'فقط مشاهده',
            ProjectMember::ROLE_BILLING => 'مالی',
            default => 'عضو',
        };

        return [
            'event' => 'workspace_added',
            'project_id' => $this->project->id,
            'title' => 'به فضای کاری جدیدی اضافه شدید',
            'body' => "{$this->addedBy->name} شما را با نقش {$role} به «{$this->project->name}» اضافه کرد.",
            'url' => route('customer.projects.enter', $this->project, false),
        ];
    }
}
