<?php

/**
 * Sample OWA Configuration for DynamoDB
 * 
 * Copy this to owa-config.php and update with your AWS credentials
 */

/**
 * DATABASE CONFIGURATION - DynamoDB
 */
define('OWA_DB_TYPE', 'dynamodb');
define('OWA_DB_USER', 'AKIA...'); // Your AWS Access Key ID
define('OWA_DB_PASSWORD', '...'); // Your AWS Secret Access Key
define('OWA_DB_NAME', 'owa_'); // Table prefix for your OWA tables
define('OWA_DYNAMODB_REGION', 'us-east-1'); // AWS region

// Not used for DynamoDB but required by OWA
define('OWA_DB_HOST', '');
define('OWA_DB_PORT', '');

/**
 * AUTHENTICATION KEYS AND SALTS
 */
define('OWA_NONCE_KEY', 'your-unique-nonce-key-here');
define('OWA_NONCE_SALT', 'your-unique-nonce-salt-here');
define('OWA_AUTH_KEY', 'your-unique-auth-key-here');
define('OWA_AUTH_SALT', 'your-unique-auth-salt-here');

/**
 * PUBLIC URL
 */
define('OWA_PUBLIC_URL', 'http://your-domain.com/owa/');

/**
 * DEVELOPMENT SETTINGS
 */
// Uncomment for debugging
// define('OWA_ERROR_HANDLER', 'development');

?>