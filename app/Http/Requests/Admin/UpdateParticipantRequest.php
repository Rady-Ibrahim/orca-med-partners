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
            'code' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('participants', 'code')->ignore($participantId)],
            'email' => ['nullable', 'string', 'max:255', Rule::unique('participants', 'email')->ignore($participantId)],
            'status' => ['sometimes', 'required', 'string', Rule::in(['active', 'inactive'])],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['sometimes', 'required_with:password', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'اسم المستخدم مستخدم بالفعل',
            'username.regex' => 'اسم المستخدم يجب أن يحتوي على أحرف وأرقام ونقاط أو شرطات فقط',
            'code.required' => 'كود المشارك مطلوب',
            'code.unique' => 'كود المشارك مستخدم بالفعل',
            'code.regex' => 'كود المشارك يجب أن يحتوي على أحرف وأرقام ونقاط أو شرطات فقط',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
        ];
    }
}
