<?php

namespace Bog\Payment\Controllers;

use Bog\Payment\Models\BogCard;
use Bog\Payment\Services\BogAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

class BogCardController extends Controller
{
    protected $bogAuth;

    public function __construct(BogAuthService $bogAuth)
    {
        $this->bogAuth = $bogAuth;
        $this->middleware('auth:sanctum');
    }

    /**
     * Add a new card manually
     */
    public function addCard(Request $request)
    {
        $request->validate([
            'card_number' => ['required', 'string', 'min:13', 'max:19'],
            'card_holder_name' => ['required', 'string', 'max:255'],
            'expiry_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            'expiry_year' => ['required', 'string', 'size:4', 'regex:/^20[2-9][0-9]$/'],
            'cvv' => ['required', 'string', 'size:3', 'regex:/^[0-9]{3}$/'],
            'card_type' => ['nullable', 'string', 'in:visa,mastercard,amex,bog,other'],
        ]);

        try {
            $user = $request->user('sanctum');
            $cardType = $request->card_type ?? $this->detectCardType($request->card_number);
            $cardBrand = $this->detectCardBrand($request->card_number);
            $cardMask = $this->maskCardNumber($request->card_number);
            $cardToken = $this->generateCardToken($request->card_number, $user->id);

            if (BogCard::where('user_id', $user->id)->where('card_mask', $cardMask)->exists()) {
                return response()->json(['success' => false, 'message' => 'This card is already saved'], 400);
            }

            $expiryDate = \Carbon\Carbon::createFromDate($request->expiry_year, $request->expiry_month, 1)->endOfMonth();
            if ($expiryDate->isPast()) {
                return response()->json(['success' => false, 'message' => 'Card has expired'], 400);
            }

            $card = BogCard::create([
                'user_id' => $user->id,
                'card_token' => $cardToken,
                'card_mask' => $cardMask,
                'card_type' => $cardType,
                'card_holder_name' => strtoupper($request->card_holder_name),
                'card_brand' => $cardBrand,
                'expiry_month' => $request->expiry_month,
                'expiry_year' => $request->expiry_year,
                'is_default' => ! BogCard::where('user_id', $user->id)->exists(),
                'metadata' => ['added_manually' => true],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Card added successfully',
                'card' => [
                    'id' => $card->id,
                    'card_mask' => $card->card_mask,
                    'card_type' => $card->card_type,
                    'card_brand' => $card->card_brand,
                    'expiry_month' => $card->expiry_month,
                    'expiry_year' => $card->expiry_year,
                    'is_default' => $card->is_default,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error adding card: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to add card'], 500);
        }
    }

    /**
     * List all saved cards
     */
    public function listCards(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $cards = BogCard::where('user_id', $user->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($card) {
                    return [
                        'id' => $card->id,
                        'card_mask' => $card->card_mask,
                        'card_type' => $card->card_type,
                        'card_brand' => $card->card_brand,
                        'card_holder_name' => $card->card_holder_name,
                        'expiry_month' => $card->expiry_month,
                        'expiry_year' => $card->expiry_year,
                        'formatted_expiry' => $card->formatted_expiry,
                        'is_default' => $card->is_default,
                        'created_at' => $card->created_at->toIso8601String(),
                    ];
                });

            return response()->json(['success' => true, 'cards' => $cards]);
        } catch (\Exception $e) {
            Log::error('Error listing cards: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve cards'], 500);
        }
    }

    /**
     * Delete a saved card
     */
    public function deleteCard(Request $request, $cardId)
    {
        try {
            $user = $request->user('sanctum');
            $card = BogCard::where('id', $cardId)->where('user_id', $user->id)->firstOrFail();

            if ($card->is_default) {
                $newDefaultCard = BogCard::where('user_id', $user->id)->where('id', '!=', $cardId)->first();
                if ($newDefaultCard) {
                    $newDefaultCard->update(['is_default' => true]);
                }
            }

            $card->delete();
            return response()->json(['success' => true, 'message' => 'Card deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting card: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete card'], 500);
        }
    }

    /**
     * Set default card
     */
    public function setDefaultCard(Request $request, $cardId)
    {
        try {
            $user = $request->user('sanctum');
            BogCard::where('user_id', $user->id)->update(['is_default' => false]);
            $card = BogCard::where('id', $cardId)->where('user_id', $user->id)->firstOrFail();
            $card->update(['is_default' => true]);

            return response()->json(['success' => true, 'message' => 'Default card updated']);
        } catch (\Exception $e) {
            Log::error('Error setting default card: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to set default card'], 500);
        }
    }

    private function detectCardType($cardNumber): string
    {
        $cardNumber = preg_replace('/\s+/', '', $cardNumber);
        if (preg_match('/^4/', $cardNumber)) return 'visa';
        if (preg_match('/^5[1-5]/', $cardNumber) || preg_match('/^2(22[1-9]|2[3-9][0-9]|[3-6][0-9]{2}|7[0-1][0-9]|720)/', $cardNumber)) return 'mastercard';
        if (preg_match('/^3[47]/', $cardNumber)) return 'amex';
        if (preg_match('/^6/', $cardNumber)) return 'bog';
        return 'other';
    }

    private function detectCardBrand($cardNumber): string
    {
        $cardNumber = preg_replace('/\s+/', '', $cardNumber);
        if (preg_match('/^4/', $cardNumber)) return 'Visa';
        if (preg_match('/^5[1-5]/', $cardNumber)) return 'Mastercard';
        if (preg_match('/^3[47]/', $cardNumber)) return 'American Express';
        return 'Unknown';
    }

    private function maskCardNumber($cardNumber): string
    {
        return '****' . substr(preg_replace('/\s+/', '', $cardNumber), -4);
    }

    private function generateCardToken($cardNumber, $userId): string
    {
        return 'card_' . hash('sha256', $cardNumber . $userId . time() . config('app.key'));
    }
}