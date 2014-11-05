<?php

include 'connect.php';


printf("<h1>VXLPAY Analyze domains </h1><br>");
printf("Started at ".$timeStart."<br>");
$timeStart = time();
$crawler = new NewsCrawler( $max, 1000000000000, 3); 
 $crawler->status("Analyzing all domains");
 $crawler->status("For help see, documentation");
 //$crawler->updateDomainAll();
 $crawler->analyzeAll();
 


 //$crawler->topRelevanceDomains(300, $locale);
 //$crawler->exportMysqlToCsv('top_'.$locale, 'top'.$locale);
 

