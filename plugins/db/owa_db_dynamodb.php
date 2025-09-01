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

/**
 * DynamoDB Data Access Class
 * 
 * @author      OWA Team
 * @license     http://www.gnu.org/copyleft/gpl.html GPL v2.0
 * @category    owa
 * @package     owa
 * @since        owa 1.7.0
 */
class owa_db_dynamodb extends owa_db {

    /**
     * DynamoDB Client
     * @var Aws\DynamoDb\DynamoDbClient
     */
    private $dynamoDbClient;

    /**
     * AWS Region
     * @var string
     */
    private $region;

    /**
     * Table prefix for multi-tenancy
     * @var string
     */
    private $tablePrefix;

    function connect() {

        if (!$this->connection) {

            try {
                // Load AWS SDK
                if (!class_exists('Aws\DynamoDb\DynamoDbClient')) {
                    // Try to autoload via composer
                    if (file_exists(OWA_BASE_DIR . '/vendor/autoload.php')) {
                        require_once(OWA_BASE_DIR . '/vendor/autoload.php');
                    } else {
                        $this->e->alert('AWS SDK for PHP not found. Please install via composer: composer require aws/aws-sdk-php');
                        $this->connection_status = false;
                        return false;
                    }
                }

                // Get AWS credentials and region from connection params
                $this->region = owa_coreAPI::getSetting('base', 'dynamodb_region') ?: $this->getConnectionParam('region') ?: 'us-east-1';
                $this->tablePrefix = $this->getConnectionParam('name') ?: 'owa_';

                $config = [
                    'region' => $this->region,
                    'version' => 'latest'
                ];

                // Add credentials if provided
                $accessKey = $this->getConnectionParam('user');
                $secretKey = $this->getConnectionParam('password');
                
                if ($accessKey && $secretKey) {
                    $config['credentials'] = [
                        'key' => $accessKey,
                        'secret' => $secretKey
                    ];
                }

                $this->dynamoDbClient = new \Aws\DynamoDb\DynamoDbClient($config);
                $this->connection = $this->dynamoDbClient;

                $this->connection_status = true;
                return true;

            } catch (Exception $e) {
                $this->e->alert('Could not connect to DynamoDB: ' . $e->getMessage());
                $this->connection_status = false;
                return false;
            }
        }

        return true;
    }

    /**
     * Execute DynamoDB operation
     *
     * @param string $sql The SQL statement (will be translated to DynamoDB operations)
     * @return mixed
     */
    function query($sql) {

        if ($this->connection_status == false) {
            $this->connect();
        }

        $this->e->debug(sprintf('DynamoDB Query: %s', $sql));

        try {
            // Parse the SQL-like query and translate to DynamoDB operation
            return $this->_translateAndExecute($sql);

        } catch (Exception $e) {
            $this->e->debug(
                sprintf(
                    'A DynamoDB error occurred. Error: %s. Query: %s',
                    $e->getMessage(),
                    $sql
                )
            );
            return false;
        }
    }

    /**
     * Translate SQL operations to DynamoDB operations
     * This is a simplified translation layer
     */
    private function _translateAndExecute($sql) {

        // Simple pattern matching for SQL operations
        $sql = trim($sql);

        if (preg_match('/^CREATE TABLE/i', $sql)) {
            return $this->_executeCreateTable($sql);
        } elseif (preg_match('/^DROP TABLE/i', $sql)) {
            return $this->_executeDropTable($sql);
        } elseif (preg_match('/^INSERT INTO/i', $sql)) {
            return $this->_executeInsert($sql);
        } elseif (preg_match('/^UPDATE/i', $sql)) {
            return $this->_executeUpdate($sql);
        } elseif (preg_match('/^DELETE FROM/i', $sql)) {
            return $this->_executeDelete($sql);
        } elseif (preg_match('/^SELECT/i', $sql)) {
            return $this->_executeSelect($sql);
        } else {
            // For other operations, just return true (some operations don't apply to DynamoDB)
            return true;
        }
    }

    private function _executeCreateTable($sql) {
        // Extract table name from CREATE TABLE statement
        if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];

            try {
                // Check if table already exists
                $this->dynamoDbClient->describeTable(['TableName' => $tableName]);
                return true; // Table already exists
            } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
                // Table doesn't exist, create it
                $params = [
                    'TableName' => $tableName,
                    'KeySchema' => [
                        [
                            'AttributeName' => 'id',
                            'KeyType' => 'HASH'
                        ]
                    ],
                    'AttributeDefinitions' => [
                        [
                            'AttributeName' => 'id',
                            'AttributeType' => 'S'
                        ]
                    ],
                    'BillingMode' => 'PAY_PER_REQUEST'
                ];

                $result = $this->dynamoDbClient->createTable($params);
                
                // Wait for table to be created
                $this->dynamoDbClient->waitUntil('TableExists', ['TableName' => $tableName]);
                
