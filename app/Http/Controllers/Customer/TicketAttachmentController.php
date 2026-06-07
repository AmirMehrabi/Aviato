<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    public function show(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless((int) $ticket->customer_id === (int) $request->user('customer')->id, 404);
        abort_unless((int) $attachment->message?->ticket_id === (int) $ticket->id, 404);
        abort_if($attachment->message?->type !== TicketMessage::TYPE_REPLY, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
