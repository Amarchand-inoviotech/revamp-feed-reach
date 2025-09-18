<?php

namespace App\Http\Controllers\Api\Frontoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMethodRequest;
use App\Http\Requests\SubscribeRequest;
use App\Models\Account;
use App\Models\GatewayPackage;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Services\NmiService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionRepositoryContract $repo,
        protected SubscriptionService $subscriptionService,
        protected string $model = 'Subscription'
    ) {}

    /**
     * Display a listing of subscriptions
     */
    public function index(IndexMethodRequest $request)
    {
        $validated = $request->validated();
        $params = RequestParams::fromValidatedRequest($validated);
        $response = $this->repo->getAll($params);

        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }

    /**
     * Store a newly created subscription
     */
    public function store(SubscribeRequest $request)
    {
        $payload = $request->validated();
        try {
            // Additional validation for authenticated user
            $user = Auth::user();
            if (!$user) {
                return errorResponse('User authentication required', 401);
            }

            // Check if user already has an active subscription for this package
            $gatewayPackage = \App\Models\GatewayPackage::whereHas('package', function ($query) use ($payload) {
                $query->where('id', $payload['package_id']);
            })->whereHas('paymentMethod', function ($query) {
                $query->where('name', 'nmi');
            })->first();

            if ($gatewayPackage) {
                $existingSubscription = Subscription::where('user_id', $user->id)
                    ->where('gateway_package_id', $gatewayPackage->id)
                    ->where('status', 'active')
                    ->first();

                if ($existingSubscription) {
                    return errorResponse('You already have an active subscription for this package', 422);
                }
            }

            $result = $this->subscriptionService->createSubscription($payload);

            if ($result['success']) {
                return successResponse($result['subscription']->toArray(request()), trans('subscription.created_successfully'));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified subscription
     */
    public function show(Subscription $subscription)
    {
        try {
            // Check if user owns this subscription
            $user = Auth::user();
            if ($subscription->user_id !== $user->id) {
                return errorResponse('Unauthorized to view this subscription', 403);
            }

            $subscription->load(['gatewayPackage.package', 'gatewayPackage.paymentMethod', 'user']);
            $response = \App\Http\Resources\SubscriptionResource::make($subscription);

            return successResponse($response->toArray(request()), trans('generic.show', ['model' => $this->model]));

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update subscription plan (change to a different package)
     */
    public function update(Request $request, Subscription $subscription)
    {
        $payload = $request->validate([
            'package_id' => ['required', 'string', 'exists:packages,uuid'],
            // Customer vault support - use existing customer and card
            'customer_id' => ['nullable', 'string', 'exists:customers,uuid'],
            'card_id' => ['nullable', 'string', 'exists:cards,uuid'],
            // Card details only required when not using existing customer/card
            'card' => ['required_without_all:card_id,customer_id', 'array'],
            'card.number' => ['required_with:card', 'string'],
            'card.exp_month' => ['required_with:card', 'string', 'size:2'],
            'card.exp_year' => ['required_with:card', 'string', 'size:4'],
            'card.cvv' => ['required_with:card', 'string', 'min:3', 'max:4'],
            // Billing info only required for new cards
            'billing_info' => ['nullable', 'array'],
            'billing_info.first_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.last_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.email' => ['nullable', 'email'],
        ]);


        try {
            $package = Package::where('uuid', $payload['package_id'])->firstOrFail();
            // Check if user owns this subscription
            $user = Auth::user();
            if ($subscription->user_id !== $user->id) {
                return errorResponse('Unauthorized to update this subscription', 403);
            }

            // Check if subscription is active
            if ($subscription->status !== 'active') {
                return errorResponse('Only active subscriptions can be updated', 422);
            }

            // Convert UUIDs to IDs for customer and card if provided
            if (isset($payload['customer_id'])) {
                $customer = \App\Models\Customer::where('uuid', $payload['customer_id'])
                    ->where('user_id', $user->id)
                    ->firstOrFail();
                $payload['customer_id'] = $customer->id;
            }

            if (isset($payload['card_id'])) {
                $card = \App\Models\Card::where('uuid', $payload['card_id'])
                    ->whereHasMorph('author', [\App\Models\User::class], function ($query) use ($user) {
                        $query->where('id', $user->id);
                    })
                    ->firstOrFail();
                $payload['card_id'] = $card->id;
            }

            // Try to update the subscription plan (preferred method)
            $result = $this->subscriptionService->updateSubscriptionPlan($subscription, $package, $payload);

            if ($result['success']) {
                $message = isset($result['method']) && $result['method'] === 'update'
                    ? trans('subscription.plan_updated')
                    : trans('subscription.plan_changed');

                return successResponse($result['subscription']->toArray(request()), $message);
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Cancel the specified subscription (destroy method for route)
     */
    public function destroy(Subscription $subscription)
    {
        try {
            // Check if user owns this subscription
            $user = Auth::user();
            if ($subscription->user_id !== $user->id) {
                return errorResponse('Unauthorized to cancel this subscription', 403);
            }

            $result = $this->subscriptionService->cancelSubscription($subscription);

            if ($result['success']) {
                return successResponse($result['subscription']->toArray(request()), trans('subscription.cancelled'));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Cancel the specified subscription (legacy method)
     */
    public function cancel(Subscription $subscription)
    {
        return $this->destroy($subscription);
    }

    /**
     * Update subscription card/payment method
     */
    public function updateCard(Request $request, Subscription $subscription)
    {
        $payload = $request->validate([
            // Use existing card from customer vault
            'card_id' => ['nullable', 'string', 'exists:cards,uuid'],
            // Or provide new card details
            'card' => ['required_without:card_id', 'array'],
            'card.number' => ['required_with:card', 'string'],
            'card.exp_month' => ['required_with:card', 'string', 'size:2'],
            'card.exp_year' => ['required_with:card', 'string', 'size:4'],
            'card.cvv' => ['required_with:card', 'string', 'min:3', 'max:4'],
            'delete_old_card' => ['sometimes', 'boolean'],
            // Billing info only required for new cards
            'billing_info' => ['nullable', 'array'],
            'billing_info.first_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.last_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.email' => ['nullable', 'email'],
        ]);

        try {
            // Check if user owns this subscription
            $user = Auth::user();
            if ($subscription->user_id !== $user->id) {
                return errorResponse('Unauthorized to update this subscription', 403);
            }

            // Check if subscription is active
            if ($subscription->status !== 'active') {
                return errorResponse('Can only update card for active subscriptions', 422);
            }

            // Convert card UUID to ID if provided
            if (isset($payload['card_id'])) {
                $card = \App\Models\Card::where('uuid', $payload['card_id'])
                    ->whereHasMorph('author', [\App\Models\User::class], function ($query) use ($user) {
                        $query->where('id', $user->id);
                    })
                    ->firstOrFail();
                $payload['card_id'] = $card->id;
            }

            $deleteOldCard = $payload['delete_old_card'] ?? false;
            $result = $this->subscriptionService->updateSubscriptionCard(
                $subscription,
                $payload['card'] ?? null,
                $deleteOldCard,
                $payload['card_id'] ?? null,
                $payload['billing_info'] ?? []
            );

            if ($result['success']) {
                $response = [
                    'subscription' => $result['subscription']->toArray(request()),
                    'new_card' => $result['new_card']->toArray(),
                    'old_card_deleted' => $result['old_card_deleted'] ?? false,
                    'message' => $result['message']
                ];

                return successResponse($response, 'Card updated successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            Log::error('Card update failed', [
                'subscription_id' => $subscription->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Sync subscription status with gateway
     */
    public function sync(Subscription $subscription)
    {
        try {
            // Check if user owns this subscription
            $user = Auth::user();
            if ($subscription->user_id !== $user->id) {
                return errorResponse('Unauthorized to sync this subscription', 403);
            }

            $result = $this->subscriptionService->syncSubscriptionStatus($subscription);

            if ($result['success']) {
                return successResponse($result['subscription']->toArray(request()), trans('subscription.synced'));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get current user's subscriptions
     */
    public function mySubscriptions(Request $request)
    {
        $payload = $request->validate([
            'status' => ['nullable', 'string', 'in:active,canceled,paused,expired'],
        ]);

        try {
            $user = Auth::user();

            $conditions = ['user_id' => $user->id];
            if (isset($payload['status'])) {
                $conditions['status'] = $payload['status'];
            }

            $subscriptions = $this->repo->getByConditions(
                $conditions,
                ['gatewayPackage.package', 'gatewayPackage.paymentMethod']
            );

            return successResponse($subscriptions, trans('subscription.user_subscriptions'));

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get user's subscriptions (admin method)
     */
    public function userSubscriptions(Request $request)
    {
        $payload = $request->validate([
            'user_id' => ['required', 'exists:users,uuid'],
            'status' => ['nullable', 'string', 'in:active,canceled,paused,expired'],
        ]);

        try {
            $user = User::where('uuid', $payload['user_id'])->firstOrFail();

            $conditions = ['user_id' => $user->id];
            if (isset($payload['status'])) {
                $conditions['status'] = $payload['status'];
            }

            $subscriptions = $this->repo->getByConditions(
                $conditions,
                ['gatewayPackage.package', 'gatewayPackage.paymentMethod']
            );

            return successResponse($subscriptions, trans('subscription.user_subscriptions'));

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get expiring subscriptions
     */
    public function expiring(Request $request)
    {
        $payload = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        try {
            $days = $payload['days'] ?? 7;
            $subscriptions = $this->repo->getExpiringSubscriptions($days);

            return successResponse($subscriptions, trans('subscription.expiring'));

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Process webhook from payment gateway
     */
    public function webhook(Request $request, string $gateway)
    {
        try {
            $webhookData = $request->all();

            Log::info('Webhook received', [
                'gateway' => $gateway,
                'data' => $webhookData,
                'headers' => $request->headers->all()
            ]);

            // Process webhook using SubscriptionService
            $result = $this->subscriptionService->processWebhook($gateway, $webhookData);

            if ($result['success']) {
                return successResponse([], 'Webhook processed successfully');
            }

            return errorResponse($result['error'] ?? 'Failed to process webhook', 422);

        } catch (Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return errorResponse($e->getMessage(), 500);
        }
    }

     /**
     * Create custom subscription with vault ID
     */
    public function createCustomSubscription(Request $request)
    {
        try {
            $payload = $request->validate([
                'plan_amount' => ['required', 'numeric', 'min:0.01'],
                'plan_payments' => ['nullable', 'integer', 'min:0'], // 0 = unlimited
                'day_frequency' => ['nullable', 'integer', 'min:1'],
                'month_frequency' => ['nullable', 'integer', 'min:1'],
                'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
                'start_date' => ['nullable', 'date', 'after_or_equal:today'],
                'order_id' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:255'],
                'billing_info' => ['required', 'array'],
                'billing_info.first_name' => ['required', 'string', 'max:100'],
                'billing_info.last_name' => ['required', 'string', 'max:100'],
                'billing_info.email' => ['required', 'email'],
                'billing_info.phone' => ['nullable', 'string', 'max:20'],
                'billing_info.address' => ['nullable', 'string', 'max:255'],
                'billing_info.city' => ['nullable', 'string', 'max:100'],
                'billing_info.state' => ['nullable', 'string', 'max:50'],
                'billing_info.zip' => ['nullable', 'string', 'max:20'],
                'billing_info.country' => ['nullable', 'string', 'max:50'],
                // Card info using same format as subscribe plan
                'card' => ['required', 'array'],
                'card.number' => ['required', 'string'],
                'card.exp_month' => ['required', 'string', 'size:2'],
                'card.exp_year' => ['required', 'string', 'size:4'],
                'card.cvv' => ['required', 'string', 'min:3', 'max:4'],
            ]);

            // Convert card format for NMI service
            $payload['card_info'] = [
                'card_number' => $payload['card']['number'],
                'exp_month' => $payload['card']['exp_month'],
                'exp_year' => $payload['card']['exp_year'],
                'cvv' => $payload['card']['cvv']
            ];

            // Use SubscriptionService for independent custom subscription creation
            $result = $this->subscriptionService->createCustomSubscription($payload);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription']->toArray(request()),
                    'customer' => $result['customer']?->toArray() ?? null,
                    'customer_vault_id' => $result['customer_vault_id'] ?? null,
                    'gateway_response' => $result['gateway_response'] ?? null
                ], 'Custom subscription created successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update subscription card
     */
    public function updateSubscriptionCard(Request $request, Subscription $subscription)
    {
        try {
            $payload = $request->validate([
                'card_info' => ['required', 'array'],
                'card_info.card_number' => ['required', 'string', 'size:16'],
                'card_info.exp_month' => ['required', 'string', 'size:2'],
                'card_info.exp_year' => ['required', 'string', 'size:4'],
                'card_info.cvv' => ['required', 'string', 'size:3'],
                 'billing_info' => ['required', 'array'],
                'billing_info.first_name' => ['required', 'string', 'max:100'],
                'billing_info.last_name' => ['required', 'string', 'max:100'],
                'billing_info.email' => ['required', 'email'],
                'billing_info.phone' => ['nullable', 'string', 'max:20'],
                'billing_info.address' => ['required', 'string', 'max:255'],
                'billing_info.city' => ['required', 'string', 'max:100'],
                'billing_info.state' => ['required', 'string', 'max:50'],
                'billing_info.zip' => ['required', 'string', 'max:20'],
                'billing_info.country' => ['nullable', 'string', 'max:50'],

            ]);

            // Convert card format for NMI service
            $payload['card_info'] = [
                'card_number' => $payload['card_info']['card_number'],
                'exp_month' => $payload['card_info']['exp_month'],
                'exp_year' => $payload['card_info']['exp_year'],
                'cvv' => $payload['card_info']['cvv']
            ];

            // Use SubscriptionService for independent custom subscription card update
            $result = $this->subscriptionService->updateCustomSubscriptionCard($subscription, $payload);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription']->toArray(request()),
                    'gateway_response' => $result['gateway_response'] ?? null
                ], 'Subscription card updated successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update subscription amount and frequency
     */
    public function updateSubscriptionAmount(Request $request, string $subscriptionUuid)
    {
        try {
            $payload = $request->validate([
                'plan_amount' => ['required', 'numeric', 'min:0.01'],
                'plan_payments' => ['nullable', 'integer', 'min:0'], // 0 = unlimited
                'day_frequency' => ['nullable', 'integer', 'min:1'],
                'month_frequency' => ['nullable', 'integer', 'min:1'],
                'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            ]);

            // Find subscription
            $subscription = Subscription::where('uuid', $subscriptionUuid)->firstOrFail();

            // Use SubscriptionService for independent custom subscription amount update
            $result = $this->subscriptionService->updateCustomSubscriptionAmount($subscription, $payload);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription']->toArray(request()),
                    'gateway_response' => $result['gateway_response'] ?? null
                ], 'Subscription amount updated successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCustomSubscription(Request $request, Subscription $subscription)
    {
            try {
                $payload = $request->validate([
                'plan_amount' => ['required', 'numeric', 'min:0.01'],
                'plan_payments' => ['nullable', 'integer', 'min:0'], // 0 = unlimited
                'day_frequency' => ['nullable', 'integer', 'min:1'],
                'month_frequency' => ['nullable', 'integer', 'min:1'],
                ]);
                $result = $this->subscriptionService->updateCustomSubscriptionAmount($subscription, $payload);
                if ($result['success']) {
                    return successResponse([
                        'subscription' => $result['subscription']->toArray(request()),
                        'gateway_response' => $result['gateway_response'] ?? null
                    ], 'Subscription updated successfully');
                }
                return errorResponse($result['error'], 400);
            }catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function manualCharge(Request $request, Subscription $subscription)
    {
        try {
            $payload = $request->validate([
                'amount' => ['required', 'numeric', 'min:0.01'],
                'customer_id' => ['nullable', 'exists:cards,uuid'],
                'description' => ['nullable', 'string', 'max:255'],
            ]);

            $result = $this->subscriptionService->manualCharge($subscription, $payload);
            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription']->toArray(request()),
                    'gateway_response' => $result['gateway_response'] ?? null
                ], 'Manual charge processed successfully');
            }
            return errorResponse($result['error'], 400);
        }catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
