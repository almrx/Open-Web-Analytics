<?php

//
// Simple test to validate DynamoDB database class functionality
//

// Load the OWA base framework files
require_once 'owa_base.php';

// Load base DB class first
if (!class_exists('owa_db')) {
    require_once 'owa_db.php';
}

// Test 1: Check if DynamoDB class can be loaded
echo "Test 1: Loading DynamoDB class...\n";
try {
    require_once 'plugins/db/owa_db_dynamodb.php';
    echo "✓ DynamoDB class loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Failed to load DynamoDB class: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if class can be instantiated
echo "\nTest 2: Instantiating DynamoDB class...\n";
try {
    $db = new owa_db_dynamodb('us-east-1', null, 'test_', null, null, false, false);
    echo "✓ DynamoDB class instantiated successfully\n";
} catch (Exception $e) {
    echo "✗ Failed to instantiate DynamoDB class: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check connection parameter methods
echo "\nTest 3: Testing connection parameter methods...\n";
try {
    $db->setConnectionParam('region', 'us-west-2');
    $region = $db->getConnectionParam('region');
    if ($region === 'us-west-2') {
        echo "✓ Connection parameter methods work correctly\n";
    } else {
        echo "✗ Connection parameter methods failed\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Connection parameter test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Basic tests passed! DynamoDB class can be loaded and instantiated.\n";
echo "\nNote: These tests do not require an actual AWS connection.\n";
echo "To test full functionality, you would need AWS credentials and DynamoDB access.\n";

?>