<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact', [
            'needTypes' => $this->needTypes(),
            'teamSizes' => $this->teamSizes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $needTypes = array_keys($this->needTypes());
        $teamSizes = array_keys($this->teamSizes());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'need_type' => ['required', 'string', Rule::in($needTypes)],
            'team_size' => ['required', 'string', Rule::in($teamSizes)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactSubmission::create([
            ...$data,
            'status' => ContactSubmission::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->route('contact')
            ->withInput($request->only(['name', 'email', 'phone']))
            ->with('status', 'درخواست شما ثبت شد. تیم آویاتو در اولین فرصت با شما تماس می گیرد.');
    }

    private function needTypes(): array
    {
        return [
            'cloud-vps' => 'خرید VPS ابری',
            'migration' => 'مهاجرت به زیرساخت آویاتو',
            'backup-database' => 'دیتابیس، بکاپ و پایداری',
            'technical-consulting' => 'مشاوره فنی و ظرفیت سنجی',
        ];
    }

    private function teamSizes(): array
    {
        return [
            '1-5' => '۱ تا ۵ نفر',
            '6-20' => '۶ تا ۲۰ نفر',
            '21-50' => '۲۱ تا ۵۰ نفر',
            '50+' => 'بیشتر از ۵۰ نفر',
        ];
    }
}
