<?php

//
// Open Web Analytics - An Open Source Web Analytics Framework
//
// Copyright 2006 Peter Adams. All rights reserved.
//
// Licensed under GPL v2.0 http://www.gnu.org/copyleft/gpl.html
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// $Id$
//


// DynamoDB-specific constants
define('OWA_DTD_BIGINT', 'N');
define('OWA_DTD_INT', 'N');
define('OWA_DTD_TINYINT', 'N');
define('OWA_DTD_TINYINT2', 'N');
define('OWA_DTD_TINYINT4', 'N');
define('OWA_DTD_SERIAL', 'N');
define('OWA_DTD_PRIMARY_KEY', 'HASH');
define('OWA_DTD_VARCHAR10', 'S');
define('OWA_DTD_VARCHAR255', 'S');
define('OWA_DTD_VARCHAR', 'S');
define('OWA_DTD_TEXT', 'S');
define('OWA_DTD_BOOLEAN', 'BOOL');
define('OWA_DTD_TIMESTAMP', 'N');
define('OWA_DTD_BLOB', 'B');
define('OWA_DTD_INDEX', 'GSI');
define('OWA_DTD_AUTO_INCREMENT', '');
define('OWA_DTD_NOT_NULL', '');
define('OWA_DTD_UNIQUE', 'HASH');

// DynamoDB operation mappings (many SQL operations don't apply to DynamoDB)
define('OWA_SQL_ADD_COLUMN', ''); // Not applicable in DynamoDB
define('OWA_SQL_DROP_COLUMN', ''); // Not applicable in DynamoDB
define('OWA_SQL_RENAME_COLUMN', ''); // Not applicable in DynamoDB
define('OWA_SQL_MODIFY_COLUMN', ''); // Not applicable in DynamoDB
define('OWA_SQL_RENAME_TABLE', ''); // Limited in DynamoDB
define('OWA_SQL_CREATE_TABLE', 'CreateTable');
define('OWA_SQL_DROP_TABLE', 'DeleteTable');
define('OWA_SQL_SHOW_TABLE', 'DescribeTable');
define('OWA_SQL_INSERT_ROW', 'PutItem');
define('OWA_SQL_UPDATE_ROW', 'UpdateItem');
define('OWA_SQL_DELETE_ROW', 'DeleteItem');
define('OWA_SQL_CREATE_INDEX', 'UpdateTable');
define('OWA_SQL_DROP_INDEX', 'UpdateTable');
define('OWA_SQL_INDEX', 'GSI');
define('OWA_SQL_BEGIN_TRANSACTION', 'TransactWrite');
define('OWA_SQL_END_TRANSACTION', 'TransactWrite');

// Table configuration
define('OWA_DTD_TABLE_TYPE', '');
define('OWA_DTD_TABLE_TYPE_DEFAULT', 'PROVISIONED');
define('OWA_DTD_TABLE_TYPE_DISK', 'PROVISIONED');
define('OWA_DTD_TABLE_TYPE_MEMORY', 'ON_DEMAND');
define('OWA_SQL_ALTER_TABLE_TYPE', 'UpdateTable');

// Join operations (not supported in DynamoDB - will need workarounds)
define('OWA_SQL_JOIN_LEFT_OUTER', '');
define('OWA_SQL_JOIN_LEFT_INNER', '');
define('OWA_SQL_JOIN_RIGHT_OUTER', '');
define('OWA_SQL_JOIN_RIGHT_INNER', '');
define('OWA_SQL_JOIN', '');

// Sorting and filtering
define('OWA_SQL_DESCENDING', 'DESC');
define('OWA_SQL_ASCENDING', 'ASC');
define('OWA_SQL_REGEXP', 'contains');
define('OWA_SQL_NOTREGEXP', 'not_contains');
define('OWA_SQL_LIKE', 'contains');
define('OWA_SQL_ADD_INDEX', 'UpdateTable');

// Aggregation functions (limited in DynamoDB)
define('OWA_SQL_COUNT', 'COUNT');
define('OWA_SQL_SUM', 'SUM');
define('OWA_SQL_ROUND', 'ROUND');
define('OWA_SQL_AVERAGE', 'AVG');
define('OWA_SQL_DISTINCT', 'DISTINCT');
define('OWA_SQL_DIVISION', 'DIVISION');

// Character encoding (not applicable to DynamoDB)
define('OWA_DTD_CHARACTER_ENCODING_UTF8', 'utf8');
define('OWA_DTD_TABLE_CHARACTER_ENCODING', '');

