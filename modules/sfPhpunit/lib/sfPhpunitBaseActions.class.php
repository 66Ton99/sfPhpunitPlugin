<?php

abstract class sfPhpunitBaseActions extends sfActions
{

  public function preExecute ()
  {
    if (!sfConfig::get('sf_phpunit_action_access', true)) {
      sfContext::getInstance()->getController()->forward(sfConfig::get('sf_phpunit_action_module', 'default'), 
                                                         sfConfig::get('sf_phpunit_action_action', 'error404'));
      exit();
    }
    $this->setLayout(false);
  }

  public function executeIndex ()
  {
//    $loader = new sfPhpunitProjectTestLoader('unit/*');
//    $loader->load();
    $tree = array();
    /*$added_tests = array();
    foreach ($this->_getTests($loader->suite()) as $test) {
      $reflection = new ReflectionClass($test);
      $test = $reflection->getFileName();
      $test = substr($test, strpos($test, 'phpunit/') + 8, strlen($test));
      $path = dirname($reflection->getFileName());
      $path = substr($path, strpos($path, 'phpunit/') + 8, strlen($path));
      $path = implode('"]["', explode('/', $path));
      if (! in_array($test, $added_tests)) {
        eval("\$tree[\"{$path}\"][] = \$test;");
        $added_tests[] = $test;
      }
    }*/
    $this->tree = $tree;
    
    
    $this->fixtureslist = $this->_getFixturesList();
  }

  public function executeRun ()
  {
    set_time_limit(0);
    $buffer = tempnam(sys_get_temp_dir(), 'phpunit');
    $listener = new PHPUnit_Util_Log_JSON($buffer);
    $testResult = new PHPUnit_Framework_TestResult();
    $testResult->addListener($listener);
    $path = str_replace('-', '/', $this->getRequestParameter('test'));
    $loader = new sfPhpunitProjectTestLoader($path);
    $loader->load();
    $loader->suite()->run($testResult);
    $result = '[' . str_replace('}{', '},{', file_get_contents($buffer)) . ']';
    $tests = array();
    foreach (json_decode($result) as $test) {
      if ('suiteStart' == $test->event)
        continue;
      if (! isset($tests[$test->suite])) {
        $tests[$test->suite]['methods'] = array();
        $tests[$test->suite]['status'] = 'pass';
      }
      $tests[$test->suite]['methods'][] = $test;
      if ('pass' != $test->status) {
        $tests[$test->suite]['status'] = 'fail';
      }
    }
    $this->result = $testResult;
    $this->tests = $tests;
    $this->path = $path;
  }

  protected function _getTests (PHPUnit_Framework_TestSuite $suite)
  {
    $tests = array();
    foreach ($suite->tests() as $test) {
      if ($test instanceof PHPUnit_Framework_TestSuite) {
        $tests = array_merge($tests, $this->_getTests($test));
      } else {
        $tests[] = $test;
      }
    }
    return $tests;
  }
  
  protected function _exec($command, $type = '')
  {
    switch ($type) {
      case 'custom':
        $return = self::exec($command);
        break;
      case 'fixtures':
        $return = $this->loadFixture($command, true);
        break;
      default:
        $return = self::exec('./symfony ' . $command);
    }
    return nl2br($return) . "<hr />";
  }
  
  protected function _readDirectory($path, $subdir = '')
  {
    if (!is_dir($path . $subdir)) return array($path . $subdir);
    $return = array();
    
    $dir = opendir($path . $subdir);
    
    while (false !== ($file = readdir($dir))) {
      if (in_array($file, array('.', '..', '.svn', 'phpunit'))) continue;
      if (is_dir($path . $subdir . $file)) {
        $tmpKey =  $subdir . $file;
        $return[$tmpKey] = $this->_readDirectory($path, $tmpKey . '/');
      } else {
        $return[] = $file;
      }
    }
    
    closedir($dir);
    return $return;
  }
  
  protected function _getFixturesList()
  {   
    return $this->_readDirectory(sfConfig::get('sf_data_dir') . '/fixtures/');
  }
  
  public function executeLoad()
  {
    $this->setTemplate('cc');
    // No need get coverage from fixture loading because it is too slow and not necessary
    unset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']);
    
    if ($list = $this->getRequestParameter('list')) {
      $this->text = $this->_exec($list, 'fixtures');
    } else {
      $this->text = 'Please select something';
      return;
    }
    
    
  }
  
  public function executeCc()
  {
    $path = sfConfig::get('sf_cache_dir');
    if (glob($path)) {
      @sfToolkit::clearGlob($path);
    }
    $this->text = "Done";
  }
  
/**
   * Load fintures
   * 
   * @param array|string $fixteres
   * @return bool|string - true if all OK OR error message if somthing wrong
   */
  public function loadFixture($fixtures = '', $returnText = false)
  {
    $loader = sfPhpunitFixture::build($this, array('fixture_ext' => ''));
    
    $loader->clean();
    
    
    
    $return = true;
    try {
      $loader->loadSymfony($fixtures/*, 'test'*/);
    } catch (Exception $e) {
      $return = $e->getMessage();
    }
    return $returnText&&true===$return?'Fixtures loaded successful.':$return;
  }
  
  /**
   * Execution command
   * 
   * @param string $command 
   * @return string - (message) after exec command
   */
  public static function exec($command)
  {
    $orDir = getcwd();
    chdir(sfConfig::get('sf_root_dir'));
    $return = shell_exec($command);
    chdir($orDir);
    return $command . "\n" . $return;
  }
  
  public function getPackageFixtureDir() {}
  
  public function getOwnFixtureDir() {}
  
  public function getCommonFixtureDir() {}
  
  public function getSymfonyFixtureDir()
  {
    $path = array(sfConfig::get('sf_data_dir'), 'fixtures');
    
    return implode(DIRECTORY_SEPARATOR, $path);
  }
  
  
}