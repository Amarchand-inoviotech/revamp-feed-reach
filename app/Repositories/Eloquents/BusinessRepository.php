<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\BusinessResource;
use App\Models\Address;
use App\Models\Business;
use App\Models\Company;
use App\Models\User;
use App\Repositories\Contracts\BusinessRepositoryContract;

class BusinessRepository extends BaseRepository implements BusinessRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(Business $model) {
        $this->model = $model;
        $this->resource = BusinessResource::class;
    }



    /**
     * Get search callback for Business model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }

    public function createBusiness(array $payload, bool $isResource = true)
    {
        //Register New User
        if($payload['user']['resident_state_id']){
            $address = [
                'name' => $payload['user']['name'] ?? null,
                'state_id' => $payload['user']['resident_state_id'],
                'phone' => $payload['user']['phone'] ?? null,
                'address_line1' => $payload['user']['address_line1'] ?? null,
                'address_line2' => $payload['user']['address_line2'] ?? null,
                'city' => null
            ];

                $residentAddressRepo = new AddressRepository(new Address());
                $residentAddress = $residentAddressRepo->storeModel($address, false);
                $payload['user']['address_id'] = $residentAddress->id;
        }

        $userRepo = new UserRepository(new User());
        $payload['user']['company_id'] = Company::where('name', config('app.company'))->value('id');
        $user = $userRepo->storeModel($payload['user'], false);

        // Track created addresses to avoid duplicates
        $createdAddresses = [];

        //Register New Business Address
        if(!empty($payload['business']['business_state_id']) && $payload['business']['business_state_id'] != $payload['user']['resident_state_id']){
            $address = [
                'name' => $payload['user']['name'] ?? null,
                'state_id' => $payload['business']['business_state_id'],
                'phone' => $payload['user']['phone'] ?? null,
                'address_line1' => $payload['address']['address_line1'],
                'address_line2' => $payload['address']['address_line2'],
                'city' => null,
                'zip' => null,
            ];

            $businessAddressRepo = new AddressRepository(new Address());
            /** @var Address $businessAddress */
            $businessAddress = $businessAddressRepo->storeModel($address, false);
            $payload['business']['business_address_id'] = $businessAddress->id;

            // Store this address for potential reuse
            $createdAddresses[$payload['business']['business_state_id']] = $businessAddress->id;

        }else{
            $payload['business']['business_address_id'] = $payload['user']['address_id'];
        }

        //Register New LLC Address (avoid duplicate if same as business address)
        if(!empty($payload['business']['llc_state_id']) && $payload['business']['llc_state_id'] != $payload['user']['resident_state_id']){

            // Check if we already created an address for this state
            if(isset($createdAddresses[$payload['business']['llc_state_id']])){
                // Reuse the existing address
                $payload['business']['llc_address_id'] = $createdAddresses[$payload['business']['llc_state_id']];
            } else {
                // Create new address only if different from business state
                $address = [
                    'name' => $payload['user']['name'] ?? null,
                    'state_id' => $payload['business']['llc_state_id'],
                    'phone' => $payload['user']['phone'] ?? null,
                    'address_line1' => $payload['address']['address_line1'],
                    'address_line2' => $payload['address']['address_line2'],
                    'city' => null,
                    'zip' => null,
                ];

                $llcAddressRepo = new AddressRepository(new Address());
                /** @var Address $llcAddress */
                $llcAddress = $llcAddressRepo->storeModel($address, false);
                $payload['business']['llc_address_id'] = $llcAddress->id;
            }
        }else{
            $payload['business']['llc_address_id'] = $payload['business']['business_address_id'];
        }

        //Register New Business of a User
        $model =$user->businesses()->make();
        $model->toFill($payload['business']);
        $model->save();

        return $this->resource ? $this->resource::make($model) : $model;
    }
}
