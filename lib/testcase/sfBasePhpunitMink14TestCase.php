<?php

//namespace Phpunit\testcase;

// require_once 'mink/autoload.php';

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\GoutteDriver,
    Behat\Mink\Driver\Goutte\Client as GoutteClient,
    Behat\Mink\Driver\SahiDriver,
    Behat\Mink\Driver\ZombieDriver,
    Behat\Mink\Driver\SeleniumDriver,
    Behat\Mink\Driver\Selenium2Driver,
    Behat\Mink\Driver\NodeJS\Connection as ZombieConnection,
    Behat\Mink\Driver\NodeJS\Server\ZombieServer;

// use Goutte\Client as GoutteClient;

use Selenium\Client as SeleniumClient;

use Behat\SahiClient\Connection as SahiConnection,
    Behat\SahiClient\Client as SahiClient;

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mink TestCase.
 *
 * @author      Konstantin Kudryashov <ever.zet@gmail.com>
 * @author      Ton Sharp <Foma-PRO@66ton99.org.ua>
 */
abstract class sfBasePhpunitMink14TestCase extends \sfBasePhpunitTestCase
{

    /**
     * Full URL to local phpunit_coverage.php
     *
     * http://local-host/sfPhpunitPlugin/phpunit_coverage.php
     *
     * @var string
     */
    protected $coverageScriptUrl;

    /**
     * @var boolean
     */
    private $collectCodeCoverageInformation;

    /**
     * @var string
     */
    private $testId;

    /**
     * Mink instance.
     *
     * @var     Behat\Mink\Mink
     */
    private static $minkTestCaseMinkInstance;

    /**
     * Initializes mink instance.
     */
    public static function setUpBeforeClass()
    {
        self::$minkTestCaseMinkInstance = new Mink();
        static::registerMinkSessions(self::$minkTestCaseMinkInstance);
    }

    /**
     * Destroys mink instance.
     */
    public static function tearDownAfterClass()
    {
        if (null !== self::$minkTestCaseMinkInstance) {
            self::$minkTestCaseMinkInstance->stopSessions();
            self::$minkTestCaseMinkInstance = null;
        }
    }

    /**
     * Reset started sessions.
     */
    public function tearDown()
    {
        parent::tearDown();
        $this->getMink()->resetSessions();
    }

    /**
     * Returns Mink instance.
     *
     * @return  Behat\Mink\Mink
     */
    public function getMink()
    {
        if (null === self::$minkTestCaseMinkInstance) {
            throw new \RuntimeException(
                'Mink is not initialized. Forgot to call parent context setUpBeforeClass()?'
            );
        }

        return self::$minkTestCaseMinkInstance;
    }

    /**
     * Returns current Mink session.
     *
     * @param   string|null name of the session OR active session will be used
     *
     * @return  Behat\Mink\Session
     */
    public function getSession($name = null)
    {
        return $this->getMink()->getSession($name);
    }

