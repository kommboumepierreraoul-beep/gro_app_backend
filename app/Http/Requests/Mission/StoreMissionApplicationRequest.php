<?php

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use App\Models\MissionApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator;

class StoreMissionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // contrôle via middleware auth:sanctum
    }

    public function rules(): array
    {
        return [
            'mission_ulid'   => 'required|string|exists:missions,ulid',
            'method'         => 'required|in:form,app_message,whatsapp,email',
            'form_responses' => 'nullable|string', // JSON encodé depuis FormData
            'motivation'     => 'nullable|string|max:2000',
            'attachments'    => 'nullable|array|max:5',
            'attachments.*'  => [
                'file',
                'max:5120', // 5 Mo
                'mimes:jpg,jpeg,png,pdf,doc,docx',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mission_ulid.exists'  => 'Cette mission n\'existe pas ou plus.',
            'method.in'            => 'Méthode de candidature invalide.',
            'motivation.max'       => 'Le mot de motivation ne doit pas dépasser 2000 caractères.',
            'attachments.max'      => 'Maximum 5 pièces jointes.',
            'attachments.*.max'    => 'Chaque fichier doit faire moins de 5 Mo.',
            'attachments.*.mimes'  => 'Formats acceptés : jpg, jpeg, png, pdf, doc, docx.',
        ];
    }

    /**
     * Validation métier supplémentaire (au-delà des simples règles de champs).
     * Exécutée après les rules() ci-dessus.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ulid = $this->input('mission_ulid');
            if (!$ulid) return;

            $mission = Mission::where('ulid', $ulid)->first();
            if (!$mission) return; // déjà couvert par exists:missions,ulid

            // Mission ouverte aux candidatures ?
            if (!$mission->isOpen()) {
                $v->errors()->add('mission_ulid', 'Cette mission n\'accepte plus de candidatures.');
                return;
            }

            // Pas sa propre mission
            if ($mission->isOwnedBy($this->user())) {
                $v->errors()->add('mission_ulid', 'Vous ne pouvez pas postuler à votre propre mission.');
                return;
            }

            // Pas déjà candidat (hors withdrawn)
            $alreadyApplied = MissionApplication::where('mission_id', $mission->id)
                ->where('applicant_id', $this->user()->id)
                ->whereNotIn('status', [MissionApplication::STATUS_WITHDRAWN])
                ->exists();

            if ($alreadyApplied) {
                $v->errors()->add('mission_ulid', 'Vous avez déjà postulé à cette mission.');
                return;
            }

            // Pièces jointes autorisées par la mission ?
            if ($this->hasFile('attachments') && !$mission->allow_attachments) {
                $v->errors()->add('attachments', 'Cette mission n\'accepte pas de pièces jointes.');
                return;
            }

            // Validation des champs requis du formulaire personnalisé
            if ($this->filled('form_responses')) {
                $responses = json_decode($this->input('form_responses'), true) ?? [];

                foreach ($mission->application_form ?? [] as $field) {
                    if (($field['required'] ?? false) && empty($responses[$field['id']])) {
                        $v->errors()->add(
                            "form_responses.{$field['id']}",
                            "Le champ \"{$field['label']}\" est requis."
                        );
                    }
                }
            } else {
                // Aucune réponse fournie : vérifier s'il y a des champs requis
                foreach ($mission->application_form ?? [] as $field) {
                    if ($field['required'] ?? false) {
                        $v->errors()->add(
                            "form_responses.{$field['id']}",
                            "Le champ \"{$field['label']}\" est requis."
                        );
                    }
                }
            }
        });
    }

    /**
     * Récupère la mission validée (évite une requête supplémentaire dans le contrôleur).
     */
    public function getMission(): Mission
    {
        return Mission::where('ulid', $this->input('mission_ulid'))
            ->with('author')
            ->firstOrFail();
    }

    /**
     * Décode les réponses du formulaire personnalisé.
     */
    public function getFormResponses(): array
    {
        if (!$this->filled('form_responses')) return [];
        return json_decode($this->input('form_responses'), true) ?? [];
    }
}
