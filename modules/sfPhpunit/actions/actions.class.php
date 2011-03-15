<?php
//require_once 'PHPUnit/Framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';
require_once 'PHPUnit/Util/Log/JSON.php';

class sfPhpunitActions extends sfActions implements sfPhpunitFixturePropelAggregator
{

  public function preExecute ()
  {
    if (!sfConfig::get('sf_phpunit_action_access', true) || !Server::isTestEnvironment()) {
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
        $return[] = $this->_readDirectory($path, $subdir . $file . '/');
      } else {
        $return[] = $subdir . $file;
      }
    }
    
    closedir($dir);
    sort($return);
    return $return;
  }
  
  protected function _getFixturesList()
  {
    $return = $this->_readDirectory(sfConfig::get('sf_data_dir') . '/fixtures/');
    
    $return[] = $return[0];
    
    $files = array();
    
    foreach ($return as $key => $val) {
      if (is_array($val) && !empty($val[0]) && 'commObject' == dirname($val[0])) {
        $return[0] = $return[$key];
        unset($return[$key]);
      }
      
      
      if (is_string($val)) {
        $files[] = $val;
        unset($return[$key]);
      }
      
    }
    
    sort($files);
    ksort($return, SORT_STRING);
    $return = array_merge($return, $files);
    
    return $this->_fixturesFlter($return);
    
  }
  
  protected function _fixturesFlter($arr, $shift = 0)
  {
    if (false === $shift) return null;
    if (!is_array($arr[0])) { 
      $name = substr($arr[0], 0, $shift);
      if ('_' == substr($name, 0, 1)) return null;
    }
    foreach ($arr as $key => $val) {
      if (is_string($val) && ('_' == substr($val, $shift, 1) || is_numeric(substr($val, $shift, 3)))) {
        unset($arr[$key]);
      } elseif (is_array($val)) {
        $rArr = $this->_fixturesFlter($arr[$key], (empty($arr[$key][0])?false:strrpos($arr[$key][0], '/')+1));
        if (empty($rArr)) {
          unset($arr[$key]);
        } else {
          $arr[$key] = $rArr;
        }
      }
    }
    if (empty($arr) && !empty($name)) {
      return $name;
    }
    return $arr;
  }
  
  public function executeLoad()
  {
    // No need get coverage from fixture loading because it is too slow and not necessar
    unset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']);
    $listToLoad = array();
    if (!($comm = $this->getRequestParameter('comm'))) {
      $this->redirect($this->getModuleName() . '/index');
    }
    
    
    $fixtureslist = $this->_getFixturesList();
    $comm = explode('-', $comm);
    
    if (empty($fixtureslist[0][$comm[1]])) {
      $this->redirect($this->getModuleName() . '/index');
    }
    $listToLoad[] = $fixtureslist[0][$comm[1]];
    $listToLoad[] = '_users.yml';
    $listToLoad[] = '_admin.yml';
    $listToLoad[] = '_category/';
    
    if ($list = $this->getRequestParameter('list')) {
      $exceptTopKeys = array();
      foreach ((array)$list as $key) {
        $keyArr = explode('-', $key);
        $topKey = $keyArr[0];
        if (in_array($topKey, $exceptTopKeys)) continue;
        $tmpList = $fixtureslist;
        foreach ($keyArr as $index) {
          $tmpList = $tmpList[$index];
        }
        
        if (is_array($tmpList)) {
          //TODO add functional for subSub... directories
          $listToLoad = array_merge($listToLoad, $tmpList);
          $exceptTopKeys[] = $topKey;
        } else {
          $listToLoad[] = $tmpList;
        }
      }
    }
    
    $this->text = $this->_exec($listToLoad, 'fixtures');
    //var_dump($listToLoad);
    
    $this->setTemplate('cc');
  }
  
  public function executeCc()
  {
    VilagoMemcache::getInstance()->flush();
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
    if (empty($fixtures) || !is_array($fixtures)) return false;
    $loader = sfPhpunitFixture::build($this, array('fixture_ext' => ''));
    
    $loader->clean();
    
    
        
    $return = true;
    try {
      foreach ($fixtures as $fixture) {
        $loader->loadSymfony($fixture/*, 'test'*/);
      }
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