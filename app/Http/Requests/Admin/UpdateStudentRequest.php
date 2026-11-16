<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->route('student'); // Student model instance
        $userId = $student->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'nis' => ['required', 'string', 'max:20', Rule::unique('students', 'nis')->ignore($student->id)],
            'class' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama siswa wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS ini sudah terdaftar untuk siswa lain.',
            'class.required' => 'Kelas wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }
}
