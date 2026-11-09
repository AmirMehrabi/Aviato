<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\VmConsoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VirtualMachineConsoleController extends Controller
{
    public function __construct(private readonly VmConsoleService $console) {}

    public function show(Request $request, VirtualMachine $virtualMachine): View
    {
        return view('admin.virtual-machines.console', [
            'vm' => $virtualMachine->loadMissing(['customer', 'project.owner', 'proxmoxServer']),
            'consoleSessionUrl' => route('admin.virtual-machines.console.session', $virtualMachine, false),
            'powerActions' => $this->console->powerActions($virtualMachine, 'admin', $request->user('admin')->allows('virtual-machines.power')),
            'consoleHistory' => $virtualMachine->activities()->where('event', 'console.session')->latest('id')->limit(20)->get(),
        ]);
    }

    public function session(Request $request, VirtualMachine $virtualMachine): JsonResponse
    {
        return $this->console->session($request, $virtualMachine->loadMissing('proxmoxServer'), 'admin');
    }
}
