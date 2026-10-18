<?php

namespace App\Http\Controllers;

use App\Services\ServiceStatus;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function __invoke(ServiceStatus $status): View
    {
        return view('status', $status->build());
    }
}
