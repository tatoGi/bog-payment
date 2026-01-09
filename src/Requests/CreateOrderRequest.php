<?php

namespace Bog\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'purchase_units' => 'required|array',
            'purchase_units.total_amount' => 'required|numeric|min:0.01',
            'purchase_units.currency' => 'required|string|size:3',
            'purchase_units.basket' => 'required|array|min:1',
            'purchase_units.basket.*.product_id' => 'required|string',
            'purchase_units.basket.*.quantity' => 'required|integer|min:1',
            'purchase_units.basket.*.unit_price' => 'required|numeric|min:0.01',
            'purchase_units.basket.*.name' => 'required|string',
            'redirect_urls' => 'required|array',
            'redirect_urls.success' => 'required|url',
            'redirect_urls.fail' => 'required|url',
            'application_type' => 'sometimes|string|in:web,mobile',
            'capture' => 'sometimes|string|in:automatic,manual',
            'external_order_id' => 'nullable|string|max:100',
            'language' => 'sometimes|string|in:en,ka,ru',
            'save_card' => 'sometimes|boolean',
            'user_id' => 'sometimes|integer',
        ];
    }
}
