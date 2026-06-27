<?php

namespace App\Http\Requests\Mission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Mêmes règles que Store mais tout est optionnel (PATCH/PUT partiel)
        return [
            'category_id'              => 'sometimes|nullable|integer|exists:mission_categories,id',
            'title'                    => 'sometimes|required|string|min:5|max:255',
            'description'              => 'sometimes|required|string|min:20|max:5000',
            'desired_profile'          => 'sometimes|nullable|string|max:1000',
            'duration_type'            => 'sometimes|required|in:hours,day,days,weeks,flexible',
            'duration_value'           => 'sometimes|nullable|integer|min:1|max:365',
            'start_date'               => 'sometimes|nullable|date',
            'expires_at'               => 'sometimes|nullable|date',
            'latitude'                 => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'                => 'sometimes|nullable|numeric|between:-180,180',
            'location_label'           => 'sometimes|nullable|string|max:255',
            'diffusion_radius_km'      => 'sometimes|nullable|integer|min:1|max:500',
            'diffusion_scope'          => 'sometimes|nullable|in:radius,platform',
            'remuneration_type'        => 'sometimes|required|in:fixed,daily_rate,hourly_rate,negotiable,in_kind,volunteer',
            'remuneration_amount'      => 'sometimes|nullable|numeric|min:0',
            'remuneration_currency'    => 'sometimes|nullable|string|size:3',
            'remuneration_conditions'  => 'sometimes|nullable|string|max:1000',
            'contact_methods'          => 'sometimes|nullable|array|max:5',
            'contact_methods.*.type'   => 'required|in:app_message,whatsapp,email,instagram,facebook',
            'contact_methods.*.value'  => 'nullable|string|max:255',
            'allow_attachments'        => 'sometimes|boolean',
            'max_applications'         => 'sometimes|nullable|integer|min:1',
            'application_form'         => 'sometimes|nullable|array|max:10',
            'application_form.*.id'    => 'required|string|max:50',
            'application_form.*.label' => 'required|string|max:255',
            'application_form.*.type'  => 'required|in:text,textarea,boolean,select,number',
            'application_form.*.required' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Décoder les champs JSON envoyés en string depuis FormData
        foreach (['contact_methods', 'application_form'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                $this->merge([$field => $decoded ?? []]);
            }
        }
    }
}
