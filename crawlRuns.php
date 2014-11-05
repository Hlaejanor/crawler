<?php

include 'connect.php';


printf("<h1>VXLPAY List of Crawl jobs </h1><br>");
printf("Started at ".$timeStart."<br>");
 $timeStart = time();
 $crawler = new CommonCrawler( 'crawler'); 
 $crawler->status("Printing list of runs");
 
 $crawler->getCrawlList();