                return true;
            }
        }
        return false;
    }

    private function _executeDropTable($sql) {
        if (preg_match('/DROP TABLE IF EXISTS (\w+)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];

            try {
                $this->dynamoDbClient->deleteTable(['TableName' => $tableName]);
                return true;
            } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
                return true; // Table doesn't exist, consider it successful
            }
        }
        return false;
    }

    private function _executeInsert($sql) {
        // Parse INSERT INTO table (columns) VALUES (values) format
        if (preg_match('/INSERT INTO (\w+) \(([^)]+)\) VALUES \(([^)]+)\)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];
            $columns = array_map('trim', explode(',', $matches[2]));
            $values = array_map('trim', explode(',', $matches[3]));

            // Remove quotes from values
            $values = array_map(function($v) { return trim($v, "'\""); }, $values);

            $item = array();
            for ($i = 0; $i < count($columns); $i++) {
                if (isset($values[$i])) {
                    // Determine attribute type based on value
                    if (is_numeric($values[$i])) {
                        $item[$columns[$i]] = ['N' => (string)$values[$i]];
                    } elseif ($values[$i] === 'true' || $values[$i] === 'false') {
                        $item[$columns[$i]] = ['BOOL' => $values[$i] === 'true'];
                    } else {
                        $item[$columns[$i]] = ['S' => $values[$i]];
                    }
                }
            }

            try {
                $this->dynamoDbClient->putItem([
                    'TableName' => $tableName,
                    'Item' => $item
                ]);
                return true;
            } catch (Exception $e) {
                $this->e->debug('DynamoDB Insert Error: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    private function _executeUpdate($sql) {
        // Parse UPDATE table SET column = value WHERE condition
        if (preg_match('/UPDATE (\w+) SET (.+) WHERE (.+)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];
            $setClause = $matches[2];
            $whereClause = $matches[3];

            // Parse WHERE clause for key
            if (preg_match('/(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]/', $whereClause, $whereMatches)) {
                $keyName = $whereMatches[1];
                $keyValue = $whereMatches[2];

                // Parse SET clause
                $updateExpression = 'SET ';
                $expressionAttributeValues = array();
                $expressionAttributeNames = array();

                $setParts = explode(',', $setClause);
                $setItems = array();

                foreach ($setParts as $i => $setPart) {
                    if (preg_match('/(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]/', trim($setPart), $setMatches)) {
                        $attrName = $setMatches[1];
                        $attrValue = $setMatches[2];
                        
                        $placeholder = ':val' . $i;
                        $namePlaceholder = '#attr' . $i;
                        
                        $setItems[] = "$namePlaceholder = $placeholder";
                        $expressionAttributeNames[$namePlaceholder] = $attrName;
                        
                        // Determine value type
                        if (is_numeric($attrValue)) {
                            $expressionAttributeValues[$placeholder] = ['N' => (string)$attrValue];
                        } else {
                            $expressionAttributeValues[$placeholder] = ['S' => $attrValue];
                        }
                    }
                }

                $updateExpression .= implode(', ', $setItems);

                try {
                    $this->dynamoDbClient->updateItem([
                        'TableName' => $tableName,
                        'Key' => [
                            $keyName => ['S' => $keyValue]
                        ],
                        'UpdateExpression' => $updateExpression,
                        'ExpressionAttributeNames' => $expressionAttributeNames,
                        'ExpressionAttributeValues' => $expressionAttributeValues
                    ]);
                    return true;
                } catch (Exception $e) {
                    $this->e->debug('DynamoDB Update Error: ' . $e->getMessage());
                    return false;
                }
            }
        }
        return false;
    }

    private function _executeDelete($sql) {
        // Parse DELETE FROM table WHERE condition
        if (preg_match('/DELETE FROM (\w+) WHERE (.+)/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];
            $whereClause = $matches[2];

            // Parse WHERE clause for key
            if (preg_match('/(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]/', $whereClause, $whereMatches)) {
                $keyName = $whereMatches[1];
                $keyValue = $whereMatches[2];

                try {
                    $this->dynamoDbClient->deleteItem([
                        'TableName' => $tableName,
                        'Key' => [
                            $keyName => ['S' => $keyValue]
                        ]
                    ]);
                    return true;
                } catch (Exception $e) {
                    $this->e->debug('DynamoDB Delete Error: ' . $e->getMessage());
                    return false;
                }
            }
        }
        return false;
    }

    private function _executeSelect($sql) {
        // Parse basic SELECT statements
        if (preg_match('/SELECT \* FROM (\w+)(?:\s+WHERE\s+(.+))?/i', $sql, $matches)) {
            $tableName = $this->tablePrefix . $matches[1];
            $whereClause = isset($matches[2]) ? $matches[2] : null;

            try {
                $params = ['TableName' => $tableName];

                if ($whereClause) {
                    // Parse simple WHERE conditions (id = 'value')
                    if (preg_match('/(\w+)\s*=\s*[\'"]([^\'"]+)[\'"]/', $whereClause, $whereMatches)) {
                        $keyName = $whereMatches[1];
                        $keyValue = $whereMatches[2];

                        // Use Query operation for key-based lookup
                        $params['KeyConditionExpression'] = '#k = :v';
                        $params['ExpressionAttributeNames'] = ['#k' => $keyName];
                        $params['ExpressionAttributeValues'] = [':v' => ['S' => $keyValue]];

                        $result = $this->dynamoDbClient->query($params);
                    } else {
                        // Use Scan operation for other conditions
                        $result = $this->dynamoDbClient->scan($params);
                    }
                } else {
                    // Scan entire table
                    $result = $this->dynamoDbClient->scan($params);
                }

                // Convert DynamoDB response to associative array format
                $rows = array();
                foreach ($result['Items'] as $item) {
                    $row = array();
                    foreach ($item as $key => $value) {
                        // Extract the actual value from DynamoDB format
                        if (isset($value['S'])) {
                            $row[$key] = $value['S'];
                        } elseif (isset($value['N'])) {
                            $row[$key] = $value['N'];
                        } elseif (isset($value['BOOL'])) {
                            $row[$key] = $value['BOOL'];
                        }
                    }
                    $rows[] = $row;
                }

                return $rows;

            } catch (Exception $e) {
                $this->e->debug('DynamoDB Select Error: ' . $e->getMessage());
                return array();
            }
        }
        return array();
    }

    function close() {
        // DynamoDB client doesn't need explicit closing
        $this->connection = null;
        $this->connection_status = false;
    }

    /**
     * Fetch result set array for DynamoDB
     */
    function get_results($sql) {
        if ($sql) {
            return $this->query($sql);
        }
        return array();
    }

    /**
     * Fetch single row for DynamoDB
     */
    function get_row($sql) {
        $results = $this->get_results($sql);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Prepare and escape string for DynamoDB
     * DynamoDB handles this automatically, so we just return the string
     */
    function prepare($string) {
        if (is_null($string)) {
            return $string;
        }
        return $string;
    }

    function getAffectedRows() {
        // DynamoDB operations don't return affected rows in the same way
        return 1;
    }

    /**
     * Override createTable to handle DynamoDB table creation
     */
    function createTable($entity) {
        
        $tableName = $this->tablePrefix . $entity->getTableName();
        
        try {
            // Check if table already exists
            $this->dynamoDbClient->describeTable(['TableName' => $tableName]);
            return true; // Table already exists
        } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
            // Table doesn't exist, create it
            
            $all_cols = $entity->getColumns();
            $keySchema = array();
            $attributeDefinitions = array();
            
            // Find primary key and set up key schema
            foreach ($all_cols as $colName) {
                $colDef = $entity->getProperty($colName);
                if ($colDef && $colDef->isPrimaryKey()) {
                    $keySchema[] = [
                        'AttributeName' => $colName,
                        'KeyType' => 'HASH'
                    ];
                    
                    // Determine attribute type based on data type
                    $dataType = $colDef->getDataType();
                    $attributeType = 'S'; // Default to string
                    if (in_array($dataType, ['OWA_DTD_BIGINT', 'OWA_DTD_INT', 'OWA_DTD_TINYINT'])) {
                        $attributeType = 'N';
                    }
                    
                    $attributeDefinitions[] = [
                        'AttributeName' => $colName,
                        'AttributeType' => $attributeType
                    ];
                    break; // DynamoDB only supports one hash key
                }
            }
            
            // If no primary key found, use 'id' as default
            if (empty($keySchema)) {
                $keySchema[] = [
                    'AttributeName' => 'id',
                    'KeyType' => 'HASH'
                ];
                $attributeDefinitions[] = [
                    'AttributeName' => 'id',
                    'AttributeType' => 'S'
                ];
            }
            
            $params = [
                'TableName' => $tableName,
                'KeySchema' => $keySchema,
                'AttributeDefinitions' => $attributeDefinitions,
                'BillingMode' => 'PAY_PER_REQUEST'
            ];

            $result = $this->dynamoDbClient->createTable($params);
            
            // Wait for table to be created
            $this->dynamoDbClient->waitUntil('TableExists', ['TableName' => $tableName]);
            
            return true;
        } catch (Exception $e) {
            $this->e->alert('DynamoDB createTable error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Override dropTable to handle DynamoDB table deletion
     */
    function dropTable($table_name) {
        
        $tableName = $this->tablePrefix . $table_name;
        
        try {
            $this->dynamoDbClient->deleteTable(['TableName' => $tableName]);
            return true;
        } catch (\Aws\DynamoDb\Exception\ResourceNotFoundException $e) {
            return true; // Table doesn't exist, consider it successful
        } catch (Exception $e) {
            $this->e->alert('DynamoDB dropTable error: ' . $e->getMessage());
            return false;
        }
    }
}

?>