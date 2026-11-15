<?php

namespace App\Notifications;

use App\Models\VirtualMachine;
use Illuminate\Notifications\Notification;

class VmDeletionBlockedNotification extends Notification
{
    public function __construct(private readonly VirtualMachine $vm) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'vm_deletion_blocked',
            'title' => 'حذف خودکار سرور متوقف شد',
            'body' => $this->vm->name.' — '.$this->vm->delete_error,
            'url' => route('admin.virtual-machines.show', $this->vm, false),
        ];
    }
}
