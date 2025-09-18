<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Repositories\Contracts\SubscriptionRepositoryContract;

class SubscriptionRepository extends BaseRepository implements SubscriptionRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = ['gatewayPackage.package', 'gatewayPackage.paymentMethod', 'user'];

    /**
     * Constructor with property promotion
     */
    public function __construct(Subscription $model)
    {
        parent::__construct($model, SubscriptionResource::class);
    }

    /**
     * Get search callback for Subscription model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function($query, $search) {
            $query->where('getway_subscription_id', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('gatewayPackage.package', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        };
    }

    /**
     * Get subscriptions by conditions with relations
     */
    public function getByConditions(array $conditions, array $relations = []): array
    {
        $query = $this->model->query();

        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        if (!empty($relations)) {
            $query->with($relations);
        } else {
            $query->with($this->defaultRelations);
        }

        $results = $query->get();

        return $this->resource ? $this->resource::collection($results)->toArray(request()) : $results->toArray();
    }

    /**
     * Get user's active subscriptions
     */
    public function getUserActiveSubscriptions(int $userId): array
    {
        $subscriptions = $this->model
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with($this->defaultRelations)
            ->get();

        return $this->resource ? $this->resource::collection($subscriptions)->toArray(request()) : $subscriptions->toArray();
    }

    /**
     * Get subscription by gateway subscription ID
     */
    public function getByGatewaySubscriptionId(string $gatewaySubscriptionId)
    {
        $subscription = $this->model
            ->where('getway_subscription_id', $gatewaySubscriptionId)
            ->with($this->defaultRelations)
            ->first();

        if (!$subscription) {
            return null;
        }

        return $this->resource ? $this->resource::make($subscription) : $subscription;
    }



    /**
     * Update subscription status
     */
    public function updateStatus(int $subscriptionId, string $status): bool
    {
        return $this->model
            ->where('id', $subscriptionId)
            ->update(['status' => $status]) > 0;
    }

    /**
     * Create a subscription record (data only, no gateway integration)
     */
    public function createSubscriptionRecord(array $subscriptionData): Subscription
    {
        return $this->model->create($subscriptionData);
    }

    /**
     * Update subscription record (data only, no gateway integration)
     */
    public function updateSubscriptionRecord(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);
        return $subscription->fresh($this->defaultRelations);
    }

    /**
     * Check if user has active subscription for a package
     */
    public function hasActiveSubscription(int $userId, int $packageId): bool
    {
        return $this->model->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('gatewayPackage', function ($query) use ($packageId) {
                $query->where('package_id', $packageId);
            })
            ->exists();
    }

    /**
     * Get expiring subscriptions
     */
    public function getExpiringSubscriptions(int $days = 7): array
    {
        $expirationDate = now()->addDays($days)->toDateString();

        $subscriptions = $this->model->where('status', 'active')
            ->where('next_billing_date', '<=', $expirationDate)
            ->with($this->defaultRelations)
            ->get();

        return $this->resource ? $this->resource::collection($subscriptions)->toArray(request()) : $subscriptions->toArray();
    }
}
