<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|min:1|max:50000',
            'conversation_id' => 'nullable|string|uuid|exists:ai_conversations,id',
            'stream' => 'nullable|boolean',
            'context_type' => ['nullable', 'string', Rule::in(['general', 'post', 'mission', 'comment'])],
            'context_id' => 'nullable|integer|required_with:context_type',
            'system_prompt' => 'nullable|string|max:1000',
            'context_data' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Le message est requis.',
            'message.max' => 'Le message ne peut pas dépasser 50000 caractères.',
            'conversation_id.exists' => 'La conversation n\'existe pas.',
            'context_id.required_with' => 'L\'ID de contexte est requis avec le type de contexte.',
            'context_type.in' => 'Le type de contexte doit être : general, post, mission ou comment.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('context_type') && !$this->has('context_id')) {
            $this->merge(['context_id' => null]);
        }

        if ($this->has('message')) {
            $this->merge([
                'message' => strip_tags($this->message),
            ]);
        }
    }
}
