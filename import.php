<?php

include 'connect.php';

$timeStart = time();
printf("<h1>VXLPAY import domains from CSV </h1><br>");
printf("Started at ".$timeStart."<br>");
printf("This imports manually assessed relevance from google docs (reading csv) and updates"
        . " the main crawler table<br>");

 $locale = $_GET['locale'];

 if(!($locale)){
     $crawler->status("You must specify locale to import<br>");
     die();
 }
 
 $crawler = new CommonCrawler( 'top_'.$locale);
 $crawler->importCSV(__DIR__ . '/csv/manual_'.$locale.'.csv');
  $crawler->status("Exporting to csv file");
 $crawler->exportMysqlToCsv('top_se', 'se');
 

