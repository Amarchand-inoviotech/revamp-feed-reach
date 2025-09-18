<?php

namespace App\Repositories\Contracts;

interface SubscriptionRepositoryContract extends BaseRepositoryContract
{
    /**
     * Get subscriptions by conditions with relations
     */
    public function getByConditions(array $conditions, array $relations = []): array;

    /**
     * Get user's active subscriptions
     */
    public function getUserActiveSubscriptions(int $userId): array;

    /**
     * Get subscription by gateway subscription ID
     */
    public function getByGatewaySubscriptionId(string $gatewaySubscriptionId);

    /**
     * Get expiring subscriptions
     */
    public function getExpiringSubscriptions(int $days = 7): array;

    /**
     * Update subscription status
     */
    public function updateStatus(int $subscriptionId, string $status): bool;

    /**
     * Create a subscription record (data only, no gateway integration)
     */
    public function createSubscriptionRecord(array $subscriptionData): \App\Models\Subscription;

    /**
     * Update subscription record (data only, no gateway integration)
     */
    public function updateSubscriptionRecord(\App\Models\Subscription $subscription, array $data): \App\Models\Subscription;

    /**
     * Check if user has active subscription for a package
     */
    public function hasActiveSubscription(int $userId, int $packageId): bool;
}
