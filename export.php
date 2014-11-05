<?php

include 'connect.php';

$timeStart = time();
printf("<h1>VXLPAY export domains to CSV </h1><br>");
printf("Started at ".$timeStart."<br>");
printf("This  exports
    new lists of top relevance domains<br>");


 $locale = $_GET['locale'];
 $max = $_GET['max'];
 
 if(!($max && $locale)){
     $crawler->status("You must specify locale and max number of rows to output");
     die();
 }
 

 $crawler = new CommonCrawler( 'top_'.$locale);
 
 $crawler->status("Exporting top $max domains for locale $locale<br>");
 
 $crawler->status("For help see, documentation<br>");

 //$crawler->updateDomainAll();
 $crawler->topRelevanceDomains(300, $locale);
 $crawler->status("Exporting to csv file");
 $crawler->exportMysqlToCsv('top_'.$locale, $locale);
 

