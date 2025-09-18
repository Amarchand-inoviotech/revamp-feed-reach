<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Subscription;
use App\Models\GatewayPackage;
use App\Models\User;
use App\Models\Card;
use App\Repositories\Contracts\SubscriptionRepositoryContract;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Exception;

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
    public function store(Request $request)
    {
        $payload = $request->validate([
            'user_id' => ['required', 'exists:users,uuid'],
            'gateway_package_id' => ['required', 'exists:gateway_packages,uuid'],
            'card_id' => ['nullable', 'exists:cards,uuid'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'billing_info' => ['nullable', 'array'],
            'billing_info.first_name' => ['nullable', 'string', 'max:100'],
            'billing_info.last_name' => ['nullable', 'string', 'max:100'],
            'billing_info.email' => ['nullable', 'email'],
            'billing_info.phone' => ['nullable', 'string', 'max:20'],
            'billing_info.address' => ['nullable', 'string', 'max:255'],
            'billing_info.city' => ['nullable', 'string', 'max:100'],
            'billing_info.state' => ['nullable', 'string', 'max:100'],
            'billing_info.zip' => ['nullable', 'string', 'max:20'],
            'billing_info.country' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            // Get related models
            $user = User::where('uuid', $payload['user_id'])->firstOrFail();
            $gatewayPackage = GatewayPackage::where('uuid', $payload['gateway_package_id'])->firstOrFail();
            $card = isset($payload['card_id']) ? Card::where('uuid', $payload['card_id'])->first() : null;

            // Prepare options
            $options = [
                'start_date' => $payload['start_date'] ?? now()->toDateString(),
                'billing_info' => $payload['billing_info'] ?? []
            ];

            // Create subscription
            $result = $this->subscriptionService->createSubscription($user, $gatewayPackage, $card, $options);

            if ($result['success']) {
                return successResponse($result['subscription'], trans('generic.store', ['model' => $this->model]));
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
        $subscription->load(['gatewayPackage.package', 'gatewayPackage.paymentMethod', 'user']);
        $response = \App\Http\Resources\SubscriptionResource::make($subscription);

        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified subscription
     */
    public function update(Request $request, Subscription $subscription)
    {
        $payload = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,canceled,paused,expired'],
        ]);

        try {
            $result = $this->subscriptionService->updateSubscription($subscription, $payload);

            if ($result['success']) {
                return successResponse($result['subscription'], trans('generic.update', ['model' => $this->model]));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Cancel the specified subscription
     */
    public function cancel(Subscription $subscription)
    {
        try {
            $result = $this->subscriptionService->cancelSubscription($subscription);

            if ($result['success']) {
                return successResponse($result['subscription'], trans('subscription.cancelled'));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Sync subscription status with gateway
     */
    public function sync(Subscription $subscription)
    {
        try {
            $result = $this->subscriptionService->syncSubscriptionStatus($subscription);

            if ($result['success']) {
                return successResponse($result['subscription'], trans('subscription.synced'));
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get user's subscriptions
     */
    public function userSubscriptions(Request $request)
    {
        $payload = $request->validate([
            'user_id' => ['required', 'exists:users,uuid'],
            'status' => ['nullable', 'string', 'in:active,canceled,paused,expired'],
        ]);

        try {
            $user = User::where('uuid', $payload['user_id'])->firstOrFail();

            if (isset($payload['status'])) {
                $subscriptions = $this->repo->getByConditions([
                    'user_id' => $user->id,
                    'status' => $payload['status']
                ]);
            } else {
                $subscriptions = $this->repo->getUserActiveSubscriptions($user->id);
            }

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
            $signature = $request->header('X-Signature') ?? $request->header('Signature');

            // Validate webhook if signature is provided
            if ($signature) {
                $gatewayService = $this->subscriptionService->getGateway($gateway);
                if ($gatewayService && !$gatewayService->validateWebhook($request->getContent(), $signature)) {
                    return errorResponse('Invalid webhook signature', 401);
                }
            }

            $result = $this->subscriptionService->processWebhook($gateway, $webhookData);

            if ($result['success']) {
                return successResponse([], $result['message'] ?? 'Webhook processed successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
