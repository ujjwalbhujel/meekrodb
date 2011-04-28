<?php
function new_error_callback($params) {
  global $error_callback_worked;
  
  if (substr_count($params['error'], 'You have an error in your SQL syntax')) $error_callback_worked = 1;
}

function my_debug_handler($params) {
  global $debug_callback_worked;
  if (substr_count($params['query'], 'SELECT')) $debug_callback_worked = 1;
}


class BasicTest extends SimpleTest {
  function __construct() {
    error_reporting(E_ALL);
    require_once '../db.class.php';
    DB::$user = 'libdb_user';
    DB::$password = 'sdf235sklj';
    DB::$dbName = 'libdb_test';
    DB::query("DROP DATABASE libdb_test");
    DB::query("CREATE DATABASE libdb_test");
    DB::useDB('libdb_test');
  }
  
  
  function test_1_create_table() {
    DB::query("CREATE TABLE `accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
    `username` VARCHAR( 255 ) NOT NULL ,
    `password` VARCHAR( 255 ) NOT NULL ,
    `age` INT NOT NULL DEFAULT '10',
    `height` DOUBLE NOT NULL DEFAULT '10.0'
    ) ENGINE = InnoDB");
  }
  
  function test_1_5_empty_table() {
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(0));
    
    $row = DB::queryFirstRow("SELECT * FROM accounts");
    $this->assert($row === null);
    
    $field = DB::queryFirstField("SELECT * FROM accounts");
    $this->assert($field === null);
    
    $field = DB::queryOneField('nothere', "SELECT * FROM accounts");
    $this->assert($field === null);
    
    $column = DB::queryFirstColumn("SELECT * FROM accounts");
    $this->assert(is_array($column) && count($column) === 0);
    
