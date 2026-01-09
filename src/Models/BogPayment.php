<?php

namespace Bog\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class BogPayment extends Model
{
    protected $table = 'bog_payments';

    protected $fillable = [
        'bog_order_id',
        'external_order_id',
        'user_id',
        'amount',
        'currency',
        'status',
        'redirect_url',
        'request_payload',
        'response_data',
        'callback_data',
        'save_card_requested',
        'verified_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'callback_data' => 'array',
        'amount' => 'decimal:2',
        'save_card_requested' => 'boolean',
    ];

    public function cards()
    {
        return $this->hasMany(BogCard::class, 'parent_order_id', 'bog_order_id');
    }

    /**
     * Get the web user (customer) who made this payment
     * Note: user_id references web_users table
     */
    public function user()
    {
        return $this->belongsTo(\config('services.bog.user_model', 'App\Models\WebUser'), 'user_id');
    }

    /**
     * Alias for user() relationship for clarity
     *
     * @deprecated Use user() instead - kept for backward compatibility
     */
    public function webUser()
    {
        return $this->user();
    }

    /**
     * Relationship to products
     * Note: This assumes Product model exists in the application
     */
    public function products()
    {
        $productModel = \config('services.bog.product_model', 'App\Models\Product');

        return $this->belongsToMany($productModel, 'bog_payment_product')
            ->withPivot('quantity', 'unit_price', 'total_price')
            ->withTimestamps();
    }
}
