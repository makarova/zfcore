<?php
require_once 'Zend/Application.php';
require_once 'Zend/Test/PHPUnit/ControllerTestCase.php';

/**
 * Controller Test case
 * 
 * @category Tests
 */
abstract class ControllerTestCase extends Zend_Test_PHPUnit_ControllerTestCase
{
    /**
     * Application entity
     *
     * @var Zend_Application
     */
    protected $_application;
    
    /**
     * Migration manager
     *
     * @var Core_Migration_Manager
     */
    protected $_manager;
    
    /**
     * fixtures put here
     *
     * @var as you wish
     */
    protected $_fixture;

    /**
     * Setup TestCase
     */
    public function setUp()
    {
        $this->bootstrap = new Zend_Application(
            APPLICATION_ENV,
            APPLICATION_PATH . '/configs/application.ini'
        );

        parent::setUp();

        $this->getFrontController()->setParam('bootstrap', $this->bootstrap->getBootstrap());

    }

    /**
     * Init Application
     */
    static public function appInit()
    {
        $application = new Zend_Application(
            APPLICATION_ENV,
            APPLICATION_PATH . '/configs/application.ini'
        );
        
//        $application->bootstrap();
//        $application->bootstrap('Frontcontroller');
        $application->bootstrap('db');

        self::migration();
    }
    
    /**
     * Shut down Application
     */
    static public function appDown()
    {
        self::migrationDown();

        try {
            Zend_Db_Table_Abstract::getDefaultAdapter()
                ->query("DROP TABLE `migrations`");
        } catch (Exception $e) {
            
        }
    }
    
    /**
     * Migrations
     */
    static public function migration($up = true, $module = null, $migration = null)
    {
        require_once 'Core/Migration/Manager.php';
        
        if ((null === $migration) && Core_Migration_Manager::isMigration($module)) {
            list($migration, $module) = array($module, null);
        }
        
        $manager = new Core_Migration_Manager(array(
            'projectDirectoryPath'    => APPLICATION_PATH . '/../',
            'modulesDirectoryPath'    => APPLICATION_PATH . '/modules/',
            'migrationsDirectoryName' => 'migrations',
        ));
        
        if ($up) {
            $manager->up($module, $migration);
        } else {
            $manager->down($module, $migration);
        }

        foreach ($manager->getMessages() as $message) {
            echo $message ."\n";
        }
    }

    /**
     * Migrations Up
     */
    static public function migrationUp($module = null)
    {
        echo "\n";
        self::migration(true, $module);
    }

    /**
     * Migrations Down
     */
    static public function migrationDown($module = null)
    {
        echo "\n";
        self::migration(false, $module);
    }
    
    /**
     * Change environment for user role/status
     * Should be run after setUp() !!!
     *
     * @param string $role
     * @param string $status
     * @return void
     */
    protected static function _doLogin($role = Model_User::ROLE_USER, $status = Model_User::STATUS_ACTIVE )
    {
        Zend_Auth::getInstance()->getStorage()
                                ->write(
                                    self::_generateFakeIdentity($role, $status)
                                );
    }
    
    
    /**
     * Create user
     *
     * @param string $role
     * @param string $status
     * @return StdClass an identity
     */
    protected static function _generateFakeIdentity($role = Model_User::ROLE_USER, $status = Model_User::STATUS_ACTIVE)
    {
        $account = new stdClass();
        
        $account->login    = 'AutoTest' . date('YmdHis');
        $account->email    = 'autotest' . time() . '@example.org';
        $account->password = md5('password');
        $account->role     = $role;
        $account->status   = $status;
        $account->id       = 75;
 
        return $account;
    }
    
    /**
     * Remove environment
     *
     */
    protected function tearDown()
    {
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $dbAdapter -> closeConnection();

        parent::tearDown();
    }
}