<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $token = $request->user('customer')->createToken($data['name'], ['wallet:read']);

        return back()->with('api_token', $token->plainTextToken)->with('status', 'کلید API ساخته شد. آن را همین حالا ذخیره کنید؛ بعدا دوباره نمایش داده نمی‌شود.');
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $request->user('customer')->tokens()->whereKey($token)->delete();

        return back()->with('status', 'کلید API با موفقیت لغو شد.');
    }
}
