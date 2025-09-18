<?php

namespace App\Services;

use App\Models\GatewayPackage;
use App\Models\User;
use App\Models\Card;
use App\Models\Package;
use App\Models\PaymentMethod;

abstract class PaymentGatewayService
{
    protected string $gatewayName;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get gateway name
     */
    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }

    /**
     * Create a subscription plan on the gateway
     */
    abstract public function createSubscriptionPlan(GatewayPackage $gatewayPackage): array;

    /**
     * Disable a subscription plan on the gateway
     */
    abstract public function disableSubscriptionPlan(GatewayPackage $gatewayPackage): array;

    /**
     * Create a customer on the gateway
     */
    abstract public function createCustomer(User $user, array $billingInfo = []): array;

    /**
     * Create a subscription for a customer
     */
    abstract public function createSubscription(
        string $customerId,
        string $planId,
        ?Card $card = null,
        array $options = []
    ): array;

    /**
     * Cancel a subscription
     */
    abstract public function cancelSubscription(string $subscriptionId): array;

    /**
     * Update a subscription
     */
    abstract public function updateSubscription(GatewayPackage $gatewayPackage);

    /**
     * Update subscription payment method
     */
    abstract public function updateSubscriptionPaymentMethod(string $subscriptionId, string $newCardToken): array;

    /**
     * Get subscription details from gateway
     */
    abstract public function getSubscription(string $subscriptionId): array;

    /**
     * Process a one-time payment
     */
    abstract public function processPayment(
        float $amount,
        Card $card,
        array $billingInfo = [],
        array $options = []
    ): array;

    /**
     * Create a payment method/card token
     */
    abstract public function createPaymentMethod(array $cardData, User $user, array $billingInfo = []): array;

    /**
     * Delete a payment method
     */
    abstract public function deletePaymentMethod(string $paymentMethodId): array;

    /**
     * Validate webhook signature
     */
    abstract public function validateWebhook(string $payload, string $signature): bool;

    /**
     * Process webhook data
     */
    abstract public function processWebhook(array $webhookData): array;

    /**
     * Get supported billing cycles for this gateway
     */
    public function getSupportedBillingCycles(): array
    {
        return [
            'daily',
            'weekly',
            'monthly',
            'quarterly',
            'bi-quarterly',
            'yearly'
        ];
    }

    /**
     * Check if gateway is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Get gateway configuration
     */
    public function getConfig(?string $key = null)
    {
        if ($key) {
            return $this->config[$key] ?? null;
        }
        return $this->config;
    }

    /**
     * Set gateway configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Format amount for gateway (convert to cents if needed)
     */
    protected function formatAmount(float $amount): int
    {
        return (int) ($amount * 100);
    }

    /**
     * Format amount from gateway (convert from cents if needed)
     */
    protected function parseAmount(int $amount): float
    {
        return $amount / 100;
    }

    /**
     * Generate unique reference ID
     */
    protected function generateReference(string $prefix = ''): string
    {
        return $prefix . uniqid() . '_' . time();
    }

    /**
     * Log gateway activity
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        logger()->log($level, "[{$this->gatewayName}] {$message}", $context);
    }
}
