<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class RemoderationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'moderation_log_id' => 'required|integer|exists:moderation_logs,id',
        ];
    }
}
