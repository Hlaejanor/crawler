<?php
header('Content-Type: text/html; charset=utf-8');

const DBServer = 'localhost';
const DBUser = 'root';
const DBPass = 'root';
const DBName = 'RestAssured';

set_time_limit(0);

error_reporting(E_ALL);

include 'commonCrawler.php';

include 'contactCrawler.php';
include 'newsCrawler.php';


$conn = new mysqli(DBServer, DBUser, DBPass, DBName);

?>