// DynamoDB-specific constants - NoSQL approach
define('OWA_DYNAMODB_TABLE_PREFIX', 'owa_');
define('OWA_DYNAMODB_GSI_SUFFIX', '_gsi');
define('OWA_DYNAMODB_DEFAULT_THROUGHPUT', 5);


/**
 * DynamoDB Data Access Class
 * 
 * @author      OWA Team
**/
class owa_db_dynamodb extends owa_db {

    var $dynamodb_client;
    var $region;
    var $table_prefix;

    function connect() {

        if ( ! $this->connection ) {

            // Check for AWS SDK
            if ( ! class_exists('Aws\DynamoDb\DynamoDbClient') ) {
                
                // Try to load composer autoload
                $autoload_paths = [
                    OWA_BASE_DIR . '/vendor/autoload.php',
                    OWA_BASE_DIR . '/../../vendor/autoload.php', // if installed via composer
                    dirname(__FILE__) . '/../../vendor/autoload.php'
                ];
                
                $autoload_found = false;
                foreach ($autoload_paths as $path) {
                    if (file_exists($path)) {
                        require_once $path;
                        $autoload_found = true;
                        break;
                    }
                }
                
                if (!$autoload_found) {
                    $this->e->error('AWS SDK for PHP autoload not found. Please install via: composer require aws/aws-sdk-php');
                    return false;
                }
                
                if (!class_exists('Aws\DynamoDb\DynamoDbClient')) {
                    $this->e->error('AWS SDK for PHP is required for DynamoDB support. Please install via: composer require aws/aws-sdk-php');
                    return false;
                }
            }

            // Get connection parameters for DynamoDB
            $this->region = $this->getConnectionParam('region') ?: 'us-east-1';
            $this->table_prefix = $this->getConnectionParam('table_prefix') ?: OWA_DYNAMODB_TABLE_PREFIX;

            $config = [
                'region' => $this->region,
                'version' => 'latest'
            ];

            // Add credentials if provided
            $key = $this->getConnectionParam('aws_access_key_id');
            $secret = $this->getConnectionParam('aws_secret_access_key');
            
            if ($key && $secret) {
                $config['credentials'] = [
                    'key' => $key,
                    'secret' => $secret
                ];
            } else {
                // Try to use environment variables or IAM roles
                $this->e->debug('Using default AWS credential chain (environment variables, IAM roles, etc.)');
            }

            // Add endpoint for local DynamoDB
            $endpoint = $this->getConnectionParam('endpoint');
            if ($endpoint) {
                $config['endpoint'] = $endpoint;
                $this->e->debug("Using custom DynamoDB endpoint: $endpoint");
            }

            try {
                $this->dynamodb_client = new \Aws\DynamoDb\DynamoDbClient($config);
                $this->connection = true;
                $this->connection_status = true;
                
                // Test connection with a simple operation
                $result = $this->dynamodb_client->listTables(['Limit' => 1]);
                $this->e->debug("DynamoDB connection successful. Service available.");
                
            } catch (\Aws\Exception\CredentialsException $e) {
                $this->e->error('DynamoDB credentials error: ' . $e->getMessage() . '. Please check AWS credentials configuration.');
                $this->connection_status = false;
                return false;
            } catch (\Aws\DynamoDb\Exception\DynamoDbException $e) {
                $this->e->error('DynamoDB service error: ' . $e->getMessage());
                $this->connection_status = false;
                return false;
            } catch (Exception $e) {
                $this->e->error('DynamoDB connection failed: ' . $e->getMessage());
                $this->connection_status = false;
                return false;
            }
        }

        return true;
    }

    function close() {
        $this->connection = null;
        $this->dynamodb_client = null;
        $this->connection_status = false;
        return true;
    }

    function isConnectionEstablished() {
        return $this->connection_status === true;
    }

    /**
     * Execute query based on query type
     * Override parent to handle DynamoDB operations
     */
    function _query() {
        
        $this->_timerStart();
        
        try {
            switch($this->_sqlParams['query_type']) {
                case 'insert':
                    $result = $this->_dynamoInsertQuery();
                    break;
                case 'select':
                    $result = $this->_dynamoSelectQuery();
                    break;
                case 'update':
                    $result = $this->_dynamoUpdateQuery();
                    break;
                case 'delete':
                    $result = $this->_dynamoDeleteQuery();
                    break;
                default:
                    $result = false;
            }
            
        } catch (Exception $e) {
            $this->e->error('DynamoDB query failed: ' . $e->getMessage());
            $result = false;
        }
        
        $this->_timerEnd();
        return $result;
    }

