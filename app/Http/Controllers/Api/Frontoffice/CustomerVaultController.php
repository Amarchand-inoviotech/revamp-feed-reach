<?php

namespace App\Http\Controllers\Api\Frontoffice;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Customer;
use App\Services\NmiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class CustomerVaultController extends Controller
{
    public function __construct(
        protected NmiService $nmiService
    ) {}

    /**
     * Get user's cards
     */
    public function getCards()
    {
        try {
            $user = Auth::user();
            
            $cards = $user->cards()
                ->where('payment_method_id', 1) // Assuming NMI payment method ID is 1
                ->get()
                ->map(function ($card) {
                    return [
                        'id' => $card->uuid,
                        'name' => $card->name,
                        'last4' => $card->last4,
                        'expiry' => $card->expiry,
                        'vault_enabled' => $card->extra['vault_enabled'] ?? false,
                        'created_at' => $card->created_at,
                    ];
                });

            return successResponse($cards, 'Cards retrieved successfully');

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get user's customers
     */
    public function getCustomers()
    {
        try {
            $user = Auth::user();
            
            $customers = $user->customers()
                ->where('payment_method_id', 1) // Assuming NMI payment method ID is 1
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->uuid,
                        'gateway_customer_id' => $customer->gateway_customer_id,
                        'vault_enabled' => $customer->extra['vault_enabled'] ?? false,
                        'created_at' => $customer->created_at,
                    ];
                });

            return successResponse($customers, 'Customers retrieved successfully');

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Create a new card
     */
    public function createCard(Request $request)
    {
        $payload = $request->validate([
            'card' => ['required', 'array'],
            'card.number' => ['required', 'string', 'min:13', 'max:19', 'regex:/^[0-9]+$/'],
            'card.exp_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            'card.exp_year' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/', 'after_or_equal:' . date('Y')],
            'card.cvv' => ['required', 'string', 'min:3', 'max:4', 'regex:/^[0-9]+$/'],
            'billing_info' => ['nullable', 'array'],
            'billing_info.first_name' => ['nullable', 'string', 'max:100'],
            'billing_info.last_name' => ['nullable', 'string', 'max:100'],
            'billing_info.address1' => ['nullable', 'string', 'max:255'],
            'billing_info.city' => ['nullable', 'string', 'max:100'],
            'billing_info.state' => ['nullable', 'string', 'max:100'],
            'billing_info.zip' => ['nullable', 'string', 'max:20'],
            'billing_info.country' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            $user = Auth::user();

            // Create payment method on NMI
            $paymentResult = $this->nmiService->createPaymentMethod(
                $payload['card'], 
                $user, 
                $payload['billing_info'] ?? []
            );

            if (!$paymentResult['success']) {
                return errorResponse('Failed to create payment method: ' . $paymentResult['error'], 422);
            }

            // Create customer record if vault enabled and doesn't exist
            if ($paymentResult['vault_enabled']) {
                $customer = $user->customers()
                    ->where('payment_method_id', 1)
                    ->first();

                if (!$customer) {
                    $customer = Customer::create([
                        'user_id' => $user->id,
                        'payment_method_id' => 1,
                        'gateway_customer_id' => $paymentResult['token'],
                        'extra' => ['vault_enabled' => true]
                    ]);
                }
            }

            // Create card name
            $cardName = $this->getCardName($user, $payload['billing_info'] ?? []);

            // Create card record
            $card = Card::create([
                'payment_method_id' => 1,
                'token' => $paymentResult['token'],
                'name' => $cardName,
                'last4' => $paymentResult['last4'],
                'expiry' => $payload['card']['exp_year'] . '-' . str_pad($payload['card']['exp_month'], 2, '0', STR_PAD_LEFT) . '-01',
                'author_id' => $user->id,
                'author_type' => get_class($user),
                'extra' => [
                    'vault_enabled' => $paymentResult['vault_enabled'] ?? false,
                    'gateway_response' => $paymentResult['gateway_response'] ?? []
                ]
            ]);

            return successResponse([
                'id' => $card->uuid,
                'name' => $card->name,
                'last4' => $card->last4,
                'expiry' => $card->expiry,
                'vault_enabled' => $card->extra['vault_enabled'] ?? false,
            ], 'Card created successfully');

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Delete a card
     */
    public function deleteCard(Request $request)
    {
        $payload = $request->validate([
            'card_id' => ['required', 'string', 'exists:cards,uuid'],
        ]);

        try {
            $user = Auth::user();
            
            $card = Card::where('uuid', $payload['card_id'])
                ->where('author_id', $user->id)
                ->where('payment_method_id', 1)
                ->first();

            if (!$card) {
                return errorResponse('Card not found or does not belong to you', 404);
            }

            // Delete local record (NMI vault cleanup would be handled separately)
            $card->delete();

            return successResponse([], 'Card deleted successfully');

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Helper method to get card name
     */
    private function getCardName($user, array $billingInfo): string
    {
        $firstName = $billingInfo['first_name'] ?? '';
        $lastName = $billingInfo['last_name'] ?? '';
        
        if (empty($firstName) && empty($lastName)) {
            $nameParts = explode(' ', $user->name ?? '', 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }
        
        return trim("$firstName $lastName") ?: ($user->name ?? 'Card Holder');
    }
}
