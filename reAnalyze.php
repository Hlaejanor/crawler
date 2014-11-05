<?php

include 'connect.php';


printf("<h1>VXLPAY reAnalyze domains</h1><br>");

printf("Started at " . date('d.m.Y - H:i:s', time())."<br>");
$timeStart = time();

$crawler = new CommonCrawler('crawler');
$crawler->status("Reanalyzing , adding subdomains and pruning the resultset");
$crawler->status("For help see, documentation");
$i = 0;
$max = 1;
//for($i = 0 ; $i < $max; $i++){
$crawler->out('Using ' . $crawler->memoryUsage() . ' amount of memory');
// if((time() - $timeStart) < 2800){

//$crawler -> truncateAllButOne();
//$crawler ->addGoogleSearchTerms();


$crawler -> updateDomainAll();


$crawler->status("Total of " . $crawler->timeSpent() . " seconds<br>");

unset($crawler);
/*
  }
  else{
       print ( "Ending crawler seconds after ".time()-$timeStart." seconds<br>");
       unset($crawler);
  }
//}*/