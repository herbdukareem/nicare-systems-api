<?php

// Simple test script to verify DO Dashboard functionality
require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client(['base_uri' => 'http://127.0.0.1:8000']);

echo "=== Testing DO Dashboard Functionality ===\n\n";

try {
    // 1. Login as test desk officer
    echo "1. Logging in as test desk officer...\n";
    $response = $client->post('/api/login', [
        'json' => [
            'username' => 'test_do',
            'password' => 'password'
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);
    
    $loginData = json_decode($response->getBody(), true);
    if (!$loginData['success']) {
        throw new Exception('Login failed: ' . $loginData['message']);
    }
    
    $token = $loginData['data']['token'];
    $user = $loginData['data']['user'];
    echo "✅ Login successful! User: {$user['name']} (ID: {$user['id']})\n";
    echo "   Token: " . substr($token, 0, 20) . "...\n\n";
    
    // 2. Test DO Dashboard Overview
    echo "2. Testing DO Dashboard Overview...\n";
    $response = $client->get('/api/v1/do-dashboard/overview', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token"
        ]
    ]);
    
    $overviewData = json_decode($response->getBody(), true);
    if (!$overviewData['success']) {
        throw new Exception('Overview failed: ' . $overviewData['message']);
    }
    
    $data = $overviewData['data'];
    echo "✅ Overview successful!\n";
    echo "   Assigned Facilities: " . count($data['assigned_facilities']) . "\n";
    echo "   Stats: " . json_encode($data['stats']) . "\n\n";
    
    // 3. Test Referrals endpoint
    echo "3. Testing Referrals endpoint...\n";
    $response = $client->get('/api/v1/do-dashboard/referrals', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token"
        ],
        'query' => [
            'page' => 1,
            'per_page' => 10
        ]
    ]);
    
    $referralsData = json_decode($response->getBody(), true);
    if (!$referralsData['success']) {
        throw new Exception('Referrals failed: ' . $referralsData['message']);
    }
    
    echo "✅ Referrals endpoint successful!\n";
    echo "   Total referrals: " . $referralsData['data']['total'] . "\n\n";
    
    // 4. Test PA Codes endpoint
    echo "4. Testing PA Codes endpoint...\n";
    $response = $client->get('/api/v1/do-dashboard/pa-codes', [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => "Bearer $token"
        ],
        'query' => [
            'page' => 1,
            'per_page' => 10
        ]
    ]);
    
    $paCodesData = json_decode($response->getBody(), true);
    if (!$paCodesData['success']) {
        throw new Exception('PA Codes failed: ' . $paCodesData['message']);
    }
    
    echo "✅ PA Codes endpoint successful!\n";
    echo "   Total PA codes: " . $paCodesData['data']['total'] . "\n\n";
    
    echo "=== All tests passed! ===\n";
    echo "The DO Dashboard is working correctly.\n";
    echo "You can now:\n";
    echo "1. Login to the frontend with username: test_do, password: password\n";
    echo "2. You should be redirected to /do-dashboard\n";
    echo "3. View assigned facilities, referrals, and PA codes\n";
    echo "4. Test UTN validation if you have secondary/tertiary facilities\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    if (isset($response)) {
        echo "Response: " . $response->getBody() . "\n";
    }
}
