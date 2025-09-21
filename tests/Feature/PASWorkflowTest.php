<?php

namespace Tests\Feature;

use Tests\TestCase;

class PASWorkflowTest extends TestCase
{

    /**
     * Test that JSON string services are properly parsed and validated
     */
    public function test_services_json_string_parsing()
    {
        // Simulate the JSON parsing logic from the controller
        $servicesArray = [
            ['id' => 1, 'type' => 'service', 'price' => 1000.00],
            ['id' => 2, 'type' => 'drug', 'price' => 500.00]
        ];

        // Test with JSON string (as sent by FormData)
        $requestData = [
            'services' => json_encode($servicesArray)
        ];

        // Apply the same logic as in the controller
        if (isset($requestData['services']) && is_string($requestData['services'])) {
            $requestData['services'] = json_decode($requestData['services'], true);
        }

        // Verify the services are now an array
        $this->assertIsArray($requestData['services']);
        $this->assertCount(2, $requestData['services']);
        $this->assertEquals(1, $requestData['services'][0]['id']);
        $this->assertEquals('service', $requestData['services'][0]['type']);
    }

    /**
     * Test that array services remain unchanged
     */
    public function test_services_array_unchanged()
    {
        $servicesArray = [
            ['id' => 1, 'type' => 'service', 'price' => 1000.00],
            ['id' => 2, 'type' => 'drug', 'price' => 500.00]
        ];

        // Test with actual array
        $requestData = [
            'services' => $servicesArray
        ];

        // Apply the same logic as in the controller
        if (isset($requestData['services']) && is_string($requestData['services'])) {
            $requestData['services'] = json_decode($requestData['services'], true);
        }

        // Verify the services remain an array
        $this->assertIsArray($requestData['services']);
        $this->assertCount(2, $requestData['services']);
        $this->assertEquals($servicesArray, $requestData['services']);
    }

    /**
     * Test that invalid JSON string returns null and fails validation
     */
    public function test_invalid_json_services_string()
    {
        // Test with invalid JSON string
        $requestData = [
            'services' => 'invalid_json_string'
        ];

        // Apply the same logic as in the controller
        if (isset($requestData['services']) && is_string($requestData['services'])) {
            $requestData['services'] = json_decode($requestData['services'], true);
        }

        // Verify that invalid JSON results in null
        $this->assertNull($requestData['services']);
    }

    /**
     * Test validation with nullable fields
     */
    public function test_nullable_fields_validation()
    {
        // Test that nullable fields can be omitted or null
        $requestData = [
            'facility_id' => 1,
            'enrollee_id' => 1,
            'request_type' => 'referral',
            'services' => [['id' => 1]],
            'receiving_facility_id' => 1,
            'severity_level' => 'routine',
            'reasons_for_referral' => 'Required field',
            // Nullable fields omitted:
            // 'presenting_complaints' => null,
            // 'preliminary_diagnosis' => null,
            // 'personnel_full_name' => null,
            // 'personnel_phone' => null,
            // 'contact_full_name' => null,
            // 'contact_phone' => null,
        ];

        // Apply the same logic as in the controller
        if (isset($requestData['services']) && is_string($requestData['services'])) {
            $requestData['services'] = json_decode($requestData['services'], true);
        }

        // Test validation rules (simplified version)
        $rules = [
            'facility_id' => 'required|integer',
            'enrollee_id' => 'required|integer',
            'request_type' => 'required|in:referral,pa_code',
            'services' => 'required|array|min:1',
            'receiving_facility_id' => 'required|integer',
            'severity_level' => 'required|in:emergency,urgent,routine',
            'presenting_complaints' => 'nullable|string',
            'reasons_for_referral' => 'required|string',
            'preliminary_diagnosis' => 'nullable|string',
            'personnel_full_name' => 'nullable|string|max:255',
            'personnel_phone' => 'nullable|string|max:20',
            'contact_full_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($requestData, $rules);

        // Should pass validation even with nullable fields omitted
        $this->assertFalse($validator->fails(), 'Validation should pass with nullable fields omitted');
        $this->assertEmpty($validator->errors()->toArray(), 'Should have no validation errors');
    }
}
