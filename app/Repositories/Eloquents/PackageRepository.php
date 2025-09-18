<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\PackageResource;
use App\Models\AttributeType;
use App\Models\GatewayPackage;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Repositories\Contracts\PackageRepositoryContract;
use App\Services\NmiService;

class PackageRepository extends BaseRepository implements PackageRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(Package $model)
    {
        parent::__construct($model, PackageResource::class);
    }

    public function storeModel(array $payload, bool $isResource = true)
    {
        $model = $this->model;
        $model->toFill($payload);
        $model->save();

        // Register with NMI gateway if enabled
        if (config('services.nmi.enabled')) {
            $paymentMethod = PaymentMethod::where('name', 'nmi')->first();

            if ($paymentMethod) {
                // Create gateway package record
                $gatewayPackage = GatewayPackage::create([
                    'package_id' => $model->id,
                    'payment_method_id' => $paymentMethod->id
                ]);

                $gatewayPackage->load('package');

                // Create subscription plan on NMI
                $nmiService = app(NmiService::class);
                $result = $nmiService->createSubscriptionPlan($gatewayPackage);

                if (!$result['success']) {
                    // Delete the gateway package if plan creation failed
                    $gatewayPackage->delete();
                    throw new \Exception('Failed to create plan on NMI: ' . $result['error']);
                }

                return $gatewayPackage;
            }
        }

        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    public function updateModel($model, array $payload, bool $isResource = true, bool $forceRecreate = false)
    {
        $model->toFill($payload);
        $model->save();

        // Update NMI plan if enabled and gateway package exists
        if (config('services.nmi.enabled')) {
            $paymentMethod = PaymentMethod::where('name', 'nmi')->first();

            if ($paymentMethod) {
                $gatewayPackage = GatewayPackage::where('package_id', $model->id)
                    ->where('payment_method_id', $paymentMethod->id)
                    ->first();

                $nmiService = app(NmiService::class);

                if (is_null($gatewayPackage?->gateway_id)) {
                    // Create gateway package record if it doesn't exist
                    $gatewayPackage = $gatewayPackage ?? GatewayPackage::create([
                        'package_id' => $model->id,
                        'payment_method_id' => $paymentMethod->id
                    ]);

                    // Create subscription plan on NMI
                    $result = $nmiService->createSubscriptionPlan($gatewayPackage);

                    if (!$result['success']) {
                        throw new \Exception('Failed to create plan on NMI: ' . $result['error']);
                    }
                } else {
                    // Update existing plan by recreating it
                    // $result = $nmiService->recreateSubscriptionPlan($gatewayPackage);

                    // if (!$result['success']) {
                    //     throw new \Exception('Failed to update plan on NMI: ' . $result['error']);
                    // }
                }
            }
        }

        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Soft Delete Model with NMI plan disable
     */
    public function softDeleteModel($model, bool $isResource = true)
    {
        // Disable NMI plan instead of deleting
        if (config('services.nmi.enabled')) {
            $paymentMethod = PaymentMethod::where('name', 'nmi')->first();

            if ($paymentMethod) {
                $gatewayPackage = GatewayPackage::where('package_id', $model->id)
                    ->where('payment_method_id', $paymentMethod->id)
                    ->first();

                if ($gatewayPackage) {
                    $gatewayPackage->load('package');

                    // Disable subscription plan on NMI
                    $nmiService = app(NmiService::class);
                    $result = $nmiService->disableSubscriptionPlan($gatewayPackage);

                    if (!$result['success']) {
                        throw new \Exception('Failed to disable plan on NMI: ' . $result['error']);
                    }
                }
            }
        }

        // Perform soft delete
        $model->delete();
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Restore Model with NMI plan re-enable
     */
    public function restoreModel($model, bool $isResource = true)
    {
        // Restore the model first
        $model->restore();

        // Re-create NMI plan if enabled
        if (config('services.nmi.enabled')) {
            $paymentMethod = PaymentMethod::where('name', 'nmi')->first();

            if ($paymentMethod) {
                $gatewayPackage = GatewayPackage::where('package_id', $model->id)
                    ->where('payment_method_id', $paymentMethod->id)
                    ->first();

                if ($gatewayPackage) {
                    $gatewayPackage->load('package');

                    // Re-create subscription plan on NMI
                    $nmiService = app(NmiService::class);
                    $result = $nmiService->createSubscriptionPlan($gatewayPackage);

                    if (!$result['success']) {
                        throw new \Exception('Failed to restore plan on NMI: ' . $result['error']);
                    }
                }
            }
        }

        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    public function getAllWithAttributes()
    {
        // Load attribute types with their attributes
        $attributeTypes = AttributeType::with('attributes')->get();

        // Load packages with their associated attributes using the proper many-to-many relationship
        $packages = $this->model->with('attributes')->get();

        // Transform packages to include attribute structure
        $packagesWithAttributes = $packages->map(function ($package) use ($attributeTypes) {
            // Get the attribute IDs for this package
            $packageAttributeIds = $package->attributes->pluck('id')->toArray();

            return [
                'id' => $package->uuid,
                'name' => $package->name,
                'price' => $package->price,
                'frequency' => $package->frequency,
                'is_agent' => $package->is_agent,
                'created_at' => $package->created_at?->format(DATE_FORMAT),
                'attribute_types' => $attributeTypes->map(function ($type) use ($packageAttributeIds) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'attributes' => $type->attributes->map(function ($attr) use ($packageAttributeIds) {
                            return [
                                'id' => $attr->id,
                                'content' => $attr->content,
                                'enabled' => in_array($attr->id, $packageAttributeIds),
                            ];
                        }),
                    ];
                }),
            ];
        });

        return $packagesWithAttributes;
    }

     /**
     * Get search callback for Package model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }

    /**
     * Force recreate NMI plan for a package (delete + create)
     */
    public function recreateGatewayPlan(Package $package, string $paymentMethodId)
    {
        // Get payment method by UUID
        $paymentMethod = PaymentMethod::where('uuid', $paymentMethodId)->firstOrFail();

        // Find existing gateway package
        $gatewayPackage = GatewayPackage::where('package_id', $package->id)
            ->where('payment_method_id', $paymentMethod->id)
            ->first();

        if (!$gatewayPackage) {
            throw new \Exception('Gateway package not found for this package and payment method');
        }

        // Force recreate the plan on NMI
        if (strtolower($paymentMethod->name) === 'nmi') {
            $nmiService = app(NmiService::class);
            $result = $nmiService->recreateSubscriptionPlan($gatewayPackage);

            if (!$result['success']) {
                throw new \Exception('Failed to recreate plan on NMI: ' . $result['error']);
            }
        }

        // Load relationships and return
        $gatewayPackage->load(['package', 'paymentMethod']);
        return \App\Http\Resources\GatewayPackageResource::make($gatewayPackage);
    }
}
