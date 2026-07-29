<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = auth('api')->id();

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:100|unique:users,username,' . $id,
            'email' => 'nullable|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:6',
            'confirm_password' => 'nullable|required_with:password|string|same:password',
        ];

        if ($this->hasFile('profile_image')) {
            $rules['profile_image'] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
        } else {
            $rules['profile_image'] = 'nullable|string';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = auth('api')->user();
            $currentPassword = $this->input('current_password');

            if ($this->filled('password') && $user) {
                if (!$currentPassword || !\Illuminate\Support\Facades\Hash::check($currentPassword, $user->password)) {
                    $validator->errors()->add('current_password', 'The current password you entered is incorrect.');
                }
            }
        });
    }

    /**
     * Handle failed validation and return a JSON error response.
     */
    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();
        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field' => $field,
                'messages' => $messages,
            ];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
