<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\GatewayPackage;
use App\Models\User;
use App\Models\Card;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Services\NmiService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionService
{
    protected array $gateways = [];

    public function __construct() {
        // Initialize available gateways
        $this->initializeGateways();
    }

    /**
     * Register a gateway service
     */
    public function registerGateway(string $name, PaymentGatewayService $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    /**
     * Get a specific gateway service
     */
    public function getGateway(string $name): ?PaymentGatewayService
    {
        return $this->gateways[$name] ?? null;
    }

    /**
     * Create a subscription from request payload
     * Handles the complete subscription process including card creation, customer creation, and subscription
     */
    public function createSubscription(array $payload): array
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            if (!$user) {
                throw new Exception('User authentication required');
            }

            // Get gateway package with relationships
            $gatewayPackage = GatewayPackage::with(['package', 'paymentMethod'])
                ->whereHas('package', function ($query) use ($payload) {
                    $query->where('id', $payload['package_id']);
                })->first();

            if (!$gatewayPackage) {
                throw new Exception("Gateway package not found for package ID: {$payload['package_id']}");
            }

            // Get the appropriate gateway service
            $paymentMethod = $gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            if (!$gateway->isEnabled()) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not enabled");
            }

            // Ensure gateway package has a plan created
            if (!$gatewayPackage->gateway_id) {
                throw new Exception("Plan not found. Please create the plan first using register-gateway-package endpoint.");
            }

            // Check for existing active subscription
            if ($this->hasActiveSubscription($user, $payload['package_id'])) {
                throw new Exception('You already have an active subscription for this package');
            }

            // Handle card information
            $card = $this->handleCardInformation($payload, $user, $gatewayPackage, $gateway);

            // Handle customer creation/retrieval
            $customerData = $this->handleCustomerCreation($payload, $user, $gatewayPackage, $gateway);

            // Create subscription on gateway
            $subscriptionResult = $this->createGatewaySubscription(
                $customerData['customer_id'],
                $gatewayPackage,
                $card,
                $payload,
                $customerData['customer_management']
            );

            // Create local subscription record
            $subscription = $this->createLocalSubscription(
                $user,
                $gatewayPackage,
                $subscriptionResult,
                $customerData,
                $card,
                $payload
            );

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription,
                'customer' => $customerData['customer'],
                'gateway_response' => $subscriptionResult
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create custom subscription with custom pricing and frequency
     * Independent flow that doesn't use regular subscription logic
     */
    public function createCustomSubscription(array $payload): array
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            if (!$user) {
                throw new Exception('User authentication required');
            }

            // Get or create minimal gateway package (just for requirement)
            $gatewayPackage = $this->getOrCreateCustomGatewayPackage($payload);

            // Get NMI service directly
            $nmiService = $this->getGateway('nmi');
            if (!$nmiService) {
                throw new Exception("NMI gateway is not available");
            }

            if (!$nmiService->isEnabled()) {
                throw new Exception("NMI gateway is not enabled");
            }

            // Create custom subscription directly using NMI service
            $result = $nmiService->createCustomSubscription($user, $payload, $gatewayPackage);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $result['subscription'],
                'customer' => $result['customer'] ?? null,
                'gateway_response' => $result['gateway_response'] ?? null,
                'customer_vault_id' => $result['customer_vault_id'] ?? null
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a subscription for specific gateway package (used by service layer)
     */
    public function createSubscriptionForGateway(
        User $user,
        GatewayPackage $gatewayPackage,
        ?Card $card = null,
        array $options = []
    ): array {
        try {
            // Get the appropriate gateway service
            $paymentMethod = $gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            if (!$gateway->isEnabled()) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not enabled");
            }

            // Ensure gateway package has a plan created
            if (!$gatewayPackage->gateway_id) {
                throw new Exception("Plan not found. Please create the plan first using register-gateway-package endpoint.");
            }

            // Step 1: Create customer if not exists
            $customerId = $options['customer_id'] ?? null;
            $customer = null;

            if (!$customerId) {
                $customerResult = $gateway->createCustomer($user, $options['billing_info'] ?? []);

                if (!$customerResult['success']) {
                    throw new Exception("Failed to create customer: " . $customerResult['error']);
                }
                $customerId = $customerResult['customer_id'];

                // Create local customer record
                $customer = $user->customers()->where('payment_method_id', $paymentMethod->id)->firstOrCreate([
                    'payment_method_id' => $paymentMethod->id,
                    'gateway_customer_id' => $customerId
                ]);
            }

            // Step 2: Subscribe to plan
            $subscriptionResult = $gateway->createSubscription(
                $customerId,
                $gatewayPackage->gateway_id,
                $card,
                $options
            );

            if (!$subscriptionResult['success']) {
                throw new Exception("Failed to create subscription: " . $subscriptionResult['error']);
            }

            // Create local subscription record
            $subscriptionData = [
                'billing_cycle' => $gatewayPackage->package->billing_cycle,
                'user_id' => $user->id,
                'gateway_package_id' => $gatewayPackage->id,
                'getway_subscription_id' => $subscriptionResult['subscription_id'],
                'start_date' => $options['start_date'] ?? now()->toDateString(),
                'status' => 'active',
                'extra' => [
                    'gateway_response' => $subscriptionResult['gateway_response'] ?? []
                ]
            ];

            // Calculate next billing date
            $subscriptionData['next_billing_date'] = $this->calculateNextBillingDate(
                $subscriptionData['start_date'],
                $subscriptionData['billing_cycle']
            );

            return [
                'success' => true,
                'subscription_data' => $subscriptionData,
                'customer' => $customer,
                'gateway_response' => $subscriptionResult
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            DB::beginTransaction();

            // Get the appropriate gateway service
            $paymentMethod = $subscription->gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            // Cancel subscription on gateway
            $cancelResult = $gateway->cancelSubscription($subscription->getway_subscription_id);

            if (!$cancelResult['success']) {
                throw new Exception("Failed to cancel subscription on gateway: " . $cancelResult['error']);
            }

            // Update local subscription
            $subscription->update([
                'status' => 'canceled',
                'end_date' => now()->toDateString(),
                'extra' => array_merge($subscription->extra ?? [], [
                    'cancelled_at' => now()->toISOString(),
                    'cancel_response' => $cancelResult['gateway_response'] ?? []
                ])
            ]);

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription,
                'gateway_response' => $cancelResult
            ];

        } catch (Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription plan (proper update instead of cancel+create)
     */
    public function updateSubscriptionPlan(Subscription $subscription, Package $newPackage, array $payload = []): array
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();

            // Get the new gateway package
            $newGatewayPackage = GatewayPackage::with(['package', 'paymentMethod'])
                ->whereHas('package', function ($query) use ($newPackage) {
                    $query->where('id', $newPackage->id);
                })
                ->first();

            if (!$newGatewayPackage) {
                throw new Exception("Gateway package not found for package ID: {$newPackage->id}");
            }

            // Get the appropriate gateway service
            $paymentMethod = $newGatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            if (!$gateway->isEnabled()) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not enabled");
            }

            // Ensure gateway package has a plan created
            if (!$newGatewayPackage->gateway_id) {
                throw new Exception("Plan not found. Please create the plan first using register-gateway-package endpoint.");
            }

            // Check if it's the same package
            if ($subscription->gatewayPackage->package_id == $newPackage->id) {
                throw new Exception('You are already subscribed to this package');
            }

            // Try to update the subscription plan on the gateway (if supported)
            if (method_exists($gateway, 'updateSubscriptionPlan')) {
                $updateResult = $gateway->updateSubscriptionPlan(
                    $subscription->getway_subscription_id,
                    $newGatewayPackage->gateway_id
                );

                if ($updateResult['success']) {
                    // Update local subscription record
                    $subscription->update([
                        'gateway_package_id' => $newGatewayPackage->id,
                        'billing_cycle' => $newGatewayPackage->package->billing_cycle,
                        'next_billing_date' => $this->calculateNextBillingDate(
                            $subscription->start_date,
                            $newGatewayPackage->package->billing_cycle
                        ),
                        'extra' => array_merge($subscription->extra ?? [], [
                            'plan_updated_at' => now()->toISOString(),
                            'previous_plan' => $subscription->gatewayPackage->gateway_id,
                            'update_response' => $updateResult['gateway_response'] ?? []
                        ])
                    ]);

                    DB::commit();

                    return [
                        'success' => true,
                        'subscription' => $subscription->fresh(),
                        'gateway_response' => $updateResult,
                        'method' => 'update'
                    ];
                }
            }

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    /**
     * Update a subscription (for status changes only)
     */
    public function updateSubscription(Subscription $subscription, array $updateData): array
    {
        try {
            DB::beginTransaction();

            // Load subscription relationships
            $subscription->load(['gatewayPackage.paymentMethod']);

            // Get the appropriate gateway service
            $paymentMethod = $subscription->gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            // For now, we only support status updates
            // Plan changes should use changeSubscriptionPlan method
            $localUpdateData = [];
            if (isset($updateData['status'])) {
                $localUpdateData['status'] = $updateData['status'];

                // If canceling, also cancel on gateway
                if ($updateData['status'] === 'canceled') {
                    $cancelResult = $gateway->cancelSubscription($subscription->getway_subscription_id);
                    if (!$cancelResult['success']) {
                        throw new Exception("Failed to cancel subscription on gateway: " . $cancelResult['error']);
                    }
                    $localUpdateData['end_date'] = now()->toDateString();
                    $localUpdateData['extra'] = array_merge($subscription->extra ?? [], [
                        'cancelled_at' => now()->toISOString(),
                        'cancel_response' => $cancelResult['gateway_response'] ?? []
                    ]);
                }
            }

            if (!empty($localUpdateData)) {
                if (!isset($localUpdateData['extra'])) {
                    $localUpdateData['extra'] = array_merge($subscription->extra ?? [], [
                        'updated_at' => now()->toISOString()
                    ]);
                }

                $subscription->update($localUpdateData);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription
            ];

        } catch (Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync subscription status with gateway
     */
    public function syncSubscriptionStatus(Subscription $subscription): array
    {
        try {
            // Get the appropriate gateway service
            $paymentMethod = $subscription->gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            // Get subscription details from gateway
            $gatewayResult = $gateway->getSubscription($subscription->getway_subscription_id);

            if (!$gatewayResult['success']) {
                throw new Exception("Failed to get subscription from gateway: " . $gatewayResult['error']);
            }

            $gatewaySubscription = $gatewayResult['subscription'];

            // Update local subscription based on gateway status
            $updateData = [
                'extra' => array_merge($subscription->extra ?? [], [
                    'last_sync' => now()->toISOString(),
                    'gateway_data' => $gatewaySubscription
                ])
            ];

            // Map gateway status to local status if needed
            if (isset($gatewaySubscription['status'])) {
                $updateData['status'] = $this->mapGatewayStatus($gatewaySubscription['status']);
            }

            $subscription->update($updateData);

            return [
                'success' => true,
                'subscription' => $subscription,
                'gateway_data' => $gatewaySubscription
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user has active subscription for a package
     */
    public function hasActiveSubscription(User $user, int $packageId): bool
    {
        return Subscription::where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereHas('gatewayPackage', function ($query) use ($packageId) {
                $query->where('package_id', $packageId);
            })
            ->exists();
    }

    /**
     * Update subscription card/payment method
     */
    public function updateSubscriptionCard(
        Subscription $subscription,
        ?array $cardData = null,
        bool $deleteOldCard = false,
        ?int $cardId = null,
        array $billingInfo = []
    ): array
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();

            // Load subscription relationships
            $subscription->load(['gatewayPackage.package', 'gatewayPackage.paymentMethod']);

            // Get the appropriate gateway service
            $paymentMethod = $subscription->gatewayPackage->paymentMethod;
            $gateway = $this->getGateway($paymentMethod->name);

            if (!$gateway) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not available");
            }

            if (!$gateway->isEnabled()) {
                throw new Exception("Gateway '{$paymentMethod->name}' is not enabled");
            }

            // Get current card for potential deletion
            $currentCard = null;
            if ($deleteOldCard) {
                $currentCard = Card::where('payment_method_id', $subscription->gatewayPackage->payment_method_id)
                    ->whereHasMorph('author', [User::class], function ($query) use ($user) {
                        $query->where('id', $user->id);
                    })
                    ->first();
            }

            $newCard = null;

            // Check if using existing card from customer vault
            if ($cardId) {
                $newCard = Card::where('id', $cardId)
                    ->where('payment_method_id', $subscription->gatewayPackage->payment_method_id)
                    ->whereHasMorph('author', [User::class], function ($query) use ($user) {
                        $query->where('id', $user->id);
                    })
                    ->first();

                if (!$newCard) {
                    throw new Exception("Card not found or does not belong to the user.");
                }
            } else {
                // Create new payment method on gateway
                if (!$cardData) {
                    throw new Exception("Card data is required when not using existing card.");
                }

                $paymentMethodResult = $gateway->createPaymentMethod($cardData, $user, $billingInfo);
                if (!$paymentMethodResult['success']) {
                    throw new Exception("Failed to create payment method: " . $paymentMethodResult['error']);
                }

                // If this created a new customer vault, store it locally
                if ($paymentMethodResult['vault_enabled'] && isset($paymentMethodResult['token'])) {
                    $user->customers()->updateOrCreate(
                        ['payment_method_id' => $subscription->gatewayPackage->payment_method_id],
                        [
                            'gateway_customer_id' => $paymentMethodResult['token'],
                            'extra' => [
                                'customer_management' => true,
                                'gateway_response' => $paymentMethodResult['gateway_response'] ?? []
                            ]
                        ]
                    );
                }

                // Create card name from billing info or user name
                $firstName = $billingInfo['first_name'] ?? '';
                $lastName = $billingInfo['last_name'] ?? '';

                if (empty($firstName) && empty($lastName)) {
                    $nameParts = explode(' ', $user->name ?? '', 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';
                }

                $cardName = trim("$firstName $lastName") ?: ($user->name ?? 'Card Holder');

                // Create new card record
                $newCard = Card::create([
                    'payment_method_id' => $subscription->gatewayPackage->payment_method_id,
                    'token' => $paymentMethodResult['token'],
                    'name' => $cardName,
                    'last4' => $paymentMethodResult['last4'],
                    'expiry' => $cardData['exp_year'] . '-' . str_pad($cardData['exp_month'], 2, '0', STR_PAD_LEFT) . '-01',
                    'author_id' => $user->getKey(),
                    'author_type' => $user->getMorphClass(),
                    'extra' => [
                        'vault_enabled' => $paymentMethodResult['vault_enabled'] ?? false,
                        'billing_id' => $paymentMethodResult['billing_id'] ?? null,
                        'gateway_response' => $paymentMethodResult['gateway_response'] ?? [],
                        'created_for' => 'card_update'
                    ]
                ]);
            }

            // **CRITICAL FIX**: Update the subscription on NMI to use the new card
            $billingId = $newCard->extra['billing_id'] ?? null;
            $updateResult = $gateway->updateSubscriptionPaymentMethod(
                $subscription->getway_subscription_id,
                $newCard->token,
                $billingId
            );

            if (!$updateResult['success']) {
                throw new Exception("Failed to update subscription payment method on gateway: " . $updateResult['error']);
            }

            // Update subscription extra to reference new card
            $subscriptionExtra = array_merge($subscription->extra ?? [], [
                'card_updated_at' => now()->toISOString(),
                'previous_card_token' => $subscription->extra['card_token'] ?? null,
                'current_card_token' => $newCard->token,
                'card_update_response' => $paymentMethodResult['gateway_response'] ?? [],
                'subscription_update_response' => $updateResult['gateway_response'] ?? []
            ]);

            // Delete old card if requested and it exists
            if ($deleteOldCard && $currentCard && $currentCard->token) {
                $deleteResult = $gateway->deletePaymentMethod($currentCard->token);
                if ($deleteResult['success']) {
                    $currentCard->delete();
                    $subscriptionExtra['old_card_deleted'] = true;
                    $subscriptionExtra['old_card_delete_response'] = $deleteResult['gateway_response'] ?? [];
                } else {
                    // Log warning but don't fail the update
                    Log::warning('Failed to delete old card during update', [
                        'subscription_id' => $subscription->id,
                        'old_card_token' => $currentCard->token,
                        'error' => $deleteResult['error'] ?? 'Unknown error'
                    ]);
                    $subscriptionExtra['old_card_delete_failed'] = $deleteResult['error'] ?? 'Unknown error';
                }
            }

            $subscription->update(['extra' => $subscriptionExtra]);

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription->fresh(),
                'new_card' => $newCard,
                'old_card_deleted' => $deleteOldCard && isset($subscriptionExtra['old_card_deleted']),
                'message' => 'Card updated successfully'
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process webhook for subscription events
     */
    public function processWebhook(string $gatewayName, array $webhookData): array
    {
        try {
            $gateway = $this->getGateway($gatewayName);

            if (!$gateway) {
                throw new Exception("Gateway '{$gatewayName}' is not available");
            }

            return $gateway->processWebhook($webhookData);

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle card information from payload
     */
    protected function handleCardInformation(array $payload, User $user, GatewayPackage $gatewayPackage, PaymentGatewayService $gateway): ?Card
    {
        $card = null;

        if (isset($payload['card_id']) && $payload['card_id']) {
            // Use existing card
            $card = Card::where('id', $payload['card_id'])
                ->where('payment_method_id', $gatewayPackage->payment_method_id)
                ->whereHasMorph('author', [User::class], function ($query) use ($user) {
                    $query->where('id', $user->getKey());
                })
                ->first();

            if (!$card) {
                throw new Exception("Card not found or does not belong to the user.");
            }
        } else {
            // Create new card
            if (!isset($payload['card']) || !is_array($payload['card'])) {
                throw new Exception("Card information is required when no existing card is selected.");
            }

            // Use user name for billing info
            $nameParts = explode(' ', $user->name ?? '', 2);
            $firstName = $payload['billing_info']['first_name'] ?? ($nameParts[0] ?? '');
            $lastName = $payload['billing_info']['last_name'] ?? ($nameParts[1] ?? '');

            // Validate card data
            $requiredCardFields = ['number', 'exp_month', 'exp_year', 'cvv'];
            foreach ($requiredCardFields as $field) {
                if (empty($payload['card'][$field])) {
                    throw new Exception("Card {$field} is required.");
                }
            }

            $cardData = [
                'number' => $payload['card']['number'],
                'exp_month' => $payload['card']['exp_month'],
                'exp_year' => $payload['card']['exp_year'],
                'cvv' => $payload['card']['cvv'],
            ];

            $paymentMethodResult = $gateway->createPaymentMethod($cardData, $user, $payload['billing_info'] ?? []);
            if (!$paymentMethodResult['success']) {
                throw new Exception("Failed to create payment method: " . $paymentMethodResult['error']);
            }

            // If this created a new customer vault, store it locally
            if ($paymentMethodResult['vault_enabled'] && isset($paymentMethodResult['token'])) {
                $user->customers()->updateOrCreate(
                    ['payment_method_id' => $gatewayPackage->payment_method_id],
                    [
                        'gateway_customer_id' => $paymentMethodResult['token'],
                        'extra' => [
                            'customer_management' => true,
                            'gateway_response' => $paymentMethodResult['gateway_response'] ?? []
                        ]
                    ]
                );
            }

            // Create card name
            $cardName = trim($firstName . ' ' . $lastName) ?: ($user->name ?? 'Card Holder');

            // Determine if vault is enabled based on response
            $vaultEnabled = $paymentMethodResult['vault_enabled'] ?? false;

            $card = Card::create([
                'payment_method_id' => $gatewayPackage->payment_method_id,
                'token' => $paymentMethodResult['token'],
                'name' => $cardName,
                'last4' => $paymentMethodResult['last4'],
                'expiry' => $payload['card']['exp_year'] . '-' . str_pad($payload['card']['exp_month'], 2, '0', STR_PAD_LEFT) . '-01',
                'author_id' => $user->getKey(),
                'author_type' => $user->getMorphClass(),
                'extra' => [
                    'vault_enabled' => $vaultEnabled,
                    'billing_id' => $paymentMethodResult['billing_id'] ?? null,
                    'gateway_response' => $paymentMethodResult['gateway_response'] ?? []
                ]
            ]);
        }

        return $card;
    }

    /**
     * Handle customer creation/retrieval
     */
    protected function handleCustomerCreation(array $payload, User $user, GatewayPackage $gatewayPackage, PaymentGatewayService $gateway): array
    {
        // Check if customer_id is provided in payload (customer vault)
        if (isset($payload['customer_id']) && $payload['customer_id']) {
            $customer = $user->customers()
                ->where('id', $payload['customer_id'])
                ->where('payment_method_id', $gatewayPackage->payment_method_id)
                ->first();

            if (!$customer) {
                throw new Exception("Customer not found or does not belong to the user.");
            }

            return [
                'customer_id' => $customer->gateway_customer_id,
                'customer_management' => $customer->extra['customer_management'] ?? false,
                'customer' => $customer
            ];
        }

        // Existing logic for creating/retrieving customer
        $customer = $user->customers()->where('payment_method_id', $gatewayPackage->payment_method_id)->first();
        $customerId = null;
        $customerManagement = false;

        if ($customer && !empty($customer->gateway_customer_id)) {
            // Use existing customer
            $customerId = $customer->gateway_customer_id;
            $customerManagement = $customer->extra['customer_management'] ?? false;
        } else {
            // Create new customer on gateway
            $billingInfo = $payload['billing_info'] ?? [];

            // Ensure we have user name information for customer creation
            if (empty($billingInfo['first_name']) && empty($billingInfo['last_name'])) {
                $nameParts = explode(' ', $user->name ?? '', 2);
                $billingInfo['first_name'] = $nameParts[0] ?? '';
                $billingInfo['last_name'] = $nameParts[1] ?? '';
            }

            $customerResult = $gateway->createCustomer($user, $billingInfo);

            if (!$customerResult['success']) {
                throw new Exception("Failed to create customer: " . $customerResult['error']);
            }

            $customerId = $customerResult['customer_id'];
            $customerManagement = $customerResult['customer_management'] ?? false;

            // Create or update local customer record
            $customer = $user->customers()->updateOrCreate(
                ['payment_method_id' => $gatewayPackage->payment_method_id],
                [
                    'gateway_customer_id' => $customerId,
                    'extra' => [
                        'customer_management' => $customerManagement,
                        'gateway_response' => $customerResult['gateway_response'] ?? []
                    ]
                ]
            );
        }

        return [
            'customer_id' => $customerId,
            'customer_management' => $customerManagement,
            'customer' => $customer
        ];
    }

    /**
     * Create subscription on gateway
     */
    protected function createGatewaySubscription(
        string $customerId,
        GatewayPackage $gatewayPackage,
        ?Card $card,
        array $payload,
        bool $customerManagement
    ): array {
        $gateway = $this->getGateway($gatewayPackage->paymentMethod->name);

        $startDate = $payload['start_date'] ?? now()->toDateString();
        $subscriptionOptions = [
            'start_date' => $startDate,
            'customer_management' => $customerManagement
        ];
        // Include card data and billing info if vault is not enabled
        $vaultEnabled = $card->extra['vault_enabled'] ?? false;
        if (!$vaultEnabled && isset($payload['card'])) {
            $subscriptionOptions['card_data'] = $payload['card'];

            // Include billing information for proper customer details
            $billingInfo = $payload['billing_info'] ?? [];

            // Ensure we have user email for billing
            if (empty($billingInfo['email'])) {
                $billingInfo['email'] = Auth::user()->email;
            }
            if (empty($billingInfo['first_name']) && empty($billingInfo['last_name'])) {
                $nameParts = explode(' ', Auth::user()->name ?? '', 2);
                $billingInfo['first_name'] = $nameParts[0] ?? '';
                $billingInfo['last_name'] = $nameParts[1] ?? '';
            }

            $subscriptionOptions['billing_info'] = $billingInfo;
        }
        $subscriptionResult = $gateway->createSubscription(
            $customerId,
             $gatewayPackage->gateway_id,
            $card,
            $subscriptionOptions
        );

        if (!$subscriptionResult['success']) {
            throw new Exception("Failed to create subscription: " . $subscriptionResult['error']);
        }

        // Validate subscription ID was returned
        if (empty($subscriptionResult['subscription_id'])) {
            throw new Exception("No subscription ID returned from gateway.");
        }

        return $subscriptionResult;
    }

    /**
     * Create local subscription record
     */
    protected function createLocalSubscription(
        User $user,
        GatewayPackage $gatewayPackage,
        array $subscriptionResult,
        array $customerData,
        ?Card $card,
        array $payload
    ): Subscription {
        $startDate = $payload['start_date'] ?? now()->toDateString();
        $subscriptionData = [
            'billing_cycle' => $gatewayPackage->package->billing_cycle,
            'user_id' => $user->getKey(),
            'gateway_package_id' => $gatewayPackage->getKey(),
            'getway_subscription_id' => $subscriptionResult['subscription_id'],
            'start_date' => $startDate,
            'status' => 'active',
            'extra' => [
                'gateway_response' => $subscriptionResult['gateway_response'] ?? [],
                'customer_id' => $customerData['customer_id'],
                'card_token' => $card?->token,
                'created_at' => now()->toISOString()
            ]
        ];

        // Calculate next billing date
        $subscriptionData['next_billing_date'] = $this->calculateNextBillingDate(
            $startDate,
            $subscriptionData['billing_cycle']
        );

        return Subscription::create($subscriptionData);
    }



    /**
     * Helper methods
     */

    protected function initializeGateways(): void
    {
        // Initialize NMI gateway from account configuration
        try {
            $paymentMethod = \App\Models\PaymentMethod::where('name', 'nmi')->first();
            if ($paymentMethod) {
                $account = \App\Models\Account::where('payment_method_id', $paymentMethod->id)
                    ->where('status', true)
                    ->first();

                if ($account) {
                    $this->gateways['nmi'] = new NmiService($account, app()->environment('production'));
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to initialize NMI gateway', [
                'error' => $e->getMessage()
            ]);
        }

        // Add other gateways here when implemented
        // Stripe, PayPal, etc.
    }

    protected function calculateNextBillingDate(string $startDate, string $billingCycle): string
    {
        $date = \Carbon\Carbon::parse($startDate);

        return match($billingCycle) {
            'daily' => $date->addDay()->toDateString(),
            'weekly' => $date->addWeek()->toDateString(),
            'monthly' => $date->addMonth()->toDateString(),
            'quarterly' => $date->addMonths(3)->toDateString(),
            'bi-quarterly' => $date->addMonths(6)->toDateString(),
            'yearly' => $date->addYear()->toDateString(),
            default => $date->addMonth()->toDateString()
        };
    }

    protected function mapGatewayStatus(string $gatewayStatus): string
    {
        // Map gateway-specific status to our local status
        return match(strtolower($gatewayStatus)) {
            'active', 'current' => 'active',
            'cancelled', 'canceled' => 'canceled',
            'paused', 'suspended' => 'paused',
            'expired', 'ended' => 'expired',
            default => 'active'
        };
    }

    /**
     * Get or create minimal gateway package for custom subscriptions
     * Only creates the package because it's required by the system
     */
    protected function getOrCreateCustomGatewayPackage(array $payload): GatewayPackage
    {
        // If package_id is provided, use it
        if (!empty($payload['package_id'])) {
            $package = Package::where('uuid', $payload['package_id'])->first();
            if ($package) {
                $gatewayPackage = GatewayPackage::with(['package', 'paymentMethod'])
                    ->whereHas('package', function ($query) use ($package) {
                        $query->where('id', $package->id);
                    })->whereHas('paymentMethod', function ($query) {
                        $query->where('name', 'nmi');
                    })->first();

                if ($gatewayPackage) {
                    return $gatewayPackage;
                }
            }
        }

        // Find or create a minimal custom package (just for system requirement)
        $customPackage = Package::where('name', 'LIKE', '%Custom%')->first();

        if (!$customPackage) {
            // Create a minimal custom package
            $customPackage = Package::create([
                'uuid' => Str::uuid(),
                'name' => 'Custom Package',
                'description' => 'Custom subscription package - pricing set per subscription',
                'price' => 0, // Not used for custom subscriptions
                'billing_cycle' => 'monthly',
                'status' => 'active'
            ]);
        }

        // Find or create gateway package for NMI
        $paymentMethod = PaymentMethod::where('name', 'nmi')->firstOrFail();

        $gatewayPackage = GatewayPackage::with(['package', 'paymentMethod'])
            ->where('package_id', $customPackage->id)
            ->where('payment_method_id', $paymentMethod->id)
            ->first();

        if (!$gatewayPackage) {
            $gatewayPackage = GatewayPackage::create([
                'uuid' => Str::uuid(),
                'package_id' => $customPackage->id,
                'payment_method_id' => $paymentMethod->id,
                'gateway_package_id' => 'custom_plan_' . uniqid(),
                'extra' => ['custom_subscription' => true, 'placeholder' => true]
            ]);
            $gatewayPackage->load(['package', 'paymentMethod']);
        }

        return $gatewayPackage;
    }

    /**
     * Update custom subscription card
     * Independent flow for custom subscriptions
     */
    public function updateCustomSubscriptionCard(Subscription $subscription, array $payload): array
    {
        try {
            DB::beginTransaction();

            // Verify this is a custom subscription
            if (!($subscription->extra['custom_subscription'] ?? false)) {
                throw new Exception('This method is only for custom subscriptions');
            }

            // Get NMI service directly
            $nmiService = $this->getGateway('nmi');
            if (!$nmiService) {
                throw new Exception("NMI gateway is not available");
            }

            if (!$nmiService->isEnabled()) {
                throw new Exception("NMI gateway is not enabled");
            }

            // Update subscription card directly using NMI service
            $result = $nmiService->updateSubscriptionCard($subscription, $payload);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $result['subscription'],
                'gateway_response' => $result['gateway_response'] ?? null
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update custom subscription amount and frequency
     * Independent flow for custom subscriptions
     */
    public function updateCustomSubscriptionAmount(Subscription $subscription, array $payload): array
    {
        try {
            DB::beginTransaction();

            // Verify this is a custom subscription
            if (!($subscription->extra['custom_subscription'] ?? false)) {
                throw new Exception('This method is only for custom subscriptions');
            }

            // Get NMI service directly
            $nmiService = $this->getGateway('nmi');
            if (!$nmiService) {
                throw new Exception("NMI gateway is not available");
            }

            if (!$nmiService->isEnabled()) {
                throw new Exception("NMI gateway is not enabled");
            }

            // Update subscription amount directly using NMI service
            $result = $nmiService->updateSubscriptionAmount($subscription, $payload);

            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $result['subscription'],
                'gateway_response' => $result['gateway_response'] ?? null
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function manualCharge(Subscription $subscription, array $payload): array
    {
        try {
            DB::beginTransaction();

            // Verify this is a custom subscription
            if (!($subscription->extra['custom_subscription'] ?? false)) {
                throw new Exception('This method is only for custom subscriptions');
            }

            // Get NMI service directly
            $nmiService = $this->getGateway('nmi');
            if (!$nmiService) {
                throw new Exception("NMI gateway is not available");
            }

            if (!$nmiService->isEnabled()) {
                throw new Exception("NMI gateway is not enabled");
            }

            // Update subscription amount directly using NMI service
            $result = $nmiService->manualCharge($subscription, $payload);
            if (!$result['success']) {
                throw new Exception($result['error']);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription->fresh(),
                'gateway_response' => $result['gateway_response'] ?? null
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
