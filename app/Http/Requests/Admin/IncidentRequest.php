<?php

namespace App\Http\Requests\Admin;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        $incident = $this->route('incident');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('incidents', 'slug')->ignore($incident)],
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'affected_service' => ['required', 'string', 'max:255'],
            'impact_summary' => ['required', 'string', 'max:2000'],
            'summary' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'customer_impact' => ['nullable', 'string'],
            'resolution' => ['nullable', 'string'],
            'next_steps' => ['nullable', 'string'],
            'final_status' => ['nullable', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_published' => ['nullable', 'boolean'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }
}