    $column = DB::queryOneColumn('nothere', "SELECT * FROM accounts"); //TODO: is this what we want?
    $this->assert(is_array($column) && count($column) === 0);
  }
  
  function test_2_insert_row() {
    DB::insert('accounts', array(
      'username' => 'Abe',
      'password' => 'hello'
    ));
    
    $this->assert(DB::affectedRows() === 1);
    
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(1));
  }
  
  function test_3_more_inserts() {
    DB::insert('`accounts`', array(
      'username' => 'Bart',
      'password' => 'hello',
      'age' => 15,
      'height' => 10.371
    ));
    
    DB::insert('`libdb_test`.`accounts`', array(
      'username' => 'Charlie\'s Friend',
      'password' => 'goodbye',
      'age' => 30,
      'height' => 155.23
    ));
    
    $this->assert(DB::insertId() === 3);
    
    $counter = DB::queryFirstField("SELECT COUNT(*) FROM accounts");
    $this->assert($counter === strval(3));
    
    $bart = DB::queryFirstRow("SELECT * FROM accounts WHERE age IN %li AND height IN %ld AND username IN %ls", 
      array(15, 25), array(10.371, 150.123), array('Bart', 'Barts'));
    $this->assert($bart['username'] === 'Bart');
    
    $charlie_password = DB::queryFirstField("SELECT password FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $charlie_password = DB::queryOneField('password', "SELECT * FROM accounts WHERE username IN %ls AND username = %s", 
      array('Charlie', 'Charlie\'s Friend'), 'Charlie\'s Friend');
    $this->assert($charlie_password === 'goodbye');
    
    $passwords = DB::queryFirstColumn("SELECT password FROM accounts WHERE username=%s", 'Bart');
    $this->assert(count($passwords) === 1);
    $this->assert($passwords[0] === 'hello');
    
    $username = $password = $age = null;
    list($age, $username, $password) = DB::queryOneList("SELECT age,username,password FROM accounts WHERE username=%s", 'Bart');
    $this->assert($username === 'Bart');
    $this->assert($password === 'hello');
    $this->assert($age == 15);
  }
  
  function test_4_query() {
    $results = DB::query("SELECT * FROM accounts WHERE username=%s", 'Charlie\'s Friend');
    $this->assert(count($results) === 1);
    $this->assert($results[0]['age'] == 30 && $results[0]['password'] == 'goodbye');
    
    $results = DB::query("SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend");
    $this->assert(count($results) === 2);
    
    $columnlist = DB::columnList('accounts');
    $this->assert(count($columnlist) === 5);
    $this->assert($columnlist[0] === 'id');
    $this->assert($columnlist[4] === 'height');
    
    $tablelist = DB::tableList();
    $this->assert(count($tablelist) === 1);
    $this->assert($tablelist[0] === 'accounts');
    
    $tablelist = null;
    $tablelist = DB::tableList('libdb_test');
    $this->assert(count($tablelist) === 1);
    $this->assert($tablelist[0] === 'accounts');
  }
  
  function test_4_1_query() {
    DB::insert('accounts', array(
      'username' => 'newguy',
      'password' => DB::sqleval("REPEAT('blah', %i)", '3'),
      'age' => DB::sqleval('171+1'),
      'height' => 111.15
    ));
    
    $row = DB::queryOneRow("SELECT * FROM accounts WHERE password=%s", 'blahblahblah');
    $this->assert($row['username'] === 'newguy');
    $this->assert($row['age'] === '172');
    
    DB::update('accounts', array(
      'password' => DB::sqleval("REPEAT('blah', %i)", 4),
      ), 'username=%s', 'newguy');
    
    $row = null;
    $row = DB::queryOneRow("SELECT * FROM accounts WHERE username=%s", 'newguy');
    $this->assert($row['password'] === 'blahblahblahblah');
    
    DB::query("DELETE FROM accounts WHERE password=%s", 'blahblahblahblah');
    $this->assert(DB::affectedRows() === 1);
  }
  
  function test_4_2_delete() {
    DB::insert('accounts', array(
      'username' => 'gonesoon',
      'password' => 'something',
      'age' => 61,
      'height' => 199.194
    ));
    
    $ct = DB::queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', 199.194);
    $this->assert(intval($ct) === 1);
    
    DB::delete('accounts', 'username=%s AND age=%i AND height=%d', 'gonesoon', '61', '199.194');
    $this->assert(DB::affectedRows() === 1);
    
    $ct = DB::queryFirstField("SELECT COUNT(*) FROM accounts WHERE username=%s AND height=%d", 'gonesoon', '199.194');
    $this->assert(intval($ct) === 0);
  }
  
  function test_4_3_insertmany() {
    $ins[] = array(
      'username' => '1ofmany',
      'password' => 'something',
      'age' => 23,
      'height' => 190.194
    );
    $ins[] = array(
      'password' => 'somethingelse',
      'username' => '2ofmany',
      'age' => 25,
      'height' => 190.194
    );
    
    DB::insertMany('accounts', $ins);
    $this->assert(DB::affectedRows() === 2);
    
    $rows = DB::query("SELECT * FROM accounts WHERE height=%d ORDER BY age ASC", 190.194);
    $this->assert(count($rows) === 2);
    $this->assert($rows[0]['username'] === '1ofmany');
    $this->assert($rows[0]['age'] === '23');
    $this->assert($rows[1]['age'] === '25');
    $this->assert($rows[1]['password'] === 'somethingelse');
    $this->assert($rows[1]['username'] === '2ofmany');
    
  }
  
  function test_5_error_handler() {
    global $error_callback_worked, $static_error_callback_worked, $nonstatic_error_callback_worked, 
      $anonymous_error_callback_worked;
    
    DB::$error_handler = 'new_error_callback';
    DB::query("SELET * FROM accounts");
    $this->assert($error_callback_worked === 1);
    
    DB::$error_handler = array('BasicTest', 'static_error_callback');
    DB::query("SELET * FROM accounts");
    $this->assert($static_error_callback_worked === 1);
    
    DB::$error_handler = array($this, 'nonstatic_error_callback');
    DB::query("SELET * FROM accounts");
    $this->assert($nonstatic_error_callback_worked === 1);
    
    DB::$error_handler = function($params) {
      global $anonymous_error_callback_worked;
      if (substr_count($params['error'], 'You have an error in your SQL syntax')) $anonymous_error_callback_worked = 1;
    };
    DB::query("SELET * FROM accounts");
    $this->assert($anonymous_error_callback_worked === 1);
    
  }
  
  public static function static_error_callback($params) {
    global $static_error_callback_worked;
    if (substr_count($params['error'], 'You have an error in your SQL syntax')) $static_error_callback_worked = 1;
  }
  
  public function nonstatic_error_callback($params) {
    global $nonstatic_error_callback_worked;
    if (substr_count($params['error'], 'You have an error in your SQL syntax')) $nonstatic_error_callback_worked = 1;
  }
  
  function test_6_exception_catch() {
    DB::$error_handler = '';
    DB::$throw_exception_on_error = true;
    try {
      DB::query("SELET * FROM accounts");
    } catch(MeekroDBException $e) {
      $this->assert(substr_count($e->getMessage(), 'You have an error in your SQL syntax'));
      $this->assert($e->getQuery() === 'SELET * FROM accounts');
      $exception_was_caught = 1;
    }
    $this->assert($exception_was_caught === 1);
    
    try {
      DB::insert('`libdb_test`.`accounts`', array(
        'id' => 2,
        'username' => 'Another Dude\'s \'Mom"',
        'password' => 'asdfsdse',
        'age' => 35,
        'height' => 555.23
      ));
    } catch(MeekroDBException $e) {
      $this->assert(substr_count($e->getMessage(), 'Duplicate entry'));
      $exception_was_caught = 2;
    }
    $this->assert($exception_was_caught === 2);
  }
  
  function test_7_debugmode_handler() {
    global $debug_callback_worked;
    
    DB::debugMode('my_debug_handler');
    DB::query("SELECT * FROM accounts WHERE username!=%s", "Charlie's Friend");
    
    $this->assert($debug_callback_worked === 1);
    
    DB::debugMode(false);
  }

}


?>
