<?php

namespace App\Http\Requests\Frontoffice;

use App\Helpers\Helper;
use App\Models\Service;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Business validation
            'business' => ['array'],
            "business.name" => ['required', 'string', 'max:255','unique:businesses,name'],
            "business.service_id" => ['required', 'exists:services,id'],
            "business.llc_state_id" => ['required', 'exists:states,id'],
            "business.business_state_id" => ['required', 'exists:states,id'],


            // User validation
            'user' => ['array'],
            "user.name" => ['required', 'string', 'max:255'],
            //auto generate username from name
            "user.username" => ['nullable', 'string', 'min:3'],
            "user.email" => ['required', 'email', 'max:255','unique:users,email'],
            "user.phone" => ['required', 'string', 'max:255','unique:users,phone'],
            'user.resident_state_id'=> ['required','exists:states,id'],
            "user.password" => ['required', 'string', 'min:6','confirmed'],

            'address' => ["nullable",'array'],
            'address.state_id'=> ['nullable','exists:states,id'],
            'address.address_line1'=> ['nullable','string',"min:3"],
            'address.address_line2'=> ['nullable','string','min:3'],
            'address.city'=> ['nullable','string','min:2'],
            'address.zip'=> ['nullable','string','min:2'],
        ];
    }

    public function prepareForValidation(){
        $business = $this->business;
        $user = $this->user;
        $address = $this->address;

        // Cache for state lookups to avoid duplicate queries
        $stateCache = [];

        // Helper function to get state ID with caching
        $getStateId = function($uuid) use (&$stateCache) {
            if (!isset($stateCache[$uuid])) {
                $stateCache[$uuid] = State::where('uuid', $uuid)->value('id');
            }
            return $stateCache[$uuid];
        };

        // Handle state lookups with caching to avoid duplicates
        if(!empty($business['llc_state_id'])){
            $llcStateId = $getStateId($business['llc_state_id']);
            $this->merge(['business.llc_state_id' => $llcStateId]);

            // If business_state_id is the same as llc_state_id, reuse the same ID
            if($business['llc_state_id'] == $business['business_state_id']){
                $this->merge(['business.business_state_id' => $llcStateId]);
            }
        }

        // Only query business_state_id if it's different from llc_state_id
        if(!empty($business['business_state_id']) && $business['llc_state_id'] != $business['business_state_id']){
            $businessStateId = $getStateId($business['business_state_id']);
            $this->merge(['business.business_state_id' => $businessStateId]);
        }

        if(!empty($business['service_id'])){
            $this->merge(['business.service_id' =>  Service::where('uuid', $business['service_id'])->value('id')]);
        }

        // Handle user resident state with caching
        if(!empty($user['resident_state_id'])){
            $residentStateId = $getStateId($user['resident_state_id']);
            $this->merge(['user.resident_state_id' => $residentStateId]);
        }

        // Handle address state with caching
        if(!empty($address['state_id'])){
            $addressStateId = $getStateId($address['state_id']);
            $this->merge(['address.state_id' => $addressStateId]);
        }else{
             $this->merge(['address.state_id' => $this->input('business.state_id')]);
        }

        if(empty($address['name'])){
            $this->merge(['address.name' => $this->input('user.name')]);
        }

        // Generate unique username from user name
        if(!empty($user['name']) && empty($user['username'])){
            $username = Helper::generateSlug($user['name'],'users','username');
            $this->merge(['user.username' => $username]);
        }
    }
}
