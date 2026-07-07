<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Services\ProjectAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerVmAccess
{
    public function __construct(
        private readonly ProjectAccessService $projects,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        $activeProject = $customer instanceof Customer
            ? $this->projects->activeProject($request, $customer)
            : null;

        if ($customer instanceof Customer && $activeProject && $this->projects->canViewVms($activeProject, $customer)) {
            return $next($request);
        }

        $message = $customer instanceof Customer && $activeProject && $this->projects->canViewBilling($activeProject, $customer)
            ? 'این بخش برای نقش مالی در دسترس نیست. شما به داشبورد و کیف پول دسترسی دارید.'
            : 'این بخش برای حساب شما در دسترس نیست.';

        return redirect()
            ->route('dashboard')
            ->with('error', $message);
    }
}
