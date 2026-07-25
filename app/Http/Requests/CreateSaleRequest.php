<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSaleRequest extends FormRequest
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
        return [
            'business_type' => 'required|in:retail,wholesale',
            'customer_id' => 'required_if:business_type,wholesale|nullable|exists:customers,id',
            'invoice_number' => 'nullable|string|max:100',
            'bill_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'total_amount' => 'required|numeric|min:0.01',
            'paid_amount' => 'sometimes|numeric|min:0|lte:total_amount',
            'payment_method' => 'nullable|in:cash,credit_card,bank_transfer,cheque',
            'sale_date' => 'required|date',
            'notes' => 'nullable|string',

            // Cheque details if payment_method = cheque
            'cheque_number' => 'required_if:payment_method,cheque|nullable|string|max:50',
            'bank_name' => 'required_if:payment_method,cheque|nullable|string|max:100',
            'cheque_date' => 'required_if:payment_method,cheque|nullable|date',
            'cheque_amount' => 'required_if:payment_method,cheque|nullable|numeric|min:0.01',
            'cheque_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }

    /**
     * Custom validation logic.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $businessType = $this->input('business_type');
            $customerId = $this->input('customer_id');
            $totalAmount = (float) $this->input('total_amount', 0);
            $paidAmount = (float) $this->input('paid_amount', 0);

            // If retail sale is partial or unpaid, customer_id is strictly required
            if ($businessType === 'retail' && $paidAmount < $totalAmount && empty($customerId)) {
                $validator->errors()->add('customer_id', 'A customer record is required for partial or credit retail sales to track running balance.');
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
