<?php

namespace App\Services;

use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use App\Models\User;
use App\Models\Card;
use App\Models\GatewayPackage;
use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Company;
use App\Helpers\Encrypt;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NmiService extends PaymentGatewayService
{
    protected string $secretApiKey;
    protected string $publicApiKey;
    protected string $webhookSecret;
    protected string $apiUrl;
    protected bool $vaultEnabled;
    protected bool $isLive;
    protected Account $account;

    public function __construct(?Account $account = null, bool $isLive = false)
    {
        $this->isLive = $isLive;
        $this->apiUrl = 'https://secure.nmi.com/api/transact.php';

        if ($account) {
            $this->account = $account;
            $this->initializeFromAccount($account, $isLive);
        } else {
            // Fallback to first available NMI account
            $this->initializeFromDefaultAccount($isLive);
        }

        parent::__construct([
            'enabled' => true,
            'vault_enabled' => $this->vaultEnabled,
            'is_live' => $this->isLive
        ]);
    }

    /**
     * Initialize service from Account model
     */
    protected function initializeFromAccount(Account $account, bool $isLive = false): void
    {
        $environment = $isLive ? 'live' : 'sandbox';
        $meta = $account->meta[$environment] ?? [];

        if (empty($meta)) {
            throw new Exception("No {$environment} configuration found for NMI account: {$account->name}");
        }

        $this->secretApiKey = Encrypt::decrypt($meta['secret_api_key'] ?? '');
        $this->publicApiKey = Encrypt::decrypt($meta['public_api_key'] ?? '');
        $this->webhookSecret = Encrypt::decrypt($meta['webhook_secret'] ?? '');
        $this->vaultEnabled = (bool) ($meta['vault_enabled'] ?? false);

        if (empty($this->secretApiKey) || empty($this->publicApiKey)) {
            throw new Exception("Invalid NMI credentials for account: {$account->name}");
        }
    }

    /**
     * Initialize from default NMI account
     */
    protected function initializeFromDefaultAccount(bool $isLive = false): void
    {
        $paymentMethod = PaymentMethod::where('name', 'nmi')->first();
        if (!$paymentMethod) {
            throw new Exception('NMI payment method not found');
        }

        $account = Account::where('payment_method_id', $paymentMethod->id)
            ->where('status', true)
            ->first();

        if (!$account) {
            throw new Exception('No active NMI account found');
        }

        $this->account = $account;
        $this->initializeFromAccount($account, $isLive);
    }

    /**
     * Create payment method - simplified approach
     */
    public function createPaymentMethod(array $cardData, User $user, array $billingInfo = []): array
    {
        try {
            if ($this->vaultEnabled) {
                return $this->createCustomerVault($cardData, $user, $billingInfo);
            } else {
                return $this->createDirectPayment($cardData, $user);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer vault with card (NMI's actual flow)
     */
    protected function createCustomerVault(array $cardData, User $user, array $billingInfo = []): array
    {
        try {
            // Format expiry date (MMYY format for NMI)
            $expMonth = str_pad($cardData['exp_month'], 2, '0', STR_PAD_LEFT);
            $expYear = substr($cardData['exp_year'], -2);

            // Generate customer vault ID
            $customerVaultId = uniqid('vault_');

            // Handle user name
            $firstName = $billingInfo['first_name'] ?? '';
            $lastName = $billingInfo['last_name'] ?? '';

            if (empty($firstName) && empty($lastName) && !empty($user->name)) {
                $nameParts = explode(' ', trim($user->name), 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
            }

            // Prepare vault data with required billing info
            $vaultData = [
                'security_key' => $this->secretApiKey,
                'customer_vault' => 'add_customer',
                'customer_vault_id' => $customerVaultId,
                'ccnumber' => preg_replace('/\s+/', '', $cardData['card_number'] ?? $cardData['number']),
                'ccexp' => $expMonth . $expYear,
                'cvv' => $cardData['cvv'],
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                // Required billing fields - use provided or defaults
                'address1' => $billingInfo['address1'] ?? $billingInfo['address'] ?? '123 Main Street',
                'city' => $billingInfo['city'] ?? 'Anytown',
                'state' => $billingInfo['state'] ?? 'CA',
                'zip' => $billingInfo['zip'] ?? '12345',
                'country' => $billingInfo['country'] ?? 'US',
                'phone' => $billingInfo['phone'] ?? '',
                'company' => $billingInfo['company'] ?? '',
            ];

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $vaultData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            if ($result['response'] == '1') {
                Log::info('NMI Customer Vault created successfully', [
                    'customer_vault_id' => $customerVaultId,
                    'user_id' => $user->id
                ]);

                return [
                    'success' => true,
                    'token' => $customerVaultId,
                    'last4' => substr($cardData['number'], -4),
                    'gateway_response' => $result,
                    'vault_enabled' => true
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to create customer vault');

        } catch (Exception $e) {
            Log::error('NMI Customer Vault creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'vault_error' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create direct payment method (fallback)
     */
    protected function createDirectPayment(array $cardData, User $user): array
    {
        try {
            // For direct payments, we'll use a simple token approach
            $token = 'direct_' . uniqid();

            return [
                'success' => true,
                'token' => $token,
                'last4' => substr($cardData['number'], -4),
                'vault_enabled' => false
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create subscription
     */
    public function createSubscription(
        string $customerId,
        string $planId,
        ?Card $card = null,
        array $options = []
    ): array {
        try {
            // Format start date properly for NMI (YYYYMMDD format)
            $startDate = $options['start_date'] ?? null;

            $subscriptionData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'add_subscription',
                'plan_id' => $planId,
            ];

            // Handle start_date - NMI requires specific format and rules
            if ($startDate) {
                try {
                    $dateObj = new \DateTime($startDate);
                    $today = new \DateTime('today');

                    // NMI requires start date to be at least tomorrow for recurring subscriptions
                    if ($dateObj <= $today) {
                        $dateObj = new \DateTime('tomorrow');
                        Log::info('Start date adjusted to tomorrow for NMI requirements', [
                            'original_date' => $startDate,
                            'adjusted_date' => $dateObj->format('Y-m-d')
                        ]);
                    }

                    $formattedDate = $dateObj->format('Ymd');
                    $subscriptionData['start_date'] = $formattedDate;

                    Log::info('NMI subscription start date set', [
                        'original_date' => $startDate,
                        'formatted_date' => $formattedDate
                    ]);

                } catch (\Exception $e) {
                    // Invalid date format, use tomorrow as default
                    $tomorrow = new \DateTime('tomorrow');
                    $subscriptionData['start_date'] = $tomorrow->format('Ymd');

                    Log::warning('Invalid start date format, using tomorrow', [
                        'start_date' => $startDate,
                        'error' => $e->getMessage(),
                        'default_date' => $tomorrow->format('Ymd')
                    ]);
                }
            } else {
                // No start date provided, use tomorrow (NMI requirement for recurring)
                $tomorrow = new \DateTime('tomorrow');
                $subscriptionData['start_date'] = $tomorrow->format('Ymd');

                Log::info('No start date provided, using tomorrow', [
                    'default_date' => $tomorrow->format('Ymd')
                ]);
            }

            // Add customer/payment method info
            if ($card && $card->token && strpos($card->token, 'direct_') !== 0) {
                // Use customer vault
                $subscriptionData['customer_vault_id'] = $card->token;
                Log::info('Using customer vault for subscription', ['vault_id' => $card->token]);
            } else {
                // Use direct customer ID (fallback)
                $subscriptionData['customer_id'] = $customerId;
                Log::info('Using customer ID for subscription', ['customer_id' => $customerId]);
            }

            // Validate required fields
            if (empty($subscriptionData['plan_id'])) {
                throw new Exception('Plan ID is required for subscription creation');
            }

            if (empty($subscriptionData['customer_vault_id']) && empty($subscriptionData['customer_id'])) {
                throw new Exception('Either customer_vault_id or customer_id is required');
            }

            // Log the subscription data being sent (without sensitive info)
            $logData = $subscriptionData;
            unset($logData['security_key']);
            Log::info('Creating NMI subscription', $logData);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $subscriptionData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            // Log the response (without sensitive info)
            Log::info('NMI subscription response', [
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown'
            ]);

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'subscription_id' => $result['subscription_id'] ?? uniqid('sub_'),
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to create subscription');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Create custom subscription with vault ID (creates customer, card, and subscription)
     * Uses the same customer creation flow as subscribe plan
     */
    public function createCustomSubscription(User $user, array $payload, ?GatewayPackage $gatewayPackage = null): array
    {
        try {
            DB::beginTransaction();

            // Generate unique customer vault ID if not provided
            $customerVaultId = 'vault_' . uniqid() . '_' . $user->id;

            // Step 2: Create customer vault (card) using custom method
            $cardResult = $this->createCustomerVaultWithId($customerVaultId, $payload['card_info'], $payload['billing_info']);
            if (!$cardResult['success']) {
                throw new Exception('Failed to create customer vault: ' . $cardResult['error']);
            }

            // Extract the customer vault ID from the card result
            $customerVaultId = $cardResult['customer_vault_id'] ?? $customerVaultId;

            // Step 3: Create custom subscription on NMI using the vault ID
            $subscriptionPayload = array_merge($payload, [
                'customer_vault_id' => $customerVaultId
            ]);
            $subscriptionResult = $this->createCustomSubscriptionOnNMI($subscriptionPayload);
            if (!$subscriptionResult['success']) {
                throw new Exception('Failed to create subscription: ' . $subscriptionResult['error']);
            }

            // Step 4: Create local customer record
            $customer = $this->createLocalCustomer($user, $customerVaultId, $cardResult);

            // Step 5: Create local card record
            $card = $this->createLocalCardForCustomSubscription($user, $payload['card_info'], $customerVaultId);

            // Step 6: Create local subscription record
            $subscription = $this->createLocalCustomSubscription(
                $user,
                $gatewayPackage,
                $subscriptionResult,
                $cardResult['card'] ?? null,
                $subscriptionPayload
            );

            DB::commit();

            Log::info('Custom subscription created successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $subscription->getway_subscription_id,
                'customer_vault_id' => $customerVaultId
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'customer' => $customer,
                'card' => $card,
                'subscription_id' => $subscriptionResult['subscription_id'],
                'customer_vault_id' => $customerVaultId,
                'gateway_response' => $subscriptionResult['gateway_response']
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Custom subscription creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Create custom subscription on NMI gateway
     */
    public function createCustomSubscriptionOnNMI(array $payload): array
    {
        try {
            $subscriptionData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'add_subscription',
                'customer_vault_id' => $payload['customer_vault_id'],
                'plan_amount' => number_format($payload['plan_amount'], 2, '.', ''),
                'plan_payments' =>$payload['plan_payments'] ?? 0, // 0 means unlimited payments
                'day_frequency' => $payload['day_frequency'] ?? null,
                'month_frequency' => $payload['month_frequency'] ?? null,
                'day_of_month' => $payload['day_of_month'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'description' => $payload['description'] ?? null,
            ];

            // Add day_of_month if using monthly frequency (required by NMI)
            if (!empty($payload['month_frequency']) || (!empty($payload['day_frequency']) && $payload['day_frequency'] >= 28)) {
                $dayOfMonth = $payload['day_of_month'] ?? date('j'); // Use current day or provided day
                $subscriptionData['day_of_month'] = $dayOfMonth;
                // Store in payload for later use in subscription extra
                $payload['day_of_month'] = $dayOfMonth;
            }

            Log::info('Creating custom NMI subscription', [
                'customer_vault_id' => $payload['customer_vault_id'],
                'plan_amount' => $payload['plan_amount'],
                'subscription_data' => $subscriptionData
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $subscriptionData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            Log::info('NMI custom subscription response', [
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown'
            ]);

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'subscription_id' => $result['subscription_id'] ?? uniqid('sub_'),
                    'gateway_response' => $result,
                    'day_of_month' => $subscriptionData['day_of_month'] ?? null
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to create custom subscription');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create customer vault (card) on NMI with specific vault ID
     */
    protected function createCustomerVaultWithId(string $customerVaultId, array $cardData, array $billingInfo): array
    {
        try {
            // Format expiry date (MMYY format for NMI)
            $expMonth = str_pad($cardData['exp_month'], 2, '0', STR_PAD_LEFT);
            $expYear = substr($cardData['exp_year'], -2);

            $vaultData = [
                'security_key' => $this->secretApiKey,
                'customer_vault' => 'add_customer',
                'customer_vault_id' => $customerVaultId,
                'ccnumber' => preg_replace('/\s+/', '', $cardData['card_number']),
                'ccexp' => $expMonth . $expYear,
                'cvv' => $cardData['cvv'],
                'first_name' => $billingInfo['first_name'],
                'last_name' => $billingInfo['last_name'],
                'email' => $billingInfo['email'],
                'address1' => $billingInfo['address'],
                'city' => $billingInfo['city'],
                'state' => $billingInfo['state'],
                'zip' => $billingInfo['zip'],
            ];

            if (!empty($billingInfo['phone'])) {
                $vaultData['phone'] = $billingInfo['phone'];
            }

            if (!empty($billingInfo['country'])) {
                $vaultData['country'] = $billingInfo['country'];
            }

            Log::info('Creating customer vault', ['customer_vault_id' => $customerVaultId]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $vaultData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'customer_vault_id' => $customerVaultId,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to create customer vault');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create local custom subscription record
     */
    protected function createLocalCustomSubscription(
        User $user,
        ?GatewayPackage $gatewayPackage,
        array $subscriptionResult,
        ?Card $card,
        array $payload
    ): Subscription {
        $startDate = $payload['start_date'] ?? now()->toDateString();

        // Determine billing cycle from frequency
        $billingCycle = 'monthly'; // default
        if (!empty($payload['day_frequency'])) {
            if ($payload['day_frequency'] == 1) $billingCycle = 'daily';
            elseif ($payload['day_frequency'] == 7) $billingCycle = 'weekly';
            elseif ($payload['day_frequency'] == 30) $billingCycle = 'monthly';
            elseif ($payload['day_frequency'] == 365) $billingCycle = 'yearly';
        } elseif (!empty($payload['month_frequency'])) {
            if ($payload['month_frequency'] == 1) $billingCycle = 'monthly';
            elseif ($payload['month_frequency'] == 3) $billingCycle = 'quarterly';
            elseif ($payload['month_frequency'] == 6) $billingCycle = 'bi-quarterly';
            elseif ($payload['month_frequency'] == 12) $billingCycle = 'yearly';
        }

        $subscriptionData = [
            'uuid' => Str::uuid(),
            'billing_cycle' => $billingCycle,
            'user_id' => $user->getKey(),
            'gateway_package_id' => $gatewayPackage?->getKey(),
            'getway_subscription_id' => $subscriptionResult['subscription_id'],
            'start_date' => $startDate,
            'status' => 'active',
            'extra' => [
                'gateway_response' => $subscriptionResult['gateway_response'] ?? [],
                'customer_id' => $payload['customer_vault_id'], // Use vault ID for consistency
                'card_token' => $card?->token ?? $payload['customer_vault_id'],
                'custom_subscription' => true,
                'plan_amount' => $payload['plan_amount'],
                'plan_payments' => $payload['plan_payments'] ?? 0,
                'day_frequency' => $payload['day_frequency'] ?? null,
                'month_frequency' => $payload['month_frequency'] ?? null,
                'day_of_month' => $payload['day_of_month'] ?? $subscriptionResult['day_of_month'] ?? null,
                'order_id' => $payload['order_id'] ?? null,
                'description' => $payload['description'] ?? null,
                'created_at' => now()->toISOString()
            ]
        ];

        return Subscription::create($subscriptionData);
    }

    /**
     * Create local customer record for custom subscription
     */
    protected function createLocalCustomer(User $user, string $customerVaultId, array $cardResult): \App\Models\Customer
    {
        return \App\Models\Customer::create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'payment_method_id' => $this->account->payment_method_id,
            'gateway_customer_id' => $customerVaultId,
            'extra' => [
                'gateway_response' => $cardResult['gateway_response'] ?? [],
                'customer_management' => true,
                'custom_subscription' => true,
                'created_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Create local card record for custom subscription
     */
    protected function createLocalCardForCustomSubscription(User $user, array $cardData, string $customerVaultId): Card
    {
        // Create expiry date from month/year
        $expiryDate = \Carbon\Carbon::createFromFormat('m/Y', $cardData['exp_month'] . '/' . $cardData['exp_year'])->endOfMonth();

        return Card::create([
            'name' => 'Custom Subscription Card',
            'payment_method_id' => $this->account->payment_method_id,
            'token' => $customerVaultId,
            'last4' => substr($cardData['card_number'], -4),
            'expiry' => $expiryDate,
            'extra' => [
                'customer_vault_id' => $customerVaultId,
                'exp_month' => $cardData['exp_month'],
                'exp_year' => $cardData['exp_year'],
                'custom_subscription' => true,
                'created_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Update subscription card on NMI
     */
    public function updateSubscriptionCard(Subscription $subscription, array $payload): array
    {
        try {
            DB::beginTransaction();

            // Get customer vault ID from subscription
            $customerVaultId = $subscription->extra['customer_id'] ?? $subscription->extra['gateway_response']['customer_vault_id'] ?? null;

            if (!$customerVaultId) {
                throw new Exception('Customer vault ID not found in subscription');
            }

            // Update customer vault on NMI
            $updateResult = $this->updateCustomerVaultOnNMI($customerVaultId, $payload['card_info'], $payload['billing_info']);
            if (!$updateResult['success']) {
                throw new Exception('Failed to update customer vault: ' . $updateResult['error']);
            }

            // Update the subscription's payment method to use the updated customer vault
            $gatewaySubscriptionId = $subscription->getway_subscription_id;
            if ($gatewaySubscriptionId) {
                $subscriptionUpdateResult = $this->updateSubscriptionPaymentMethod($gatewaySubscriptionId, $customerVaultId);
                if (!$subscriptionUpdateResult['success']) {
                    Log::warning('Failed to update subscription payment method, but customer vault was updated', [
                        'subscription_id' => $subscription->id,
                        'gateway_subscription_id' => $gatewaySubscriptionId,
                        'customer_vault_id' => $customerVaultId,
                        'error' => $subscriptionUpdateResult['error']
                    ]);
                    // Don't fail the entire operation since customer vault was updated successfully
                }
            }

            // Update local card record
            $card = Card::where('token', $customerVaultId)->first();
            if ($card) {
                $expiryDate = \Carbon\Carbon::createFromFormat('m/Y', $payload['card_info']['exp_month'] . '/' . $payload['card_info']['exp_year'])->endOfMonth();

                $card->update([
                    'last4' => substr($payload['card_info']['card_number'], -4),
                    'expiry' => $expiryDate,
                    'extra' => array_merge($card->extra ?? [], [
                        'exp_month' => $payload['card_info']['exp_month'],
                        'exp_year' => $payload['card_info']['exp_year'],
                        'updated_at' => now()->toISOString()
                    ])
                ]);
            }

            // Update subscription extra with new card info
            $subscription->update([
                'extra' => array_merge($subscription->extra ?? [], [
                    'card_updated_at' => now()->toISOString(),
                    'last_card_update' => $updateResult['gateway_response'] ?? [],
                    'subscription_payment_method_updated' => isset($subscriptionUpdateResult) ? $subscriptionUpdateResult['success'] : false
                ])
            ]);

            DB::commit();

            Log::info('Subscription card updated successfully', [
                'subscription_id' => $subscription->id,
                'customer_vault_id' => $customerVaultId,
                'subscription_payment_method_updated' => isset($subscriptionUpdateResult) ? $subscriptionUpdateResult['success'] : false
            ]);

            return [
                'success' => true,
                'subscription' => $subscription->fresh(),
                'customer_vault_id' => $customerVaultId,
                'gateway_response' => $updateResult['gateway_response'] ?? []
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Subscription card update failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription amount on NMI
     */
    public function updateSubscriptionAmount(Subscription $subscription, array $payload): array
    {
        try {
            DB::beginTransaction();

            // Get gateway subscription ID
            $gatewaySubscriptionId = $subscription->getway_subscription_id;

            if (!$gatewaySubscriptionId) {
                throw new Exception('Gateway subscription ID not found');
            }
            // Update subscription on NMI
            $updateResult = $this->updateSubscriptionOnNMI($gatewaySubscriptionId, $payload);
            if (!$updateResult['success']) {
                throw new Exception('Failed to update subscription: ' . $updateResult['error']);
            }

            // Determine new billing cycle from frequency
            $billingCycle = $subscription->billing_cycle; // Keep current as default
            if (!empty($payload['day_frequency'])) {
                if ($payload['day_frequency'] == 1) $billingCycle = 'daily';
                elseif ($payload['day_frequency'] == 7) $billingCycle = 'weekly';
                elseif ($payload['day_frequency'] == 30) $billingCycle = 'monthly';
                elseif ($payload['day_frequency'] == 365) $billingCycle = 'yearly';
            } elseif (!empty($payload['month_frequency'])) {
                if ($payload['month_frequency'] == 1) $billingCycle = 'monthly';
                elseif ($payload['month_frequency'] == 3) $billingCycle = 'quarterly';
                elseif ($payload['month_frequency'] == 6) $billingCycle = 'bi-quarterly';
                elseif ($payload['month_frequency'] == 12) $billingCycle = 'yearly';
            }

            // Update local subscription record
            $subscription->update([
                'billing_cycle' => $billingCycle,
                'extra' => array_merge($subscription->extra ?? [], [
                    'plan_amount' => $payload['plan_amount'],
                    'plan_payments' => $payload['plan_payments'] ?? $subscription->extra['plan_payments'] ?? 0,
                    'day_frequency' => $payload['day_frequency'] ?? $subscription->extra['day_frequency'] ?? null,
                    'month_frequency' => $payload['month_frequency'] ?? $subscription->extra['month_frequency'] ?? null,
                    'day_of_month' => $payload['day_of_month'] ?? $updateResult['day_of_month'] ?? $subscription->extra['day_of_month'] ?? null,
                    'amount_updated_at' => now()->toISOString(),
                    'last_amount_update' => $updateResult['gateway_response'] ?? []
                ])
            ]);

            DB::commit();

            Log::info('Subscription amount updated successfully', [
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $gatewaySubscriptionId,
                'new_amount' => $payload['plan_amount']
            ]);

            return [
                'success' => true,
                'subscription' => $subscription->fresh(),
                'gateway_subscription_id' => $gatewaySubscriptionId,
                'gateway_response' => $updateResult['gateway_response'] ?? []
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Subscription amount update failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

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

            // Get customer vault ID from subscription
            $customerVaultId = isset($payload['customer_id']) ? Card::where('uuid', $payload['customer_id'])->value('token'):null;
            if (!$customerVaultId) {
                $customerVaultId = $subscription->extra['customer_id'] ?? $subscription->extra['gateway_response']['customer_vault_id'] ?? null;
            }
            if (!$customerVaultId) {
                throw new Exception('Customer vault ID not found in subscription');
            }
            $chargeData = [
                'security_key' => $this->secretApiKey,
                'customer_vault_id' => $customerVaultId,
                "subscription_id" => $subscription->getway_subscription_id,
                "recurring" => 'charge_subscription',
                'amount' => number_format($payload['amount'], 2, '.', ''),
                'description' => $payload['description'] ?? 'Manual charge against subscription #' . $subscription->id,
                "order_id" => "subscription_" . $subscription->uuid?? null
            ];

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $chargeData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            if ($result['response'] == '1') {
                DB::commit();
                return [
                    'success' => true,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to process manual charge');

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update customer vault on NMI
     */
    protected function updateCustomerVaultOnNMI(string $customerVaultId, array $cardData, array $billingInfo): array
    {
        try {
            // Format expiry date (MMYY format for NMI)
            $expMonth = str_pad($cardData['exp_month'], 2, '0', STR_PAD_LEFT);
            $expYear = substr($cardData['exp_year'], -2);

            $vaultData = [
                'security_key' => $this->secretApiKey,
                'customer_vault' => 'update_customer',
                'customer_vault_id' => $customerVaultId,
                'ccnumber' => preg_replace('/\s+/', '', $cardData['card_number']),
                'ccexp' => $expMonth . $expYear,
                'cvv' => $cardData['cvv'],
                'first_name' => $billingInfo['first_name'],
                'last_name' => $billingInfo['last_name'],
                'email' => $billingInfo['email'],
            ];

            if (!empty($billingInfo['phone'])) {
                $vaultData['phone'] = $billingInfo['phone'];
            }

            if (!empty($billingInfo['address'])) {
                $vaultData['address1'] = $billingInfo['address'];
            }

            if (!empty($billingInfo['city'])) {
                $vaultData['city'] = $billingInfo['city'];
            }

            if (!empty($billingInfo['state'])) {
                $vaultData['state'] = $billingInfo['state'];
            }

            if (!empty($billingInfo['zip'])) {
                $vaultData['zip'] = $billingInfo['zip'];
            }

            if (!empty($billingInfo['country'])) {
                $vaultData['country'] = $billingInfo['country'];
            }

            Log::info('Updating customer vault on NMI', [
                'customer_vault_id' => $customerVaultId,
                'vault_data' => array_merge($vaultData, ['ccnumber' => '****' . substr($vaultData['ccnumber'], -4), 'cvv' => '***'])
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $vaultData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            Log::info('NMI customer vault update response', [
                'customer_vault_id' => $customerVaultId,
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown'
            ]);

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'customer_vault_id' => $customerVaultId,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to update customer vault');

        } catch (Exception $e) {
            Log::error('NMI customer vault update failed', [
                'customer_vault_id' => $customerVaultId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription on NMI
     */
    protected function updateSubscriptionOnNMI(string $subscriptionId, array $payload): array
    {
        try {
            $subscriptionData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'update_subscription',
                'subscription_id' => $subscriptionId,
                'plan_amount' => number_format($payload['plan_amount'], 2, '.', ''),
            ];

            // Add optional parameters
            if (isset($payload['plan_payments'])) {
                $subscriptionData['plan_payments'] = $payload['plan_payments'];
            }

            //update next billing date
            if (isset($payload['next_billing_date'])) {
                $subscriptionData['next_billing_date'] = $payload['next_billing_date'];
            }

            if (!empty($payload['day_frequency'])) {
                $subscriptionData['day_frequency'] = $payload['day_frequency'];
            }

            if (!empty($payload['month_frequency'])) {
                $subscriptionData['month_frequency'] = $payload['month_frequency'];
            }

            // // Add day_of_month if using monthly frequency (required by NMI)
            // if (!empty($payload['month_frequency']) || (!empty($payload['day_frequency']) && $payload['day_frequency'] >= 28)) {
            //     $dayOfMonth = $payload['day_of_month'] ?? date('j'); // Use current day or provided day
            //     $subscriptionData['day_of_month'] = $dayOfMonth;
            //     // Store in payload for later use in subscription extra
            //     $payload['day_of_month'] = $dayOfMonth;
            // }

            Log::info('Updating subscription on NMI', [
                'subscription_id' => $subscriptionId,
                'plan_amount' => $payload['plan_amount'],
                'subscription_data' => $subscriptionData
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $subscriptionData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                    'gateway_response' => $result,
                    'day_of_month' => $subscriptionData['day_of_month'] ?? null
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to update subscription');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create or get existing customer
     */
    protected function createOrGetCustomer(User $user, array $billingInfo): array
    {
        try {
            // For custom subscriptions, we'll just return user info
            // In a real implementation, you might want to create/update customer records
            return [
                'success' => true,
                'customer_id' => $user->uuid,
                'user' => $user,
                'billing_info' => $billingInfo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription payment method
     */
    public function updateSubscriptionPaymentMethod(string $subscriptionId, string $newCardToken, ?string $billingId = null): array
    {
        try {
            $updateData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'update_subscription',
                'subscription_id' => $subscriptionId,
            ];

            if ($billingId) {
                $updateData['billing_id'] = $billingId;
            } else {
                $updateData['customer_vault_id'] = $newCardToken;
            }

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $updateData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to update subscription payment method');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse NMI response
     */
    protected function parseResponse(string $response): array
    {
        parse_str($response, $result);

        $result['response'] = $result['response'] ?? '0';
        $result['responsetext'] = $result['responsetext'] ?? 'Unknown error';

        return $result;
    }

    // Required abstract methods from PaymentGatewayService
    public function createCustomer(User $user, array $billingInfo = []): array
    {
        return ['success' => true, 'customer_id' => 'customer_' . $user->uuid, 'customer_management' => true];
    }

    public function updateSubscription(GatewayPackage $gatewayPackage)
    {
        return ['success' => true];
    }

    /**
     * Update subscription plan on NMI
     */
    public function updateSubscriptionPlan(string $subscriptionId, string $newPlanId): array
    {
        try {
            $updateData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'update_subscription',
                'subscription_id' => $subscriptionId,
                'plan_id' => $newPlanId,
            ];

            Log::info('Updating NMI subscription plan', [
                'subscription_id' => $subscriptionId,
                'new_plan_id' => $newPlanId
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $updateData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            Log::info('NMI subscription plan update response', [
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown'
            ]);

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to update subscription plan');

        } catch (Exception $e) {
            Log::error('NMI subscription plan update failed', [
                'subscription_id' => $subscriptionId,
                'new_plan_id' => $newPlanId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return ['success' => true];
    }

    public function getSubscription(string $subscriptionId): array
    {
        return ['success' => true];
    }

    public function createSubscriptionPlan(GatewayPackage $gatewayPackage): array
    {
        try {
            $package = $gatewayPackage->package;

            if (!$package) {
                throw new Exception('Package not found for gateway package');
            }

            // Generate unique plan ID
            $planId = 'plan_' . $package->uuid;

            // Convert billing cycle to NMI format
            $frequency = $this->convertFrequencyToNmi($package->billing_cycle);

            $planData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'add_plan',
                'plan_id' => $planId,
                'plan_name' => $package->name,
                'plan_amount' => number_format($package->price, 2, '.', ''),
                'plan_payments' => 0, // 0 means unlimited payments
            ];

            // Add frequency parameters
            $planData = array_merge($planData, $frequency);

            Log::info('Creating NMI subscription plan', [
                'plan_id' => $planId,
                'package_id' => $package->id,
                'gateway_package_id' => $gatewayPackage->id
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $planData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            Log::info('NMI plan creation response', [
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown',
                'plan_id' => $planId
            ]);


            if ($result['response'] == '1') {
                // Update gateway package with the plan ID
                $gatewayPackage->update(['gateway_id' => $planId]);

                return [
                    'success' => true,
                    'plan_id' => $planId,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to create subscription plan');

        } catch (Exception $e) {
            Log::error('NMI plan creation failed', [
                'gateway_package_id' => $gatewayPackage->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function disableSubscriptionPlan(GatewayPackage $gatewayPackage): array
    {
        try {
            if (!$gatewayPackage->gateway_id) {
                throw new Exception('No gateway plan ID found for this package');
            }

            $planData = [
                'security_key' => $this->secretApiKey,
                'recurring' => 'delete_plan',
                'plan_id' => $gatewayPackage->gateway_id,
            ];

            Log::info('Disabling NMI subscription plan', [
                'plan_id' => $gatewayPackage->gateway_id,
                'gateway_package_id' => $gatewayPackage->id
            ]);

            $response = Http::timeout(30)->asForm()->post($this->apiUrl, $planData);

            if (!$response->successful()) {
                throw new Exception("HTTP request failed with status: " . $response->status());
            }

            $result = $this->parseResponse($response->body());

            Log::info('NMI plan disable response', [
                'response_code' => $result['response'] ?? 'unknown',
                'response_text' => $result['responsetext'] ?? 'unknown',
                'plan_id' => $gatewayPackage->gateway_id
            ]);

            if ($result['response'] == '1') {
                return [
                    'success' => true,
                    'gateway_response' => $result
                ];
            }

            throw new Exception($result['responsetext'] ?? 'Failed to disable subscription plan');

        } catch (Exception $e) {
            Log::error('NMI plan disable failed', [
                'gateway_package_id' => $gatewayPackage->id,
                'plan_id' => $gatewayPackage->gateway_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function recreateSubscriptionPlan(GatewayPackage $gatewayPackage): array
    {
        try {
            // First disable the existing plan if it exists
            if ($gatewayPackage->gateway_id) {
                $disableResult = $this->disableSubscriptionPlan($gatewayPackage);
                if (!$disableResult['success']) {
                    Log::warning('Failed to disable existing plan during recreation', [
                        'gateway_package_id' => $gatewayPackage->id,
                        'plan_id' => $gatewayPackage->gateway_id,
                        'error' => $disableResult['error']
                    ]);
                }
            }

            // Clear the gateway_id to force creation of new plan
            $gatewayPackage->update(['gateway_id' => null]);

            // Create new plan
            $createResult = $this->createSubscriptionPlan($gatewayPackage);

            if ($createResult['success']) {
                Log::info('NMI plan recreated successfully', [
                    'gateway_package_id' => $gatewayPackage->id,
                    'new_plan_id' => $createResult['plan_id']
                ]);
            }

            return $createResult;

        } catch (Exception $e) {
            Log::error('NMI plan recreation failed', [
                'gateway_package_id' => $gatewayPackage->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function deletePaymentMethod(string $paymentMethodId): array
    {
        return ['success' => true];
    }

    public function processPayment(float $amount, Card $card, array $billingInfo = [], array $options = []): array
    {
        return ['success' => true, 'transaction_id' => uniqid('txn_')];
    }

    /**
     * Convert package frequency to NMI frequency parameters
     */
    protected function convertFrequencyToNmi(string $frequency): array
    {
        switch (strtolower($frequency)) {
            case 'daily':
                return ['day_frequency' => '1'];

            case 'weekly':
                return ['day_frequency' => '7'];

            case 'monthly':
                return [
                    'month_frequency' => '1',
                    'day_of_month' => '1'
                ];

            case 'quarterly':
                return [
                    'month_frequency' => '3',
                    'day_of_month' => '1'
                ];

            case 'bi-quarterly':
            case 'semi-annually':
            case 'semiannually':
                return [
                    'month_frequency' => '6',
                    'day_of_month' => '1'
                ];

            case 'annually':
            case 'yearly':
                return [
                    'month_frequency' => '12',
                    'day_of_month' => '1'
                ];

            default:
                // Default to monthly if frequency is not recognized
                Log::warning('Unknown frequency, defaulting to monthly', ['frequency' => $frequency]);
                return [
                    'month_frequency' => '1',
                    'day_of_month' => '1'
                ];
        }
    }

    public function processWebhook(array $webhookData): array
    {
        try {
            DB::beginTransaction();

            // Normalize payload (supports new JSON webhook format)
            $webhookData = $this->normalizeNmiPayload($webhookData);

            // Check for duplicate webhook processing
            $transactionId = $webhookData['transactionid'] ?? null;
            if ($transactionId && $this->isDuplicateWebhook($transactionId)) {
                Log::info('Duplicate NMI webhook ignored', [
                    'transaction_id' => $transactionId
                ]);

                return [
                    'success' => true,
                    'message' => 'Duplicate webhook ignored'
                ];
            }

            // Determine webhook type based on NMI data
            $webhookType = $this->determineWebhookType($webhookData);

            Log::info('Processing NMI webhook', [
                'type' => $webhookType,
                'transaction_id' => $transactionId
            ]);

            $result = match($webhookType) {
                'subscription_payment' => $this->handleSubscriptionPayment($webhookData),
                'subscription_created' => $this->handleSubscriptionCreated($webhookData),
                'subscription_cancelled' => $this->handleSubscriptionCancelled($webhookData),
                'payment_success' => $this->handlePaymentSuccess($webhookData),
                'payment_failed' => $this->handlePaymentFailed($webhookData),
                'refund' => $this->handleRefund($webhookData),
                'chargeback' => $this->handleChargeback($webhookData),
                default => $this->handleUnknownWebhook($webhookData)
            };

            if ($result['success']) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('NMI webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $webhookData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // NMI doesn't use signature validation like Stripe
        // Instead, validate the webhook secret if provided in the payload
        $data = [];
        parse_str($payload, $data);

        // Check if webhook secret matches (if provided)
        if (!empty($this->webhookSecret) && isset($data['webhook_secret'])) {
            return hash_equals($this->webhookSecret, $data['webhook_secret']);
        }

        // Validate source IP (NMI webhook IPs)
        $allowedIPs = [
            '69.46.86.0/24',    // NMI webhook IP range
            '208.109.54.0/24',  // NMI webhook IP range
            '127.0.0.1',        // Local testing
        ];

        $clientIP = request()->ip();
        foreach ($allowedIPs as $allowedIP) {
            if ($this->ipInRange($clientIP, $allowedIP)) {
                return true;
            }
        }

        Log::warning('NMI webhook from unauthorized IP', [
            'client_ip' => $clientIP,
            'allowed_ips' => $allowedIPs
        ]);

        return false;
    }

    /**
     * Check if IP is in range
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) == $subnet;
    }

    /**
     * Check if webhook has already been processed
     */
    protected function isDuplicateWebhook(string $transactionId): bool
    {
        // Check if payment with this transaction ID already exists
        return Payment::where('gateway_id', $transactionId)->exists();
    }

    /**
     * Determine webhook type from NMI data
     */
    protected function determineWebhookType(array $data): string
    {
        // JSON event types mapping
        $eventType = $data['event_type'] ?? null;
        $isJsonPayload = !empty($eventType);

        // Remove excessive debug logging for production

        if ($isJsonPayload) {
            // Recurring subscription payments often marked by source=recurring
            if (($data['source'] ?? null) === 'recurring' && isset($data['transactionid']) && ($data['response'] ?? '') == '1') {
                return 'subscription_payment';
            }

            // Map common JSON transaction events
            if (str_contains($eventType, 'transaction.sale.success')) {
                return 'payment_success';
            }
            if (str_contains($eventType, 'transaction.sale.failed') || str_contains($eventType, 'transaction.sale.declined')) {
                return 'payment_failed';
            }
            if (str_contains($eventType, 'transaction.refund.success')) {
                return 'refund';
            }
            if (str_contains($eventType, 'transaction.chargeback') || str_contains($eventType, 'chargeback.batch.complete')) {
                return 'chargeback';
            }

            // Additional check for refunds based on action_type
            if (($data['action_type'] ?? null) === 'refund') {
                return 'refund';
            }
        }

        // Legacy format fallbacks (simplified)
        if (isset($data['subscription_id'])) {
            return match($data['action'] ?? null) {
                'created' => 'subscription_created',
                'cancelled' => 'subscription_cancelled',
                default => isset($data['transactionid']) && ($data['response'] ?? '') == '1'
                    ? 'subscription_payment'
                    : 'unknown'
            };
        }

        if (isset($data['transactionid'])) {
            return ($data['response'] ?? '') == '1' ? 'payment_success' : 'payment_failed';
        }

        if (isset($data['type'])) {
            return match($data['type']) {
                'refund' => 'refund',
                'chargeback' => 'chargeback',
                default => 'unknown'
            };
        }

        return 'unknown';
    }
    /**
     * Normalize NMI webhook payloads (JSON or legacy form data)
     */
    protected function normalizeNmiPayload(array $payload): array
    {
        // If JSON event_type present, flatten to legacy keys our code expects
        if (isset($payload['event_type']) && isset($payload['event_body']) && is_array($payload['event_body'])) {
            $e = $payload['event_body'];
            $action = $e['action'] ?? [];
            $card = $e['card'] ?? [];

            // Map cc_type to shorter values to fit database column (15 chars max)
            $ccType = $card['cc_type'] ?? null;
            if ($ccType) {
                $ccTypeMap = [
                    'American Express' => 'Amex',
                    'Discover' => 'Discover',
                    'MasterCard' => 'MasterCard',
                    'Visa' => 'Visa',
                ];
                $ccType = $ccTypeMap[$ccType] ?? substr($ccType, 0, 15);
            }

            // Special handling for chargeback batch format
            if (str_contains($payload['event_type'], 'chargeback.batch.complete')) {
                $flattened = [
                    'event_type' => $payload['event_type'],
                    'chargeback_batch' => true,
                    'chargeback_count' => $e['count'] ?? 0,
                    'chargeback_amount' => $e['chargeback_amount'] ?? null,
                    'chargebacks' => $e['chargebacks'] ?? [],
                    'merchant' => $e['merchant'] ?? null,
                    'processor' => $e['processor'] ?? null,
                ];
            } else {
                $flattened = [
                    // Legacy-like keys our handlers expect
                    'event_type' => $payload['event_type'],
                    'transaction_id' => $e['transaction_id'] ?? null, // New JSON format uses transaction_id
                    'transactionid' => $e['transaction_id'] ?? null, // Legacy compatibility
                    'amount' => $action['amount'] ?? ($e['requested_amount'] ?? null),
                    'requested_amount' => $e['requested_amount'] ?? null, // For refunds
                    'response' => !empty($action['success']) && (string)$action['success'] === '1' ? '1' : '2',
                    'responsetext' => $action['response_text'] ?? null,
                    'ip' => $action['ip_address'] ?? null,
                    'ip_address' => $action['ip_address'] ?? null, // New format
                    'cc_type' => $ccType,
                    'customerid' => $e['customerid'] ?? null,
                    'source' => $action['source'] ?? null,
                    'action_type' => $action['action_type'] ?? null, // For identifying refunds
                ];
            }

            // Payload normalized successfully

            // Keep originals for logging/debugging if needed
            $flattened['_original'] = $payload;
            return $flattened;
        }

        // Already legacy format
        return $payload;
    }


    /**
     * Handle subscription payment webhook
     */
    /**
     * Find subscription by gateway_subscription_id or customerid
     * Enhanced to match subscription_id from gateway responses
     */
    protected function findSubscription(array $data): ?Subscription
    {
        $subscriptionId = $data['subscription_id'] ?? null;
        $customerId = $data['customerid'] ?? null;

        Log::info('Finding subscription for webhook', [
            'subscription_id' => $subscriptionId,
            'customerid' => $customerId
        ]);

        // First try: Direct subscription ID match
        if ($subscriptionId) {
            $subscription = Subscription::where('getway_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                Log::info('Found subscription by gateway_subscription_id', ['subscription_id' => $subscription->id]);
                return $subscription;
            }
        }

        // Second try: Customer vault ID match (most common for recurring payments)
        if ($customerId) {
            $subscription = Subscription::whereJsonContains('extra->gateway_response->customer_vault_id', $customerId)->first();
            if ($subscription) {
                Log::info('Found subscription by customer_vault_id', [
                    'subscription_id' => $subscription->id,
                    'customer_vault_id' => $customerId
                ]);
                return $subscription;
            }

            // Fallback: try extra->customerid (legacy)
            $subscription = Subscription::whereJsonContains('extra->customerid', $customerId)->first();
            if ($subscription) {
                Log::info('Found subscription by legacy customerid', ['subscription_id' => $subscription->id]);
                return $subscription;
            }
        }

        Log::warning('No subscription found for webhook', [
            'subscription_id' => $subscriptionId,
            'customerid' => $customerId
        ]);

        return null;
    }
    protected function handleSubscriptionPayment(array $data): array
    {
        try {
            // Find subscription by gateway_subscription_id or customerid
            $subscription = $this->findSubscription($data);

            if (!$subscription) {
                // Debug: Check what subscriptions exist
                Log::warning('NMI subscription payment for unknown subscription - Debug info', [
                    'webhook_subscription_id' => $data['subscription_id'] ?? null,
                    'webhook_customerid' => $data['customerid'] ?? null
                ]);

                // Create standalone invoice for unknown subscription
                $invoice = $this->createStandaloneInvoice($data, InvoiceStatusEnum::PAID);
                $payment = $this->createPaymentRecord($invoice, $data, PaymentStatusEnum::SUCCESS);

                return [
                    'success' => true,
                    'type' => 'subscription_payment_standalone',
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id
                ];
            }

            // Create invoice for existing subscription
            Log::info('Creating invoice for found subscription', [
                'subscription_id' => $subscription->id,
                'getway_subscription_id' => $subscription->getway_subscription_id,
                'webhook_customerid' => $data['customerid'] ?? null
            ]);

            $invoice = $this->createInvoiceForSubscription($subscription, $data);
            $payment = $this->createPaymentRecord($invoice, $data, PaymentStatusEnum::SUCCESS);

            Log::info('Successfully created subscription payment', [
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'invoice_model_type' => $invoice->model_type,
                'invoice_model_id' => $invoice->model_id
            ]);

            return [
                'success' => true,
                'type' => 'subscription_payment',
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle NMI subscription payment', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle subscription created webhook
     */
    protected function handleSubscriptionCreated(array $data): array
    {
        Log::info('NMI subscription created webhook received', $data);

        return [
            'success' => true,
            'type' => 'subscription_created',
            'message' => 'Subscription created notification received'
        ];
    }

    /**
     * Handle subscription cancelled webhook
     */
    protected function handleSubscriptionCancelled(array $data): array
    {
        try {
            $subscriptionId = $data['subscription_id'] ?? null;

            if ($subscriptionId) {
                $subscription = Subscription::where('getway_subscription_id', $subscriptionId)->first();

                if ($subscription) {
                    $subscription->update(['status' => 'cancelled']);

                    Log::info('NMI subscription cancelled', [
                        'subscription_id' => $subscriptionId,
                        'local_subscription_id' => $subscription->id
                    ]);
                }
            }

            return [
                'success' => true,
                'type' => 'subscription_cancelled',
                'subscription_id' => $subscriptionId
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle NMI subscription cancellation', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle successful payment webhook
     */
    protected function handlePaymentSuccess(array $data): array
    {
        try {
            $invoice = $this->createStandaloneInvoice($data, InvoiceStatusEnum::PAID);
            $payment = $this->createPaymentRecord($invoice, $data, PaymentStatusEnum::SUCCESS);

            Log::info('NMI payment success processed', [
                'transaction_id' => $data['transactionid'] ?? 'unknown',
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id
            ]);

            return [
                'success' => true,
                'type' => 'payment_success',
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle NMI payment success', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle failed payment webhook
     */
    protected function handlePaymentFailed(array $data): array
    {
        try {
            $invoice = $this->createStandaloneInvoice($data, InvoiceStatusEnum::UNPAID);
            $payment = $this->createPaymentRecord($invoice, $data, PaymentStatusEnum::FAILED);

            Log::warning('NMI payment failed processed', [
                'transaction_id' => $data['transactionid'] ?? 'unknown',
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'reason' => $data['responsetext'] ?? 'Unknown error'
            ]);

            return [
                'success' => true,
                'type' => 'payment_failed',
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle NMI payment failure', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Query NMI API to get original transaction ID for refunds and chargebacks
     */
    protected function getOriginalTransactionId(string $transactionId, string $type = 'refund'): ?string
    {
        try {
            // Prepare query data based on transaction type
            if ($type === 'chargeback') {
                $queryData = [
                    'security_key' => $this->secretApiKey,
                    'report_type' => 'chargeback',
                    'id' => $transactionId,
                ];
            } else {
                // Default to refund query format
                $queryData = [
                    'security_key' => $this->secretApiKey,
                    'transaction_id' => $transactionId,
                ];
            }

            Log::info('Querying NMI for original transaction ID', [
                'transaction_id' => $transactionId,
                'query_type' => $type
            ]);

            $response = Http::timeout(30)
                ->asForm()
                ->post('https://secure.networkmerchants.com/api/query.php', $queryData);

            if (!$response->successful()) {
                Log::error('NMI query API request failed', [
                    'status' => $response->status(),
                    'transaction_id' => $transactionId,
                    'query_type' => $type
                ]);
                return null;
            }

            // Parse XML response
            $xmlContent = $response->body();

            try {
                $xml = simplexml_load_string($xmlContent);
                if ($xml === false) {
                    throw new Exception('Failed to parse XML response');
                }

                // Extract original transaction ID based on response type
                if ($type === 'chargeback') {
                    $originalTransactionId = (string)($xml->chargeback->original_transaction_id ?? '');
                } else {
                    $originalTransactionId = (string)($xml->transaction->original_transaction_id ?? '');
                }

                if (!empty($originalTransactionId)) {
                    Log::info('Found original transaction ID from NMI query', [
                        'transaction_id' => $transactionId,
                        'query_type' => $type,
                        'original_transaction_id' => $originalTransactionId
                    ]);
                    return $originalTransactionId;
                }

                Log::warning('Original transaction ID not found in NMI response', [
                    'transaction_id' => $transactionId,
                    'query_type' => $type,
                    'xml_response' => $xmlContent
                ]);

            } catch (Exception $e) {
                Log::error('Failed to parse NMI query XML response', [
                    'error' => $e->getMessage(),
                    'transaction_id' => $transactionId,
                    'query_type' => $type,
                    'xml_response' => $xmlContent
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to query NMI for original transaction ID', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'query_type' => $type
            ]);
        }

        return null;
    }

    /**
     * Determine refund status based on original payment and refund amount
     */
    protected function determineRefundStatus(Payment $originalPayment, float $refundAmount): PaymentStatusEnum
    {
        $originalAmount = floatval($originalPayment->amount);

        // Get total refunded amount for this payment (including current refund)
        $totalRefunded = $this->getTotalRefundedAmount($originalPayment, $refundAmount);

        Log::info('Determining refund status', [
            'original_payment_id' => $originalPayment->id,
            'original_amount' => $originalAmount,
            'current_refund_amount' => $refundAmount,
            'total_refunded_amount' => $totalRefunded
        ]);

        // Allow for small floating point differences (0.01)
        $tolerance = 0.01;

        if (abs($totalRefunded - $originalAmount) <= $tolerance) {
            // Full refund: total refunded equals original amount
            return PaymentStatusEnum::REFUND_FULL;
        } elseif ($totalRefunded < $originalAmount) {
            // Partial refund: total refunded is less than original amount
            return PaymentStatusEnum::REFUND_PARTIAL;
        } else {
            // Over-refund scenario (shouldn't happen normally, but handle gracefully)
            Log::warning('Refund amount exceeds original payment', [
                'original_payment_id' => $originalPayment->id,
                'original_amount' => $originalAmount,
                'total_refunded' => $totalRefunded,
                'current_refund' => $refundAmount
            ]);
            return PaymentStatusEnum::REFUND_FULL;
        }
    }

    /**
     * Get total refunded amount for a payment (including current refund)
     */
    protected function getTotalRefundedAmount(Payment $originalPayment, float $currentRefundAmount): float
    {
        // Get all existing refund payments for this invoice
        $existingRefunds = Payment::where('invoice_id', $originalPayment->invoice_id)
            ->where('type', 'debit')
            ->whereIn('status', [PaymentStatusEnum::REFUND_PARTIAL, PaymentStatusEnum::REFUND_FULL])
            ->sum('amount');

        return floatval($existingRefunds) + $currentRefundAmount;
    }

    /**
     * Handle refund webhook
     */
    protected function handleRefund(array $data): array
    {
        try {
            $refundTransactionId = $data['transaction_id'] ?? $data['transactionid'] ?? null;
            $refundAmount = abs(floatval($data['amount'] ?? $data['requested_amount'] ?? 0)); // Make amount positive

            if (!$refundTransactionId) {
                throw new Exception('Refund transaction ID not found in webhook data');
            }

            Log::info('Processing NMI refund webhook', [
                'refund_transaction_id' => $refundTransactionId,
                'refund_amount' => $refundAmount
            ]);

            // Query NMI to get original transaction ID
            $originalTransactionId = $this->getOriginalTransactionId($refundTransactionId,"refund");

            if (!$originalTransactionId) {
                Log::warning('Could not find original transaction ID for refund', [
                    'refund_transaction_id' => $refundTransactionId
                ]);

                return [
                    'success' => true,
                    'type' => 'refund_no_original_transaction',
                    'message' => 'Refund processed but original transaction ID not found'
                ];
            }

            // Find original payment by gateway_id
            $originalPayment = Payment::where('gateway_id', $originalTransactionId)->first();

            if ($originalPayment) {
                // Check if refund was successful based on webhook data
                $isRefundSuccessful = ($data['response'] ?? '') === '1' ||
                                    (isset($data['_original']['event_body']['action']['success']) &&
                                     $data['_original']['event_body']['action']['success'] === '1');

                $originalAmount = floatval($originalPayment->amount);

                // Determine refund status
                if (!$isRefundSuccessful) {
                    // Failed refund
                    $refundStatus = PaymentStatusEnum::FAILED;
                    $statusNote = 'Failed refund';
                } else {
                    // Successful refund - determine if partial or full
                    $refundStatus = $this->determineRefundStatus($originalPayment, $refundAmount);
                    $statusNote = $refundStatus === PaymentStatusEnum::REFUND_FULL ? 'Full refund' : 'Partial refund';
                }

                // Create refund payment record linked to the same invoice
                $refundPayment = Payment::create([
                    'invoice_id' => $originalPayment->invoice_id,
                    'payment_method_id' => $originalPayment->payment_method_id,
                    'gateway_id' => $refundTransactionId,
                    'note' => "NMI refund for transaction {$originalTransactionId} - {$statusNote}",
                    'amount' => $refundAmount,
                    'usd_amount' => $refundAmount,
                    'type' => 'debit', // Refunds are debit type
                    'status' => $refundStatus,
                    'response' => array_merge($data, [
                        'original_transaction_id' => $originalTransactionId,
                        'original_amount' => $originalAmount,
                        'refund_successful' => $isRefundSuccessful,
                        'refund_type' => $refundStatus === PaymentStatusEnum::REFUND_FULL ? 'full' :
                                       ($refundStatus === PaymentStatusEnum::REFUND_PARTIAL ? 'partial' : 'failed')
                    ]),
                    'client_ip' => $data['ip_address'] ?? $data['ip'] ?? null
                ]);

                $logLevel = $isRefundSuccessful ? 'info' : 'warning';
                $logMessage = $isRefundSuccessful ? 'NMI refund processed successfully' : 'NMI refund failed';

                Log::{$logLevel}($logMessage, [
                    'original_transaction_id' => $originalTransactionId,
                    'refund_transaction_id' => $refundTransactionId,
                    'refund_payment_id' => $refundPayment->id,
                    'invoice_id' => $originalPayment->invoice_id,
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount,
                    'refund_status' => $refundStatus->value,
                    'refund_successful' => $isRefundSuccessful,
                    'refund_type' => $refundStatus === PaymentStatusEnum::REFUND_FULL ? 'full' :
                                   ($refundStatus === PaymentStatusEnum::REFUND_PARTIAL ? 'partial' : 'failed'),
                    'response_text' => $data['responsetext'] ?? 'Unknown'
                ]);

                return [
                    'success' => true,
                    'type' => 'refund',
                    'refund_successful' => $isRefundSuccessful,
                    'refund_status' => $refundStatus->value,
                    'refund_type' => $refundStatus === PaymentStatusEnum::REFUND_FULL ? 'full' :
                                   ($refundStatus === PaymentStatusEnum::REFUND_PARTIAL ? 'partial' : 'failed'),
                    'refund_payment_id' => $refundPayment->id,
                    'original_payment_id' => $originalPayment->id,
                    'invoice_id' => $originalPayment->invoice_id,
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount
                ];
            } else {
                Log::warning('NMI refund for unknown original transaction', [
                    'original_transaction_id' => $originalTransactionId,
                    'refund_transaction_id' => $refundTransactionId,
                    'refund_amount' => $refundAmount
                ]);

                return [
                    'success' => true,
                    'type' => 'refund_unknown_original_payment',
                    'message' => 'Refund processed but original payment not found in database'
                ];
            }

        } catch (Exception $e) {
            Log::error('Failed to handle NMI refund', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle chargeback batch webhook
     */
    protected function handleChargebackBatch(array $data): array
    {
        try {
            $chargebacks = $data['chargebacks'] ?? [];
            $totalChargebackAmount = floatval($data['chargeback_amount'] ?? 0);
            $chargebackCount = intval($data['chargeback_count'] ?? 0);

            $processedChargebacks = [];
            $failedChargebacks = [];
            $totalProcessedAmount = 0;

            Log::info('Processing NMI chargeback batch', [
                'chargeback_count' => $chargebackCount,
                'total_amount' => $totalChargebackAmount,
                'merchant' => $data['merchant'] ?? null
            ]);

            foreach ($chargebacks as $chargeback) {
                $chargebackResult = $this->processSingleChargeback($chargeback, $data);

                if ($chargebackResult['success']) {
                    $processedChargebacks[] = $chargebackResult;
                    $totalProcessedAmount += floatval($chargeback['amount'] ?? 0);
                } else {
                    $failedChargebacks[] = $chargebackResult;
                }
            }

            Log::info('NMI chargeback batch processing completed', [
                'total_chargebacks' => $chargebackCount,
                'processed_successfully' => count($processedChargebacks),
                'failed_processing' => count($failedChargebacks),
                'total_processed_amount' => $totalProcessedAmount
            ]);

            return [
                'success' => true,
                'type' => 'chargeback_batch',
                'chargeback_count' => $chargebackCount,
                'processed_count' => count($processedChargebacks),
                'failed_count' => count($failedChargebacks),
                'total_amount' => $totalChargebackAmount,
                'processed_amount' => $totalProcessedAmount,
                'processed_chargebacks' => $processedChargebacks,
                'failed_chargebacks' => $failedChargebacks
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle NMI chargeback batch', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a single chargeback from the batch
     */
    protected function processSingleChargeback(array $chargeback, array $batchData): array
    {
        try {
            $chargebackId = $chargeback['id'] ?? null;
            $chargebackAmount = floatval($chargeback['amount'] ?? 0);
            $ccNumber = $chargeback['cc_number'] ?? null;
            $customerName = $chargeback['customer_name'] ?? null;
            $reason = $chargeback['reason'] ?? null;
            $date = $chargeback['date'] ?? null;

            Log::info('Processing individual chargeback', [
                'chargeback_id' => $chargebackId,
                'amount' => $chargebackAmount,
                'customer_name' => $customerName,
                'cc_number' => $ccNumber
            ]);

            // Find original payment using NMI API
            $originalPayment = $this->findOriginalPaymentForChargeback($chargeback);

            if ($originalPayment) {
                // Get the original transaction ID for logging and response
                $originalTransactionId = $originalPayment->gateway_id;

                // Create chargeback payment record
                $chargebackPayment = Payment::create([
                    'invoice_id' => $originalPayment->invoice_id,
                    'payment_method_id' => $originalPayment->payment_method_id,
                    'gateway_id' => $chargebackId,
                    'note' => "NMI chargeback for transaction {$originalTransactionId} - ID: {$chargebackId}, Reason: {$reason}",
                    'amount' => $chargebackAmount,
                    'usd_amount' => $chargebackAmount,
                    'type' => 'debit', // Chargebacks are debit type
                    'status' => PaymentStatusEnum::CHARGEBACK,
                    'response' => array_merge($batchData, [
                        'chargeback_details' => $chargeback,
                        'original_payment_id' => $originalPayment->id,
                        'original_transaction_id' => $originalTransactionId
                    ]),
                    'client_ip' => null
                ]);

                Log::warning('NMI chargeback processed successfully', [
                    'chargeback_id' => $chargebackId,
                    'original_transaction_id' => $originalTransactionId,
                    'original_payment_id' => $originalPayment->id,
                    'chargeback_payment_id' => $chargebackPayment->id,
                    'invoice_id' => $originalPayment->invoice_id,
                    'amount' => $chargebackAmount,
                    'customer_name' => $customerName,
                    'reason' => $reason
                ]);

                return [
                    'success' => true,
                    'chargeback_id' => $chargebackId,
                    'chargeback_payment_id' => $chargebackPayment->id,
                    'original_payment_id' => $originalPayment->id,
                    'original_transaction_id' => $originalTransactionId,
                    'invoice_id' => $originalPayment->invoice_id,
                    'amount' => $chargebackAmount
                ];
            } else {
                Log::warning('NMI chargeback for unknown payment', [
                    'chargeback_id' => $chargebackId,
                    'amount' => $chargebackAmount,
                    'customer_name' => $customerName,
                    'cc_number' => $ccNumber
                ]);

                return [
                    'success' => false,
                    'chargeback_id' => $chargebackId,
                    'error' => 'Original payment not found',
                    'amount' => $chargebackAmount
                ];
            }

        } catch (Exception $e) {
            Log::error('Failed to process individual chargeback', [
                'chargeback' => $chargeback,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'chargeback_id' => $chargeback['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Find original payment for chargeback using NMI API
     */
    protected function findOriginalPaymentForChargeback(array $chargeback): ?Payment
    {
        $chargebackId = $chargeback['id'] ?? null;

        if (!$chargebackId) {
            Log::warning('No chargeback ID provided for original payment lookup');
            return null;
        }

        Log::info('Querying NMI for chargeback original transaction ID', [
            'chargeback_id' => $chargebackId
        ]);

        // Reuse the existing getOriginalTransactionId method
        // Pass 'chargeback' type to use the correct NMI API parameters
        $originalTransactionId = $this->getOriginalTransactionId($chargebackId, 'chargeback');

        if (!$originalTransactionId) {
            Log::warning('Could not find original transaction ID for chargeback', [
                'chargeback_id' => $chargebackId
            ]);
            return null;
        }

        // Find original payment by gateway_id
        $originalPayment = Payment::where('gateway_id', $originalTransactionId)->first();

        if ($originalPayment) {
            Log::info('Found original payment for chargeback', [
                'chargeback_id' => $chargebackId,
                'original_transaction_id' => $originalTransactionId,
                'original_payment_id' => $originalPayment->id
            ]);
        } else {
            Log::warning('Original payment not found in database for chargeback', [
                'chargeback_id' => $chargebackId,
                'original_transaction_id' => $originalTransactionId
            ]);
        }

        return $originalPayment;
    }

    /**
     * Handle chargeback webhook
     */
    protected function handleChargeback(array $data): array
    {
        try {
            // Check if this is a chargeback batch format
            if (isset($data['chargeback_batch']) && $data['chargeback_batch']) {
                return $this->handleChargebackBatch($data);
            }

            // Legacy single chargeback format
            $originalTransactionId = $data['original_transactionid'] ?? $data['transactionid'] ?? null;
            $chargebackAmount = floatval($data['amount'] ?? 0);

            // Find original payment
            $originalPayment = Payment::where('gateway_id', $originalTransactionId)->first();

            if ($originalPayment) {
                // Create chargeback payment record
                $chargebackPayment = Payment::create([
                    'invoice_id' => $originalPayment->invoice_id,
                    'payment_method_id' => $originalPayment->payment_method_id,
                    'gateway_id' => $data['transactionid'] ?? null,
                    'note' => 'NMI chargeback',
                    'amount' => $chargebackAmount,
                    'usd_amount' => $chargebackAmount,
                    'type' => 'debit', // Chargebacks are debit type
                    'status' => PaymentStatusEnum::CHARGEBACK,
                    'response' => $data,
                    'client_ip' => $data['ip'] ?? null
                ]);

                Log::warning('NMI chargeback processed', [
                    'original_transaction_id' => $originalTransactionId,
                    'chargeback_transaction_id' => $data['transactionid'] ?? 'unknown',
                    'chargeback_payment_id' => $chargebackPayment->id,
                    'amount' => $chargebackAmount
                ]);

                return [
                    'success' => true,
                    'type' => 'chargeback',
                    'chargeback_payment_id' => $chargebackPayment->id
                ];
            } else {
                Log::warning('NMI chargeback for unknown transaction', [
                    'original_transaction_id' => $originalTransactionId,
                    'chargeback_amount' => $chargebackAmount
                ]);

                return [
                    'success' => true,
                    'type' => 'chargeback_unknown_transaction',
                    'message' => 'Chargeback processed but original transaction not found'
                ];
            }

        } catch (Exception $e) {
            Log::error('Failed to handle NMI chargeback', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle unknown webhook type
     */
    protected function handleUnknownWebhook(array $data): array
    {
        Log::info('NMI unknown webhook type received', $data);

        return [
            'success' => true,
            'type' => 'unknown',
            'message' => 'Unknown webhook type received and logged'
        ];
    }

    /**
     * Create invoice for subscription payment
     */
    protected function createInvoiceForSubscription(Subscription $subscription, array $data): Invoice
    {
        $amount = floatval($data['amount'] ?? 0);
        $transactionId = $data['transactionid'] ?? null;

        // Get account and company from subscription
        $account = Account::where('payment_method_id', $subscription->paymentMethod->id)->first();
        $company = Company::first();

        if (!$account) {
            throw new Exception('No account found for payment method');
        }

        if (!$company) {
            throw new Exception('No company found');
        }

        $invoice = Invoice::create([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'model_id' => $subscription->id,
            'model_type' => Subscription::class,
            'amount' => $amount,
            'usd_amount' => $amount,
            'currency_id' => 1, // Assuming USD
            'status' => InvoiceStatusEnum::PAID,
            'note' => 'NMI subscription payment - Transaction: ' . $transactionId,
            'expires_at' => now()->addDays(30),
        ]);

        return $invoice;
    }

    /**
     * Create standalone invoice (not linked to subscription)
     */
    protected function createStandaloneInvoice(array $data, ?InvoiceStatusEnum $status = null): Invoice
    {
        $amount = floatval($data['amount'] ?? 0);
        $transactionId = $data['transactionid'] ?? null;

        // Use the account from this service instance
        $company = Company::first();

        if (!$company) {
            throw new Exception('No company found');
        }

        $invoice = Invoice::create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'model_id' => $this->account->id, // Link standalone payments to the account
            'model_type' => Account::class,
            'amount' => $amount,
            'usd_amount' => $amount,
            'currency_id' => 1, // Assuming USD
            'status' => $status ?? ($data['response'] == '1' ? InvoiceStatusEnum::PAID : InvoiceStatusEnum::UNPAID),
            'note' => 'NMI webhook payment - Transaction: ' . $transactionId,
            'expires_at' => now()->addDays(30),
        ]);

        return $invoice;
    }

    /**
     * Create payment record for invoice
     */
    protected function createPaymentRecord(Invoice $invoice, array $data, PaymentStatusEnum $status): Payment
    {
        $amount = floatval($data['amount'] ?? 0);
        $transactionId = $data['transactionid'] ?? null;

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method_id' => $this->account->payment_method_id,
            'gateway_id' => $transactionId,
            'note' => 'NMI webhook payment',
            'amount' => $amount,
            'usd_amount' => $amount,
            'type' => 'credit',
            'status' => $status,
            'response' => $data,
            'client_ip' => $data['ip'] ?? null,
            'cc_type' => $data['cc_type'] ?? null
        ]);

        return $payment;
    }
}
