<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class ModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|min:1|max:50000',
            'model' => 'nullable|string|in:deepseek-chat,deepseek-coder,llama-3.3-70b-versatile',
            'strict_mode' => 'nullable|boolean',
        ];
    }
}
