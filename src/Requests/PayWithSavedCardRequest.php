<?php

namespace Bog\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayWithSavedCardRequest extends FormRequest
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'callback_url' => 'required|url',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|in:GEL,USD,EUR',
            'basket' => 'required|array|min:1',
            'basket.*.quantity' => 'required|integer|min:1',
            'basket.*.unit_price' => 'required|numeric|min:0.01',
            'basket.*.product_id' => 'required|string',
            'external_order_id' => 'sometimes|string',
            'language' => 'sometimes|string|in:ka,en,ru',
        ];
    }
}