    /**
     * Registers Mink sessions on it's initialization.
     *
     * @param   Behat\Mink\Mink     $mink   Mink manager instance
     */
    protected static function registerMinkSessions(Mink $mink)
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        foreach ($configs['drivers'] as $driver => $options) {
           if (!$mink->hasSession($driver)) {
               $initFn = 'init' . ucfirst($driver) . 'Session';
               $mink->registerSession($driver, static::$initFn());
           }
        }
        $mink->setDefaultSessionName($configs['default_driver']);
    }

    /**
     * Initizalizes and returns new GoutteDriver session.
     *
     * @return  Behat\Mink\Session
     */
    protected static function initGoutteSession()
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        extract($configs['drivers']['goutte']);

        return new Session(new GoutteDriver(new GoutteClient()));
    }

    /**
     * Initizalizes and returns new SahiDriver session.
     *
     * @return  Behat\Mink\Session
     */
    protected static function initSahiSession()
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        extract($configs['drivers']['sahi']);
        return new Session(new SahiDriver($browser, new SahiClient(new SahiConnection($sid, $host, $port))));
    }

    /**
     * Initizalizes and returns new ZombieDriver session.
     *
     * @return  Behat\Mink\Session
     */
    protected static function initZombieSession()
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        extract($configs['drivers']['zombie']);

        $connection = new ZombieConnection($host, $port);
        $server     = $autoServer ? new ZombieServer($host, $port, $nodeBin) : null;

        return new Session(new ZombieDriver($connection, $server, $autoServer));
    }

    /**
     * Initizalizes and returns new Selenium session.
     *
     * @return  Behat\Mink\Session
     */
    protected static function initSeleniumSession()
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        extract($configs['drivers']['selenium']);

        $client = new SeleniumClient($host, $port, $timeout);
        $driver = new SeleniumDriver($browser, $baseUrl, $client);

        return new Session($driver);
    }

    /**
     * Initizalizes and returns new Selenium2Driver session.
     *
     * @return  Behat\Mink\Session
     */
    protected static function initWebdriverSession()
    {
        $configs = sfConfig::get('sf_phpunit_mink');
        extract($configs['drivers']['webdriver']);
        return new Session(new Selenium2Driver($browser, $desiredCapabilities, $host));
    }

    /**
     * {@inheritdoc}
     */
    public function runTest()
    {
        $this->testId = get_class($this) . '__' . $this->getName();
        if ($this->collectCodeCoverageInformation && $this->coverageScriptUrl) {
          $this->getSession()->setCookie('PHPUNIT_SELENIUM_TEST_ID', $this->testId);
        }

        return parent::runTest();
    }

    /**
     * @param PHPUnit_Framework_TestResult $result
     * @return PHPUnit_Framework_TestResult
     */
    public function run(PHPUnit_Framework_TestResult $result = null)
    {
        if ($result === null) {
            $result = $this->createResult();
        }

        $this->collectCodeCoverageInformation = $result->getCollectCodeCoverageInformation();

        parent::run($result);

        if ($this->collectCodeCoverageInformation && $this->coverageScriptUrl) {

            $session = $this->getSession('goutte');

            $url = sprintf(
                '%s?PHPUNIT_SELENIUM_TEST_ID=%s',
                $this->coverageScriptUrl,
                $this->testId
            );

            $session->visit($url);

            $coverage = array();
            if ($content = $session->getPage()->getContent()) {
                $coverage = unserialize($content);
                if (is_array($coverage)) {
                    $coverage = $this->matchLocalAndRemotePaths($coverage);
                } else {
                    throw new Exception('Empty or invalid code coverage data received from url "' . $url . '"');
                }
            }

            $result->getCodeCoverage()->append(
              $coverage,
                $this
            );
        }

        return $result;
    }

    /**
     * @param  array $coverage
     * @return array
     * @author Mattis Stordalen Flister <mattis@xait.no>
     */
    protected function matchLocalAndRemotePaths(array $coverage)
    {
        $coverageWithLocalPaths = array();

        foreach ($coverage as $originalRemotePath => $data) {
            $remotePath = $originalRemotePath;
            $separator  = $this->findDirectorySeparator($remotePath);

            while (!($localpath = stream_resolve_include_path($remotePath)) &&
                strpos($remotePath, $separator) !== false) {
                $remotePath = substr($remotePath, strpos($remotePath, $separator) + 1);
            }

            if ($localpath && md5_file($localpath) == $data['md5']) {
                $coverageWithLocalPaths[$localpath] = $data['coverage'];
            }
        }

        return $coverageWithLocalPaths;
    }

    /**
     * @param  string $path
     * @return string
     * @author Mattis Stordalen Flister <mattis@xait.no>
     */
    protected function findDirectorySeparator($path)
    {
        if (strpos($path, '/') !== false) {
            return '/';
        }

        return '\\';
    }
}
