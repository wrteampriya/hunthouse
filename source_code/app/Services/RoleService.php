<?php

namespace App\Services;

use App\Models\Customer;
use Exception;

class RoleService
{
    /**
     * Upgrade a simple user customer profile to an Agent profile.
     *
     * @throws Exception
     */
    public function upgradeToAgent(Customer $customer): Customer
    {
        try {
            if (! $customer->is_agent) {
                $customer->is_agent = 1;
                $customer->save();
            }

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Error upgrading user to agent: '.$e->getMessage());
        }
    }

    /**
     * Remove the Agent role from a customer profile.
     *
     * @throws Exception
     */
    public function removeAgentRole(Customer $customer): Customer
    {
        try {
            if ($customer->is_agent) {
                $customer->is_agent = 0;
                $customer->save();
            }

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Error removing agent role: '.$e->getMessage());
        }
    }

    /**
     * Set the Customer's is_agent_verified attribute to 1
     * Giving them the Verified Agent badge.
     *
     * @throws Exception
     */
    public function grantAgentVerificationBadge(Customer $customer): Customer
    {
        try {
            if (! $customer->is_agent_verified) {
                $customer->is_agent_verified = 1;
                $customer->save();
            }

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Error granting agent verified badge: '.$e->getMessage());
        }
    }

    /**
     * Remove the Customer's verified badge by setting is_agent_verified to 0
     *
     * @throws Exception
     */
    public function revokeAgentVerificationBadge(Customer $customer): Customer
    {
        try {
            if ($customer->is_agent_verified) {
                $customer->is_agent_verified = 0;
                $customer->save();
            }

            return $customer;
        } catch (Exception $e) {
            throw new Exception('Error revoking agent verified badge: '.$e->getMessage());
        }
    }
}
