# DynamoDB Database Support for Open Web Analytics

This implementation adds DynamoDB support to the Open Web Analytics framework, allowing users to choose between MySQL and DynamoDB as their backend database.

## Features Implemented

### 1. Database Abstraction Layer
- **New DynamoDB Class**: `plugins/db/owa_db_dynamodb.php` - Complete DynamoDB implementation
- **Enhanced Base Class**: Added `setConnectionParam()` method to `owa_db.php` for flexible configuration
- **Database Factory**: Updated `owa_coreAPI.php` to handle both MySQL and DynamoDB configuration patterns

### 2. CRUD Operations for DynamoDB
- **Create (Insert)**: Maps entity properties to DynamoDB items with automatic type conversion
- **Read (Select)**: Uses DynamoDB scan operations with filter expressions for WHERE clauses
- **Update**: Updates DynamoDB items using UpdateExpression with proper key handling  
- **Delete**: Removes DynamoDB items based on key conditions

### 3. Schema Management
- **Table Creation**: Automatic DynamoDB table creation with appropriate partition keys
- **Type Conversion**: Bidirectional conversion between PHP types and DynamoDB attribute types
- **NoSQL Adaptation**: Handles the differences between SQL and NoSQL data models

### 4. Configuration Support
- **Installation**: Enhanced `modules/base/installConfig.php` to validate DynamoDB vs MySQL settings
- **Connection Parameters**: Support for AWS regions, credentials, endpoints (for local development)
- **Validation**: Different validation rules for SQL vs NoSQL database types

## Configuration

### DynamoDB Configuration Parameters

When using DynamoDB, the following configuration parameters are supported:

```php
// Required for DynamoDB
define('OWA_DB_TYPE', 'dynamodb');
define('OWA_DB_REGION', 'us-east-1'); // AWS region

// Optional - if not provided, will use IAM roles or environment variables
define('OWA_AWS_ACCESS_KEY_ID', 'your_access_key');
define('OWA_AWS_SECRET_ACCESS_KEY', 'your_secret_key');

// Optional - for local DynamoDB development
define('OWA_DYNAMODB_ENDPOINT', 'http://localhost:8000');

// Optional - table prefix (defaults to 'owa_')
define('OWA_DB_TABLE_PREFIX', 'myapp_owa_');
```

### MySQL Configuration (Unchanged)

```php
define('OWA_DB_TYPE', 'mysql');
define('OWA_DB_HOST', 'localhost');
define('OWA_DB_NAME', 'owa_database');
define('OWA_DB_USER', 'owa_user');
define('OWA_DB_PASSWORD', 'password');
define('OWA_DB_PORT', '3306');
```

## Installation Requirements

### For DynamoDB Support

1. **AWS SDK for PHP**: Add to your `composer.json`:
   ```json
   {
     "require": {
       "aws/aws-sdk-php": "^3.0"
     }
   }
   ```

2. **AWS Credentials**: Either:
   - Set environment variables: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
   - Use IAM roles (recommended for EC2/ECS deployments)
   - Configure in OWA config file (see above)

3. **DynamoDB Access**: Ensure your AWS credentials have DynamoDB permissions:
   - `dynamodb:CreateTable`
   - `dynamodb:DescribeTable` 
   - `dynamodb:PutItem`
   - `dynamodb:GetItem`
   - `dynamodb:UpdateItem`
   - `dynamodb:DeleteItem`
   - `dynamodb:Scan`
   - `dynamodb:Query`

## Usage

Once configured, OWA will automatically use the selected database type. All existing OWA functionality should work transparently with either MySQL or DynamoDB.

### Entity Operations

```php
// This works with both MySQL and DynamoDB
$entity = owa_coreAPI::entityFactory('base.some_entity');
$entity->set('property', 'value');
$entity->save(); // Automatically uses correct database

// Table creation (automatic during install)
$entity->createTable(); // Creates MySQL table or DynamoDB table as appropriate
```

### Database Operations

```php
// All these operations work with both database types
$db = owa_coreAPI::dbSingleton();

$db->selectFrom('table_name');
$db->where('column', 'value');
$result = $db->getAllRows();

$db->insertInto('table_name');
$db->set('column', 'value');
$db->executeQuery();
```

## Data Model Considerations

### MySQL to DynamoDB Mapping

- **Primary Keys**: Entity primary keys become DynamoDB partition keys
- **Indexes**: Secondary indexes can be created but require manual configuration
- **Relationships**: Foreign key relationships work at the application layer
- **Transactions**: DynamoDB transactions are not currently implemented (single-item operations only)

### Data Types

The implementation handles automatic conversion between PHP and DynamoDB types:

- `string` → DynamoDB String (S)
- `int/float` → DynamoDB Number (N) 
- `bool` → DynamoDB Boolean (BOOL)
- `null` → DynamoDB Null (NULL)

## Limitations

1. **Complex Queries**: DynamoDB uses scan operations which are less efficient than SQL queries for complex filtering
2. **Joins**: No native join support (handled at application layer)
3. **Transactions**: Single-item operations only (no multi-table transactions)
4. **Sorting**: Limited sorting capabilities compared to SQL ORDER BY
5. **Aggregations**: No native SUM, COUNT, AVG operations (computed at application layer)

## Development and Testing

### Local DynamoDB

For development, you can use DynamoDB Local:

```bash
# Download and run DynamoDB Local
java -Djava.library.path=./DynamoDBLocal_lib -jar DynamoDBLocal.jar -sharedDb

# Configure OWA to use local endpoint
define('OWA_DYNAMODB_ENDPOINT', 'http://localhost:8000');
```

### Switching Between Databases

You can switch between MySQL and DynamoDB by changing the `OWA_DB_TYPE` setting and ensuring the appropriate configuration parameters are set.

## Files Modified/Created

- **New**: `plugins/db/owa_db_dynamodb.php` - DynamoDB database implementation
- **Modified**: `owa_db.php` - Added setConnectionParam method
- **Modified**: `owa_coreAPI.php` - Enhanced dbFactory for DynamoDB support
- **Modified**: `modules/base/installConfig.php` - Added DynamoDB configuration validation
- **Modified**: `owa-config-dist.php` - Updated to show DynamoDB option
- **Modified**: `composer.json` - Added AWS SDK dependency

## Future Enhancements

- Support for DynamoDB Global Secondary Indexes (GSI)
- Batch operations for improved performance
- DynamoDB Transactions API integration
- Query optimization for common access patterns
- DynamoDB Streams integration for real-time processing