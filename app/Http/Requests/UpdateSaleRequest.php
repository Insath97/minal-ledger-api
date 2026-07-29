<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSaleRequest extends FormRequest
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
        $rules = [
            'business_type' => 'sometimes|required|in:retail,wholesale',
            'customer_id' => 'nullable|exists:customers,id',
            'invoice_number' => 'nullable|string|max:100|unique:sales,invoice_number,' . $this->route('sale'),
            'total_amount' => 'sometimes|required|numeric|min:0.01',
            'paid_amount' => 'sometimes|numeric|min:0',
            'sale_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|in:cash,credit_card,bank_transfer,cheque',
            'cheque_number' => 'required_if:payment_method,cheque|nullable|string|max:50',
            'bank_name' => 'required_if:payment_method,cheque|nullable|string|max:100',
            'cheque_date' => 'required_if:payment_method,cheque|nullable|date',
            'cheque_amount' => 'required_if:payment_method,cheque|nullable|numeric|min:0.01',
        ];

        if ($this->hasFile('bill_image')) {
            $rules['bill_image'] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
        } else {
            $rules['bill_image'] = 'nullable|string';
        }

        if ($this->hasFile('cheque_image')) {
            $rules['cheque_image'] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
        } else {
            $rules['cheque_image'] = 'nullable|string';
        }

        return $rules;
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
