<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('email') && ($this->email === '' || $this->email === null)) {
            $this->merge(['email' => null]);
        }
    }

    public function rules(): array
    {
        $participantId = $this->route('participant')?->getKey();

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('participants', 'username')->ignore($participantId)],
            'email' => ['nullable', 'string', 'max:255', Rule::unique('participants', 'email')->ignore($participantId)],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'username.regex' => 'اسم المستخدم يجب أن يحتوي على أحرف وأرقام ونقاط أو شرطات فقط',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
        ];
    }
}