@csrf
<div class="grid gap-4 md:grid-cols-2">
    <x-form.input name="name" label="Account name" :value="$account->name" />
    <x-form.input name="api_token" type="password" label="Hetzner API token" value="" dir-ltr :help="$account->exists ? 'Leave empty to keep the current token.' : 'Create this token in the Hetzner Cloud project API settings.'" />
    <x-form.checkbox name="is_active" label="Active" :checked="old('is_active', $account->is_active ?? true)" />
    <x-form.checkbox name="maintenance_mode" label="Maintenance mode" :checked="old('maintenance_mode', $account->maintenance_mode ?? false)" />
</div>
<div class="mt-6 flex gap-3">
    <button class="rounded-lg bg-[#0069FF] px-5 py-3 text-sm font-black text-white">ذخیره</button>
    <a href="{{ route('admin.hetzner-accounts.index') }}" class="rounded-lg border border-slate-200 px-5 py-3 text-sm font-black text-slate-700">Back</a>
</div>
