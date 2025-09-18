<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\GatewayPackage;
use App\Models\User;
use App\Models\Card;
use App\Services\SubscriptionService;
use App\Services\NMIService;
use Illuminate\Http\Request;
use Exception;

class NmiDemoController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected NMIService $NMIService
    ) {}

    /**
     * Demo: Create a plan on NMI (Step 1)
     */
    public function createPlan(Request $request)
    {
        $payload = $request->validate([
            'package_id' => ['required', 'exists:packages,uuid'],
            'payment_method_name' => ['required', 'string', 'in:nmi'],
        ]);

        try {
            // Get package and payment method
            $package = Package::where('uuid', $payload['package_id'])->firstOrFail();
            $paymentMethod = PaymentMethod::where('name', $payload['payment_method_name'])->firstOrFail();

            // Check if gateway package already exists
            $existingGatewayPackage = GatewayPackage::where('package_id', $package->id)
                ->where('payment_method_id', $paymentMethod->id)
                ->first();

            if ($existingGatewayPackage) {
                return errorResponse('Plan already exists for this package and payment method', 422);
            }

            // Create gateway package record
            $gatewayPackage = GatewayPackage::create([
                'package_id' => $package->id,
                'payment_method_id' => $paymentMethod->id,
                'gateway_id' => null, // Will be set by NMI service
            ]);

            // Create plan on NMI
            $result = $this->NMIService->createSubscriptionPlan($gatewayPackage);

            if ($result['success']) {
                return successResponse([
                    'gateway_package' => $gatewayPackage->fresh(),
                    'plan_id' => $result['plan_id'],
                    'nmi_response' => $result
                ], 'Plan created successfully on NMI');
            }

            // Delete the gateway package if plan creation failed
            $gatewayPackage->delete();
            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Demo: Create a subscription (Step 2)
     * This handles: 1) Create customer if not exists, 2) Subscribe to plan
     */
    public function createSubscription(Request $request)
    {
        $payload = $request->validate([
            'user_id' => ['required', 'exists:users,uuid'],
            'gateway_package_id' => ['required', 'exists:gateway_packages,uuid'],
            'card_number' => ['required', 'string', 'size:16'],
            'exp_month' => ['required', 'string', 'size:2'],
            'exp_year' => ['required', 'string', 'size:4'],
            'cvv' => ['required', 'string', 'size:3'],
            'billing_info' => ['required', 'array'],
            'billing_info.first_name' => ['required', 'string', 'max:100'],
            'billing_info.last_name' => ['required', 'string', 'max:100'],
            'billing_info.email' => ['required', 'email'],
            'billing_info.phone' => ['nullable', 'string', 'max:20'],
            'billing_info.address' => ['required', 'string', 'max:255'],
            'billing_info.city' => ['required', 'string', 'max:100'],
            'billing_info.state' => ['required', 'string', 'max:100'],
            'billing_info.zip' => ['required', 'string', 'max:20'],
            'billing_info.country' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            // Get related models
            $user = User::where('uuid', $payload['user_id'])->firstOrFail();
            $gatewayPackage = GatewayPackage::where('uuid', $payload['gateway_package_id'])->firstOrFail();

            // Create payment method token on NMI
            $cardData = [
                'number' => $payload['card_number'],
                'exp_month' => $payload['exp_month'],
                'exp_year' => $payload['exp_year'],
                'cvv' => $payload['cvv'],
            ];

            $paymentMethodResult = $this->NMIService->createPaymentMethod($cardData, $user);

            if (!$paymentMethodResult['success']) {
                return errorResponse('Failed to create payment method: ' . $paymentMethodResult['error'], 422);
            }

            // Create card record
            $card = Card::create([
                'payment_method_id' => $gatewayPackage->payment_method_id,
                'token' => $paymentMethodResult['token'],
                'name' => $payload['billing_info']['first_name'] . ' ' . $payload['billing_info']['last_name'],
                'last4' => $paymentMethodResult['last4'],
                'expiry' => $payload['exp_year'] . '-' . $payload['exp_month'] . '-01',
                'author_id' => $user->id,
                'author_type' => $user->getMorphClass(),
            ]);

            // Create subscription
            $options = [
                'start_date' => now()->toDateString(),
                'billing_info' => $payload['billing_info']
            ];

            $result = $this->subscriptionService->createSubscription($user, $gatewayPackage, $card, $options);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription'],
                    'card' => $card,
                    'gateway_response' => $result['gateway_response']
                ], 'Subscription created successfully');
            }

            // Clean up card if subscription creation failed
            $card->delete();
            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Demo: Cancel a subscription
     */
    public function cancelSubscription(Request $request)
    {
        $payload = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,uuid'],
        ]);

        try {
            $subscription = \App\Models\Subscription::where('uuid', $payload['subscription_id'])->firstOrFail();

            $result = $this->subscriptionService->cancelSubscription($subscription);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription'],
                    'gateway_response' => $result['gateway_response']
                ], 'Subscription cancelled successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Demo: Get subscription status from NMI
     */
    public function getSubscriptionStatus(Request $request)
    {
        $payload = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,uuid'],
        ]);

        try {
            $subscription = \App\Models\Subscription::where('uuid', $payload['subscription_id'])->firstOrFail();

            $result = $this->subscriptionService->syncSubscriptionStatus($subscription);

            if ($result['success']) {
                return successResponse([
                    'subscription' => $result['subscription'],
                    'gateway_data' => $result['gateway_data']
                ], 'Subscription status synced successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Demo: Process a one-time payment
     */
    public function processPayment(Request $request)
    {
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'card_id' => ['required', 'exists:cards,uuid'],
            'billing_info' => ['required', 'array'],
        ]);

        try {
            $card = Card::where('uuid', $payload['card_id'])->firstOrFail();

            $result = $this->NMIService->processPayment(
                $payload['amount'],
                $card,
                $payload['billing_info'],
                ['order_id' => 'demo_' . uniqid()]
            );

            if ($result['success']) {
                return successResponse([
                    'transaction_id' => $result['transaction_id'],
                    'gateway_response' => $result['gateway_response']
                ], 'Payment processed successfully');
            }

            return errorResponse($result['error'], 422);

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Demo: Get available gateways
     */
    public function getAvailableGateways()
    {
        try {
            $gateways = $this->subscriptionService->getAvailableGateways();

            $gatewayInfo = [];
            foreach ($gateways as $gatewayName) {
                $gateway = $this->subscriptionService->getGateway($gatewayName);
                $gatewayInfo[$gatewayName] = [
                    'name' => $gatewayName,
                    'enabled' => $gateway->isEnabled(),
                    'supported_billing_cycles' => $gateway->getSupportedBillingCycles(),
                ];
            }

            return successResponse($gatewayInfo, 'Available gateways retrieved successfully');

        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