    /**
     * DynamoDB Insert Operation
     */
    private function _dynamoInsertQuery() {
        
        $table = $this->table_prefix . $this->_sqlParams['table'];
        $params = $this->_fetchSqlParams('set_values');
        
        if (empty($params)) {
            return false;
        }
        
        $item = [];
        foreach ($params as $param) {
            $item[$param['name']] = $this->_convertToDynamoType($param['value']);
        }
        
        $response = $this->dynamodb_client->putItem([
            'TableName' => $table,
            'Item' => $item
        ]);
        
        $this->rows_affected = 1;
        return true;
    }

    /**
     * DynamoDB Select Operation
     */
    private function _dynamoSelectQuery() {
        
        // Get table name from FROM clause or table parameter
        $table_name = '';
        $fromParams = $this->_fetchSqlParams('from');
        if (!empty($fromParams)) {
            // Get first table from FROM clause
            $firstTable = reset($fromParams);
            $table_name = $firstTable['name'];
        } else {
            // Fallback to table parameter
            $table_name = $this->_sqlParams['table'] ?? '';
        }
        
        if (empty($table_name)) {
            return false;
        }
        
        $table = $this->table_prefix . $table_name;
        
        // Handle simple case first - table scan with conditions
        $scanParams = [
            'TableName' => $table
        ];
        
        // Add filter conditions from WHERE clause
        $whereParams = $this->_fetchSqlParams('where');
        if (!empty($whereParams)) {
            $filterExpression = [];
            $expressionValues = [];
            
            foreach ($whereParams as $condition) {
                $placeholder = ':' . $condition['name'];
                $filterExpression[] = $condition['name'] . ' = ' . $placeholder;
                $expressionValues[$placeholder] = $this->_convertToDynamoType($condition['value']);
            }
            
            if (!empty($filterExpression)) {
                $scanParams['FilterExpression'] = implode(' AND ', $filterExpression);
                $scanParams['ExpressionAttributeValues'] = $expressionValues;
            }
        }
        
        // Add limit
        $limit = $this->_fetchSqlParams('limit');
        if ($limit) {
            $scanParams['Limit'] = (int)$limit[0]['limit'];
        }
        
        $response = $this->dynamodb_client->scan($scanParams);
        
        $this->result = [];
        if (isset($response['Items'])) {
            foreach ($response['Items'] as $item) {
                $row = [];
                foreach ($item as $key => $value) {
                    $row[$key] = $this->_convertFromDynamoType($value);
                }
                $this->result[] = $row;
            }
        }
        
        $this->num_rows = count($this->result);
        return true;
    }

    /**
     * DynamoDB Update Operation
     */
    private function _dynamoUpdateQuery() {
        
        $table = $this->table_prefix . $this->_sqlParams['table'];
        $setParams = $this->_fetchSqlParams('set_values');
        $whereParams = $this->_fetchSqlParams('where');
        
        if (empty($setParams) || empty($whereParams)) {
            return false;
        }
        
        // Build key from WHERE conditions (assuming primary key is in WHERE)
        $key = [];
        foreach ($whereParams as $condition) {
            $key[$condition['name']] = $this->_convertToDynamoType($condition['value']);
        }
        
        // Build update expression
        $updateExpression = 'SET ';
        $expressionValues = [];
        $updates = [];
        
        foreach ($setParams as $i => $param) {
            $placeholder = ':val' . $i;
            $updates[] = $param['name'] . ' = ' . $placeholder;
            $expressionValues[$placeholder] = $this->_convertToDynamoType($param['value']);
        }
        
        $updateExpression .= implode(', ', $updates);
        
        $this->dynamodb_client->updateItem([
            'TableName' => $table,
            'Key' => $key,
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues' => $expressionValues
        ]);
        
        $this->rows_affected = 1;
        return true;
    }

    /**
     * DynamoDB Delete Operation
     */
    private function _dynamoDeleteQuery() {
        
        $table = $this->table_prefix . $this->_sqlParams['table'];
        $whereParams = $this->_fetchSqlParams('where');
        
        if (empty($whereParams)) {
            return false;
        }
        
        // Build key from WHERE conditions
        $key = [];
        foreach ($whereParams as $condition) {
            $key[$condition['name']] = $this->_convertToDynamoType($condition['value']);
        }
        
        $this->dynamodb_client->deleteItem([
            'TableName' => $table,
            'Key' => $key
        ]);
        
        $this->rows_affected = 1;
        return true;
    }

    /**
     * Create DynamoDB table
     */
    function createTable($entity) {
        
        $tableName = $this->table_prefix . $entity->getTableName();
        
        // Check if table exists
        try {
            $this->dynamodb_client->describeTable(['TableName' => $tableName]);
            // Table exists
            return true;
        } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
            // Table doesn't exist, create it
        }
        
