<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Models\Beneficiary;

class BeneficaryService
{


    public function createBeneficiary(array $validatedData)
    {
        $userId = auth()->id();

        #  Check if this combination already exists
        $exists = Beneficiary::where('user_id', $userId)
            ->where('phone', $validatedData['phone'])
            ->where('service_type', $validatedData['service_type'])
            ->exists();

        if ($exists) {
            return Utility::outputData(false, 'Beneficiary already exists', $validatedData, 200);
        }

        return Beneficiary::create([
            'name' => $validatedData['name'] ?? null,
            'phone' => $validatedData['phone'],
            'service_type' => $validatedData['service_type'],
            'user_id' => $userId,
        ]);
    }

}
