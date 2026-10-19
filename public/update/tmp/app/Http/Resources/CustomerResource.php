<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    protected $extraFields;

    public function __construct($resource, $extraFields = [])
    {
        parent::__construct($resource);
        $this->extraFields = $extraFields;
    }

    public function toArray($request)
    {
        $data = parent::toArray($request);

        // Identify the model that contains customer information
        // It could be the resource itself (if it's a Customer) or the 'customer' relationship (if it's a Project/Property)
        $customer = $this->resource;
        if (! ($customer instanceof Customer)) {
            $customer = $this->customer ?? $this->user;
        }

        // ✅ Add only requested fields
        if (in_array('is_agent', $this->extraFields)) {
            $data['is_agent'] = $customer?->is_agent ?? false;
        }

        if (in_array('is_user_verified', $this->extraFields)) {
            $data['is_user_verified'] = ($customer?->verifyCustomer ?? $customer?->verify_customer)?->status === 'approved';
        }

        if (in_array('is_appointment_available', $this->extraFields)) {
            if ($customer instanceof Customer) {
                $data['is_appointment_available'] =
                    $customer->verifyCustomer?->status === 'approved' &&
                    $customer->property->where('status', 1)
                        ->where('request_status', 'approved')
                        ->whereIn('propery_type', [0, 1])
                        ->isNotEmpty() &&
                    $customer->agent_availabilities->where('is_active', 1)->isNotEmpty();
            } else {
                $data['is_appointment_available'] = false;
            }
        }

        if (in_array('become_agent_status', $this->extraFields)) {
            $data['become_agent_status'] = $customer?->becomeAgent?->status ?? 'not_applied';
        }

        if (in_array('agent_verification_status', $this->extraFields)) {
            $data['agent_verification_status'] = $customer?->verifyAgent?->status ?? 'not_applied';
        }

        if (in_array('user_verification_status', $this->extraFields)) {
            $data['user_verification_status'] = ($customer?->verifyCustomer ?? $customer?->verify_customer)?->status ?? 'not_applied';
        }

        return $data;
    }
}
