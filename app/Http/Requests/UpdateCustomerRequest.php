<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class UpdateCustomerRequest extends FormRequest
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
        $id = $this->route('customer') ?? $this->route('id');

        $rules = [
            'code' => 'sometimes|required|string|max:50|unique:customers,code,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'id_type' => 'nullable|string|in:nic,driving,passport,other',
            'id_number' => 'nullable|required_with:id_type|string|max:50',
            'phone' => 'sometimes|required|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'outstanding_balance' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ];

        if ($this->hasFile('profile_image')) {
            $rules['profile_image'] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
        } else {
            $rules['profile_image'] = 'nullable|string';
        }

        if ($this->hasFile('nic_image')) {
            $rules['nic_image'] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
        } else {
            $rules['nic_image'] = 'nullable|string';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $id = $this->route('customer') ?? $this->route('id');
            $idType = $this->input('id_type');
            $idNumber = $this->input('id_number');

            if ($idType && $idNumber) {
                $exists = DB::table('customers')
                    ->where('id_type', $idType)
                    ->where('id_number', $idNumber)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('id_number', 'The combination of ID type and ID number already exists on another customer record.');
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
