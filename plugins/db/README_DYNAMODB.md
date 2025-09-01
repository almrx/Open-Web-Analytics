# DynamoDB Support for Open Web Analytics

This document describes how to configure Open Web Analytics (OWA) to use Amazon DynamoDB as the backend database instead of MySQL.

## Prerequisites

1. **AWS Account**: You need an AWS account with DynamoDB access
2. **AWS Credentials**: IAM user with DynamoDB permissions
3. **AWS SDK for PHP**: Installed via Composer

## Installation

1. Install the AWS SDK for PHP:
   ```bash
   composer require aws/aws-sdk-php
   ```

2. Configure your `owa-config.php` file with DynamoDB settings:
   ```php
   define('OWA_DB_TYPE', 'dynamodb');
   define('OWA_DB_USER', 'your_aws_access_key_id');
   define('OWA_DB_PASSWORD', 'your_aws_secret_access_key');
   define('OWA_DB_NAME', 'owa_'); // Table prefix for your OWA tables
   define('OWA_DYNAMODB_REGION', 'us-east-1'); // Your AWS region
   ```

   Alternatively, you can copy `owa-config-dynamodb-sample.php` to `owa-config.php` and update the values.

## Configuration Parameters

When using DynamoDB, the configuration parameters have different meanings:

- **OWA_DB_TYPE**: Set to `'dynamodb'`
- **OWA_DB_USER**: Your AWS Access Key ID
- **OWA_DB_PASSWORD**: Your AWS Secret Access Key
- **OWA_DB_NAME**: Table prefix (e.g., 'owa_' will create tables like 'owa_visitors', 'owa_sessions')
- **OWA_DB_HOST**: Not used for DynamoDB
- **OWA_DB_PORT**: Not used for DynamoDB
- **OWA_DYNAMODB_REGION**: AWS region where your DynamoDB tables will be created

## AWS Permissions

Your AWS IAM user needs the following DynamoDB permissions:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "dynamodb:CreateTable",
                "dynamodb:DeleteTable",
                "dynamodb:DescribeTable",
                "dynamodb:PutItem",
                "dynamodb:GetItem",
                "dynamodb:UpdateItem",
                "dynamodb:DeleteItem",
                "dynamodb:Query",
                "dynamodb:Scan"
            ],
            "Resource": "*"
        }
    ]
}
```

## How It Works

The DynamoDB adapter (`owa_db_dynamodb.php`) provides a translation layer between OWA's SQL-based operations and DynamoDB's NoSQL API:

- **Table Creation**: Automatically creates DynamoDB tables with appropriate key schemas
- **CRUD Operations**: Translates INSERT, SELECT, UPDATE, DELETE to DynamoDB PutItem, Query/Scan, UpdateItem, DeleteItem
- **Query Translation**: Converts WHERE clauses to DynamoDB filter expressions
- **Data Type Mapping**: Maps SQL data types to DynamoDB attribute types (String, Number, Boolean)

## Differences from MySQL

DynamoDB is a NoSQL database, which means some SQL features are not available:

- **No JOINs**: Related data must be denormalized or retrieved separately
- **Limited aggregation**: COUNT, SUM, AVG operations are limited
- **No complex WHERE clauses**: Only simple equality and range queries supported
- **No schema changes**: Tables can't be altered after creation (columns can't be added/removed)
- **Primary Key Required**: Each table must have a primary key (defaults to 'id' if none specified)

## Performance Considerations

- DynamoDB is configured with **PAY_PER_REQUEST** billing mode for simplicity
- For high-traffic sites, consider switching to **PROVISIONED** throughput mode
- Design your queries to use the primary key when possible for best performance
- Scan operations are more expensive than Query operations

## Architecture

The implementation consists of:

- **owa_db_dynamodb.php**: Main adapter class extending `owa_db`
- **SQL Translation Layer**: Converts SQL operations to DynamoDB API calls
- **Data Type Mapping**: Handles conversion between SQL and DynamoDB data types
- **Error Handling**: Provides meaningful error messages and fallbacks

## Installation Process

1. **Dependencies**: Run `composer install` to install AWS SDK
2. **Configuration**: Set up AWS credentials and region
3. **OWA Installation**: Run OWA installation process normally
4. **Table Creation**: Tables are created automatically during setup

## Troubleshooting

### Connection Issues
- Verify AWS credentials are correct
- Check AWS region setting
- Ensure IAM user has required DynamoDB permissions

### Permission Errors
- Review IAM policy attached to your user
- Verify resource ARNs if using resource-specific permissions

### Table Creation Issues
- Check CloudWatch logs for detailed error messages
- Ensure table names don't conflict with existing tables
- Verify AWS region has DynamoDB available

### Performance Issues
- Use primary key queries when possible
- Consider using Global Secondary Indexes for frequent queries
- Monitor DynamoDB metrics in CloudWatch

## Monitoring

Monitor your DynamoDB usage through:
- AWS CloudWatch metrics
- DynamoDB console
- AWS billing dashboard (for cost monitoring)

## Limitations

This is an initial implementation with the following limitations:

- Complex SQL queries may not be fully supported
- JOIN operations are not implemented
- Some advanced MySQL features are not available
- Performance may vary compared to MySQL for certain query patterns
- Transactions are limited to single items

## Future Enhancements

Potential improvements for future versions:
- Global Secondary Index support for improved query performance
- Transaction support for multi-item operations
- Enhanced query optimization
- Better error handling and retry logic
- Migration tools from MySQL to DynamoDB

## Support

For issues related to DynamoDB integration:
1. Check the OWA error logs
2. Review AWS CloudWatch logs
3. Verify AWS credentials and permissions
4. Consult AWS DynamoDB documentation