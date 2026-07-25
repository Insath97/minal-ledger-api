<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'cheque_id' => 'nullable|integer|exists:cheques,id',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,credit_card,bank_transfer,cheque',
            'payment_date' => 'required|date',
            'proof_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'notes' => 'nullable|string',

            // Optional explicit array of sale IDs to settle. If omitted, system uses FIFO auto allocation.
            'sale_ids' => 'nullable|array',
            'sale_ids.*' => 'integer|exists:sales,id',
        ];
    }

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