        // Get primary key from entity
        $primaryKey = $entity->getPrimaryKey();
        if (empty($primaryKey)) {
            $primaryKey = 'id'; // default
        }
        
        $keySchema = [
            [
                'AttributeName' => $primaryKey,
                'KeyType' => 'HASH'
            ]
        ];
        
        $attributeDefinitions = [
            [
                'AttributeName' => $primaryKey,
                'AttributeType' => 'S' // String by default
            ]
        ];
        
        $params = [
            'TableName' => $tableName,
            'KeySchema' => $keySchema,
            'AttributeDefinitions' => $attributeDefinitions,
            'BillingMode' => 'PAY_PER_REQUEST' // On-demand billing
        ];
        
        try {
            $this->dynamodb_client->createTable($params);
            
            // Wait for table to be active
            $this->dynamodb_client->waitUntil('TableExists', [
                'TableName' => $tableName,
                '@waiter' => [
                    'delay' => 5,
                    'maxAttempts' => 20
                ]
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->e->error('Failed to create DynamoDB table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if table exists
     */
    function tableExists($tableName) {
        try {
            $this->dynamodb_client->describeTable([
                'TableName' => $this->table_prefix . $tableName
            ]);
            return true;
        } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
            return false;
        }
    }

    /**
     * Convert PHP value to DynamoDB attribute value
     */
    private function _convertToDynamoType($value) {
        if (is_numeric($value)) {
            return ['N' => (string)$value];
        } elseif (is_bool($value)) {
            return ['BOOL' => $value];
        } elseif (is_null($value)) {
            return ['NULL' => true];
        } else {
            return ['S' => (string)$value];
        }
    }

    /**
     * Convert DynamoDB attribute value to PHP value
     */
    private function _convertFromDynamoType($dynamoValue) {
        if (isset($dynamoValue['S'])) {
            return $dynamoValue['S'];
        } elseif (isset($dynamoValue['N'])) {
            return is_float($dynamoValue['N']) ? (float)$dynamoValue['N'] : (int)$dynamoValue['N'];
        } elseif (isset($dynamoValue['BOOL'])) {
            return $dynamoValue['BOOL'];
        } elseif (isset($dynamoValue['NULL'])) {
            return null;
        } else {
            return $dynamoValue; // fallback
        }
    }

    /**
     * Override methods that don't apply to DynamoDB
     */
    function beginTransaction() {
        // DynamoDB doesn't have traditional transactions
        // Could implement with transaction API in future
        return true;
    }

    function endTransaction() {
        return true;
    }

    /**
     * Prepare string for DynamoDB (less sanitization needed)
     */
    function prepare($value) {
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    /**
     * Get all rows from result
     */
    function getAllRows() {
        return $this->result;
    }

    /**
     * Get single row from result
     */
    function getOneRow() {
        if (!empty($this->result)) {
            return $this->result[0];
        }
        return false;
    }

    /**
     * Get affected rows count
     */
    function getAffectedRows() {
        return $this->rows_affected;
    }

    /**
     * Execute raw query (limited DynamoDB support)
     */
    function query($operation) {
        // For DynamoDB, this would be for direct AWS SDK calls
        // Limited implementation for compatibility
        $this->e->debug("Direct query execution not supported in DynamoDB: $operation");
        return false;
    }

    /**
     * Get number of rows in result
     */
    function getNumRows() {
        return $this->num_rows;
    }

    /**
     * DynamoDB doesn't support traditional LIMIT/OFFSET pagination
     * This provides basic limit support using DynamoDB Limit parameter
     */
    function limit($limit) {
        $this->_sqlParams['limit'][] = array('limit' => $limit);
        return $this;
    }

    /**
     * Order by is not directly supported in DynamoDB scans
     * This is a no-op for compatibility but logs a warning
     */
    function orderBy($col, $direction = 'ASC') {
        $this->e->debug("ORDER BY not supported in DynamoDB scans. Results are not guaranteed to be ordered by $col $direction");
        return $this;
    }

    /**
     * Group by is not supported in DynamoDB
     * This is a no-op for compatibility but logs a warning  
     */
    function groupBy($col) {
        $this->e->debug("GROUP BY not supported in DynamoDB. Grouping by $col will be ignored.");
        return $this;
    }

    /**
     * Having clauses are not supported in DynamoDB
     * This is a no-op for compatibility but logs a warning
     */
    function having($name, $value, $operator = '=') {
        $this->e->debug("HAVING clauses not supported in DynamoDB. Having $name $operator $value will be ignored.");
        return $this;
    }
}
?>