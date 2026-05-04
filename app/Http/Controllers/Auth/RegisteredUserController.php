<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(string $portal): View
    {
        return view('auth.register', ['portal' => $portal]);
    }

    public function store(Request $request, string $portal): RedirectResponse
    {
        $model = $portal === 'admin' ? User::class : Customer::class;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique($model, 'email')],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,29}$/', Rule::unique($model, 'phone')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var Model $account */
        $account = $model::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        Auth::guard($portal)->login($account);
        $request->session()->regenerate();

        return redirect($this->portalUrl($portal, config("portals.$portal.home_path")));
    }

    private function portalUrl(string $portal, string $path): string
    {
        $domain = config("portals.$portal.domain");
        $path = '/'.trim($path, '/');

        return $domain ? request()->getScheme().'://'.$domain.$path : $path;
    }
}
