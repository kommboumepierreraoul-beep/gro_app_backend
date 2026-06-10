<?php
// app/Http/Requests/AI/ChatRequest.php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permet l'accès même sans user pour le test, mais vérifie en prod
        // return $this->user() !== null;
        return true; // Temporaire pour test, à remettre en prod
    }

    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'min:1',
                'max:4000',
                // Protection contre les injections de prompt
                function (string $attribute, mixed $value, \Closure $fail) {
                    $dangerousPatterns = [
                        '/ignore\s+(all\s+)?(previous|above|prior)\s+instructions/i',
                        '/you\s+are\s+now\s+/i',
                        '/act\s+as\s+(a\s+)?(different|new|another)/i',
                        '/jailbreak/i',
                        '/DAN\s+mode/i',
                        '/\[SYSTEM\]/i',
                        '/\<\|system\|\>/i',
                    ];

                    foreach ($dangerousPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            $fail('Ce message contient du contenu non autorisé.');
                            return;
                        }
                    }
                },
            ],
            'session_id' => [
                'sometimes',        // Permet l'absence du champ
                'nullable',         // Permet null
                'string',
                Rule::uuid(),       // Valide uniquement si présent
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Le message est obligatoire.',
            'message.max' => 'Le message ne peut pas dépasser 4000 caractères.',
            'session_id.uuid' => 'L\'identifiant de session est invalide.',
        ];
    }

    /**
     * Prépare les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        // Si session_id n'est pas fourni, on ne fait rien
        // Le service s'en occupera
        if (!$this->has('session_id')) {
            return;
        }

        // Nettoie session_id si besoin
        $sessionId = $this->input('session_id');
        if ($sessionId === 'null' || $sessionId === 'undefined' || empty($sessionId)) {
            $this->offsetUnset('session_id');
        }
    }
}
