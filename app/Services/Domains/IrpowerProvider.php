<?php

namespace App\Services\Domains;

use App\Models\AppSetting;
use App\Models\DomainOrder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class IrpowerProvider
{
    public const URL = 'https://resellerarea.net/api';

    public function request(string $command, array $parameters = []): array
    {
        $key = AppSetting::irpowerKey();
        if ($key === '') {
            throw new RuntimeException('IRPOWER is not configured.');
        }
        // No retries: a lost response never gives permission to repeat a purchase.
        try {
            $response = Http::withHeaders(['X-Api-Key' => $key])->acceptJson()->asJson()
                ->connectTimeout(5)->timeout(25)->withoutRedirecting()
                ->post(self::URL, ['command' => $command] + $parameters);
        } catch (ConnectionException) {
            throw new RuntimeException('IRPOWER connection could not be confirmed.');
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! array_key_exists('success', $data)
            || ! in_array($data['success'], [true, false, 'true', 'false'], true)) {
            throw new RuntimeException('IRPOWER response could not be confirmed.');
        }
        if (in_array($data['success'], [false, 'false'], true)) {
            $code = (int) (data_get($data, 'error.code', data_get($data, 'errors.0.code', 0)));
            throw new ProviderRejected('IRPOWER rejected the request.', $code);
        }

        return is_array($data['result'] ?? null) ? $data['result'] : [];
    }

    public function validateHandles(array $registrant): void
    {
        foreach (collect(['holder', 'admin', 'tech', 'billing'])->groupBy(fn ($role) => $registrant[$role] ?? '')->all() as $handle => $roles) {
            if ($handle === '') {
                throw ValidationException::withMessages(['holder' => 'شناسه ایرنیک را وارد کنید.']);
            }
            try {
                $info = $this->request('GetContactInfo', ['irnic_handle' => $handle]);
            } catch (ProviderRejected) {
                throw ValidationException::withMessages(['holder' => 'شناسه ایرنیک قابل تأیید نیست. شناسه و دسترسی نمایندگان در nic.ir را بررسی کنید.']);
            }
            foreach ($roles as $role) {
                $remoteRole = $role === 'billing' ? 'bill' : $role;
                if (! in_array(data_get($info, 'relations.'.$remoteRole), [true, 'true'], true)) {
                    throw ValidationException::withMessages([$role => 'دسترسی این شناسه برای نقش انتخاب‌شده تأیید نشد. در nic.ir، قسمت رابطه‌های مجاز، دسترسی نمایندگان را فعال کنید و دوباره ادامه دهید.']);
                }
            }
        }
    }

    public function submit(DomainOrder $order): array
    {
        $parameters = ['domain' => $order->domain, 'period' => $order->period];
        if ($order->operation === 'register') {
            $parameters['nameservers'] = $this->nameserverPayload($order->nameservers);
            if ($order->contact_mode === 'registry_handle') {
                $parameters['fields'] = [
                    'irnic_holder_handle' => $order->registrant['holder'],
                    'irnic_admin_handle' => $order->registrant['admin'],
                    'irnic_tech_handle' => $order->registrant['tech'],
                    'irnic_bill_handle' => $order->registrant['billing'],
                ];
            } else {
                $details = $order->registrant;
                $name = preg_split('/\s+/u', trim($details['name']), 2);
                $parameters['contact'] = [
                    'first_name' => $name[0], 'last_name' => $name[1] ?? '',
                    'company_name' => $details['organization'] ?? '', 'email' => $details['email'],
                    'phone' => str_replace('.', '', $details['phone']), 'fax' => str_replace('.', '', $details['fax'] ?? $details['phone']),
                    'address' => $details['address'], 'city' => $details['city'], 'state' => $details['state'],
                    'postcode' => $details['postCode'], 'country' => $details['country'],
                ];
            }
        }

        return $this->request($order->operation === 'register' ? 'RegisterDomain' : 'RenewDomain', $parameters);
    }

    public function nameserverPayload(array $nameservers): array
    {
        if (count($nameservers) < 2 || count($nameservers) > 5) {
            throw ValidationException::withMessages(['nameservers' => 'بین دو تا پنج نیم‌سرور وارد کنید.']);
        }

        return array_combine(array_map(fn ($i) => 'ns'.$i, range(1, count($nameservers))), array_values($nameservers));
    }

    public function nameservers(string $domain): array
    {
        $data = $this->request('GetDomainNameServers', compact('domain'));
        $nameservers = [];
        foreach (range(1, 5) as $index) {
            if (! empty($data['ns'.$index]) && is_string($data['ns'.$index])) {
                $nameservers[] = strtolower(rtrim($data['ns'.$index], '.'));
            }
        }
        if (count($nameservers) < 2) {
            throw new RuntimeException('IRPOWER nameservers could not be confirmed.');
        }

        return $nameservers;
    }
}
