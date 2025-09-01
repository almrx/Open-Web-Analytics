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

require_once(OWA_BASE_CLASS_DIR.'installController.php');

/**
 * Install Configuration Controller
 * 
 * @author      Peter Adams <peter@openwebanalytics.com>
 * @copyright   Copyright &copy; 2006 Peter Adams <peter@openwebanalytics.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GPL v2.0
 * @category    owa
 * @package     owa
 * @version        $Revision$
 * @since        owa 1.0.0
 */

class owa_installConfigController extends owa_installController {

    function __construct($params) {
    
        parent::__construct($params);

        // require nonce
        $this->setNonceRequired();
    }

    public function validate()
    {
        //required params
        $db_type = $this->getParam('db_type');
        $this->addValidation('db_type', $db_type, 'required', ['errorMsg' => 'Database type is required.']);
        
        // Different validation based on database type
        if ($db_type === 'dynamodb') {
            // DynamoDB specific validation
            $this->addValidation('aws_region', $this->getParam('aws_region'), 'required', ['errorMsg' => 'AWS Region is required for DynamoDB.']);
            // AWS credentials are optional if using IAM roles or environment variables
        } else {
            // Traditional SQL database validation
            $this->addValidation('db_host', $this->getParam('db_host'), 'required', ['errorMsg' => 'Database host is required.']);
            $this->addValidation('db_name', $this->getParam('db_name'), 'required', ['errorMsg' => 'Database name is required.']);
            $this->addValidation('db_user', $this->getParam('db_user'), 'required', ['errorMsg' => 'Database user is required.']);
            $this->addValidation('db_password', $this->getParam('db_password'), 'required', ['errorMsg' => 'Database password is required.']);
        }

        // Config for the public_url validation
        $publicUrlConf = [
            'substring' => 'http',
            'match'     => '/',
            'length'    => -1,
            'position'  => -1,
            'operator'  => '=',
            'errorMsg'  => 'Your URL of OWA\'s base directory must end with a slash.'
        ];

        $this->addValidation('public_url', $this->getParam('public_url'), 'subStringMatch', $publicUrlConf);

        // Config for the domain validation
        $domainConf = [
            'substring' => 'http',
            'position'  => 0,
            'operator'  => '=',
            'errorMsg'  => 'Please add http:// or https:// to the beginning of your public url.'
        ];

        $this->addValidation('public_url', $this->getParam('public_url'), 'subStringPosition', $domainConf);
    }

    function action() {

        // define db connection constants using values submitted
        if ( ! defined( 'OWA_DB_TYPE' ) ) {
            define( 'OWA_DB_TYPE', $this->getParam( 'db_type' ) );
        }

        $db_type = $this->getParam('db_type');
        
        if ($db_type === 'dynamodb') {
            // Handle DynamoDB configuration
            if ( ! defined( 'OWA_DB_REGION' ) ) {
                define('OWA_DB_REGION', $this->getParam('aws_region'));
            }
            if ( ! defined( 'OWA_AWS_ACCESS_KEY_ID' ) && $this->getParam('aws_access_key_id') ) {
                define('OWA_AWS_ACCESS_KEY_ID', $this->getParam('aws_access_key_id'));
            }
            if ( ! defined( 'OWA_AWS_SECRET_ACCESS_KEY' ) && $this->getParam('aws_secret_access_key') ) {
                define('OWA_AWS_SECRET_ACCESS_KEY', $this->getParam('aws_secret_access_key'));
            }
            if ( ! defined( 'OWA_DYNAMODB_ENDPOINT' ) && $this->getParam('dynamodb_endpoint') ) {
                define('OWA_DYNAMODB_ENDPOINT', $this->getParam('dynamodb_endpoint'));
            }
            if ( ! defined( 'OWA_DB_TABLE_PREFIX' ) ) {
                define('OWA_DB_TABLE_PREFIX', $this->getParam('table_prefix') ?: 'owa_');
            }
            
            owa_coreAPI::setSetting('base', 'db_type', OWA_DB_TYPE);
            owa_coreAPI::setSetting('base', 'db_region', OWA_DB_REGION);
            if (defined('OWA_AWS_ACCESS_KEY_ID')) {
                owa_coreAPI::setSetting('base', 'aws_access_key_id', OWA_AWS_ACCESS_KEY_ID);
            }
            if (defined('OWA_AWS_SECRET_ACCESS_KEY')) {
                owa_coreAPI::setSetting('base', 'aws_secret_access_key', OWA_AWS_SECRET_ACCESS_KEY);
            }
            if (defined('OWA_DYNAMODB_ENDPOINT')) {
                owa_coreAPI::setSetting('base', 'dynamodb_endpoint', OWA_DYNAMODB_ENDPOINT);
            }
            owa_coreAPI::setSetting('base', 'db_table_prefix', OWA_DB_TABLE_PREFIX);
            
        } else {
            // Handle traditional SQL database configuration
            if ( ! defined( 'OWA_DB_HOST' ) ) {
                define('OWA_DB_HOST', $this->getParam( 'db_host' ) );
            }

            if ( ! defined( 'OWA_DB_PORT' ) ) {
                define('OWA_DB_PORT', $this->getParam( 'db_port' ) );
            }

            if ( ! defined( 'OWA_DB_NAME' ) ) {
                define('OWA_DB_NAME', $this->getParam( 'db_name' ) );
            }

            if ( ! defined( 'OWA_DB_USER' ) ) {
                define('OWA_DB_USER', $this->getParam( 'db_user' ) );
            }

            if ( ! defined( 'OWA_DB_PASSWORD' ) ) {
                define('OWA_DB_PASSWORD', $this->getParam( 'db_password' ) );
            }
            
            owa_coreAPI::setSetting('base', 'db_type', OWA_DB_TYPE);
            owa_coreAPI::setSetting('base', 'db_host', OWA_DB_HOST);
            owa_coreAPI::setSetting('base', 'db_port', OWA_DB_PORT);
            owa_coreAPI::setSetting('base', 'db_name', OWA_DB_NAME);
            owa_coreAPI::setSetting('base', 'db_user', OWA_DB_USER);
            owa_coreAPI::setSetting('base', 'db_password', OWA_DB_PASSWORD);
        }

        // Check DB connection status
        $db = owa_coreAPI::dbSingleton();
        $db->connect();
        if ($db->connection_status != true) {
            $this->set('error_msg', $this->getMsg(3012));
            $this->set('config', $this->params);
            $this->setView('base.install');
            $this->setSubview('base.installConfigEntry');

        } else {
            //create config file
            $this->c->createConfigFile($this->params);
            $this->setRedirectAction('base.installDefaultsEntry');
        }
    }

    function errorAction() {
        
        $this->set('config', $this->params);
        $this->setView('base.install');
        $this->setSubview('base.installConfigEntry');
    }
}

?>