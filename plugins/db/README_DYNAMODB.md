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

## Differences from MySQL

DynamoDB is a NoSQL database, which means some SQL features are not available:

- **No JOINs**: Related data must be denormalized or retrieved separately
- **Limited aggregation**: COUNT, SUM, AVG operations are limited
- **No complex WHERE clauses**: Only simple equality and range queries
- **No schema changes**: Tables can't be altered after creation (columns can't be added/removed)

## Performance Considerations

- DynamoDB is configured with **PAY_PER_REQUEST** billing mode for simplicity
- For high-traffic sites, consider switching to **PROVISIONED** throughput mode
- Design your queries to use the primary key when possible for best performance

## Troubleshooting

1. **Connection Issues**: Check your AWS credentials and region settings
2. **Permission Errors**: Verify your IAM user has the required DynamoDB permissions
3. **Table Creation**: DynamoDB tables may take a few seconds to become available after creation

## Limitations

This is an initial implementation with the following limitations:

- Complex SQL queries may not be fully supported
- JOIN operations are not implemented
- Some advanced MySQL features are not available
- Performance may vary compared to MySQL for certain query patterns