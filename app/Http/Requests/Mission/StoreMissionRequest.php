<?php

namespace App\Http\Requests\Mission;

use Illuminate\Foundation\Http\FormRequest;

class StoreMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // contrôle via middleware auth:sanctum
    }

    public function rules(): array
    {
        return [
            // Identité
            'category_id'              => 'nullable|integer|exists:mission_categories,id',
            'title'                    => 'required|string|min:5|max:255',
            'description'              => 'required|string|min:20|max:5000',
            'desired_profile'          => 'nullable|string|max:1000',

            // Durée
            'duration_type'            => 'required|in:hours,day,days,weeks,flexible',
            'duration_value'           => 'nullable|required_unless:duration_type,flexible|integer|min:1|max:365',
            'start_date'               => 'nullable|date|after_or_equal:today',
            'expires_at'               => 'nullable|date|before:' . now()->addMonths(6)->toDateString(),

            // Localisation
            'latitude'                 => 'nullable|numeric|between:-90,90',
            'longitude'                => 'nullable|numeric|between:-180,180',
            'location_label'           => 'nullable|string|max:255',
            'diffusion_radius_km'      => 'nullable|integer|min:1|max:500',
            'diffusion_scope'          => 'nullable|in:radius,platform',

            // Rémunération
            'remuneration_type'        => 'required|in:fixed,daily_rate,hourly_rate,negotiable,in_kind,volunteer',
            'remuneration_amount'      => 'nullable|required_if:remuneration_type,fixed,daily_rate,hourly_rate|numeric|min:0|max:99999999',
            'remuneration_currency'    => 'nullable|string|size:3',
            'remuneration_conditions'  => 'nullable|string|max:1000',

            // Candidature
            'contact_methods'                  => 'nullable|array|max:5',
            'contact_methods.*.type'           => 'required|in:app_message,whatsapp,email,instagram,facebook',
            'contact_methods.*.value'          => 'nullable|string|max:255',
            'allow_attachments'                => 'boolean',
            'max_applications'                 => 'nullable|integer|min:1|max:1000',

            // Formulaire personnalisé
            'application_form'                 => 'nullable|array|max:10',
            'application_form.*.id'            => 'required|string|max:50',
            'application_form.*.label'         => 'required|string|max:255',
            'application_form.*.type'          => 'required|in:text,textarea,boolean,select,number',
            'application_form.*.required'      => 'boolean',
            'application_form.*.options'       => 'nullable|array|max:10',
            'application_form.*.options.*'     => 'string|max:100',

            // État initial
            'status'                   => 'nullable|in:draft,published',
        ];
    }

    public function messages(): array
    {
        return [
            'title.min'             => 'Le titre doit faire au moins 5 caractères.',
            'description.min'       => 'La description doit faire au moins 20 caractères.',
            'remuneration_type.in'  => 'Type de rémunération invalide.',
            'start_date.after_or_equal' => 'La date de début doit être aujourd\'hui ou dans le futur.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliser le statut (défaut: draft)
        if (!$this->has('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Normaliser la devise (défaut: XAF)
        if (!$this->has('remuneration_currency')) {
            $this->merge(['remuneration_currency' => 'XAF']);
        }

        // Normaliser le rayon (défaut: 25)
        if (!$this->has('diffusion_radius_km')) {
            $this->merge(['diffusion_radius_km' => 25]);
        }

        // Décoder les champs JSON envoyés en string depuis FormData
        foreach (['contact_methods', 'application_form'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                $this->merge([$field => $decoded ?? []]);
            }
        }
    }
}
