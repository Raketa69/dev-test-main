<?php

use App\Database;
use App\DatabaseTest;

require str_replace('\\', '/', __DIR__) . '/autoload.php';

$mysqli = new \MySQLi('mysql', 'root', 'pass', 'test_db', 3306);
if ($mysqli->connect_errno) {
    throw new Exception($mysqli->connect_error);
}

$db = new Database($mysqli);
$test = new DatabaseTest($db);
$test->testBuildQuery();

exit('OK');
