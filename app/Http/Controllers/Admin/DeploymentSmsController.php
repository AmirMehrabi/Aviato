<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DeploymentSmsSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PDO;

class DeploymentSmsController extends Controller
{
    public function index(DeploymentSmsSettings $settings)
    {
        $deliveries = $settings->database()->query('SELECT * FROM deliveries ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);

        return view('admin.settings.deployment-sms', ['settings' => $settings->read(), 'deliveries' => $deliveries, 'events' => DeploymentSmsSettings::EVENTS]);
    }

    public function update(Request $request, DeploymentSmsSettings $settings)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'], 'recipients' => ['nullable', 'string', 'max:1000'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'events' => ['required', 'array:started,success,failure,rollback,recovery'],
            'events.*.enabled' => ['nullable', 'boolean'],
            'events.*.template' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $phones = array_values(array_unique(preg_split('/[\s,،]+/u', trim($data['recipients'] ?? ''), flags: PREG_SPLIT_NO_EMPTY)));
        if (count($phones) > 20 || collect($phones)->contains(fn ($phone) => ! preg_match('/^09[0-9]{9}$/', $phone))) {
            throw ValidationException::withMessages(['recipients' => 'حداکثر ۲۰ شماره با قالب 09xxxxxxxxx وارد کنید.']);
        }
        $events = [];
        foreach (DeploymentSmsSettings::EVENTS as $event) {
            $enabled = $request->boolean('events.'.$event.'.enabled');
            $template = $data['events'][$event]['template'] ?? '';
            if ($enabled && $template === '') {
                throw ValidationException::withMessages(['events.'.$event.'.template' => 'برای رویداد فعال، قالب تأییدشده انتخاب کنید.']);
            }
            $events[$event] = ['enabled' => $enabled, 'template' => $template];
        }
        $settings->save(['enabled' => $request->boolean('enabled'), 'recipients' => $phones, 'max_attempts' => (int) $data['max_attempts'], 'events' => $events]);

        return back()->with('status', 'تنظیمات پیامک استقرار ذخیره شد.');
    }

    public function action(Request $request, DeploymentSmsSettings $settings)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['retry', 'cancel', 'test'])], 'id' => ['required_unless:action,test', 'integer'], 'event' => ['required_if:action,test', Rule::in(DeploymentSmsSettings::EVENTS)]]);
        $db = $settings->database();
        if ($data['action'] === 'test') {
            $config = $settings->read();
            $event = $data['event'];
            if (! $config['enabled'] || empty($config['events'][$event]['enabled']) || empty($config['events'][$event]['template']) || $config['recipients'] === []) {
                throw ValidationException::withMessages(['event' => 'ابتدا ارسال، رویداد، گیرندگان و قالب تأییدشده را ذخیره کنید.']);
            }
            $statement = $db->prepare('INSERT INTO deliveries(event,revision,release,phone,template,created_at) VALUES(?,?,?,?,?,?)');
            foreach ($config['recipients'] as $phone) {
                $statement->execute([$event, 'test-'.bin2hex(random_bytes(8)), 'test', $phone, $config['events'][$event]['template'], time()]);
            }
        } else {
            $sql = $data['action'] === 'cancel'
                ? "UPDATE deliveries SET status='cancelled' WHERE id=? AND status IN ('pending','failed')"
                : "UPDATE deliveries SET status='pending',attempts=0,next_attempt=0,error=NULL WHERE id=? AND status IN ('failed','cancelled')";
            $db->prepare($sql)->execute([$data['id']]);
        }

        return back()->with('status', 'عملیات ثبت شد. ارسال آزمایشی نیز در تاریخچه نمایش داده می‌شود.');
    }
}
