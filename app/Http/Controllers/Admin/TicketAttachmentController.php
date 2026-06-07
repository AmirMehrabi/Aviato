<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless((int) $attachment->message?->ticket_id === (int) $ticket->id, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
