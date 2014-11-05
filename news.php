<?php

include 'connect.php';


printf("<h1>VXLPAY news crawler</h1><br>");

printf("Started at " . date('d.m.Y - H:i:s', time())."<br>");
$timeStart = time();

$crawler = new NewsCrawler(300, 300, 3);
$crawler->status("Search spider looking for news sites in many langugages");
$crawler->status("For help see, documentation");
$i = 0;
$max = 1;
//for($i = 0 ; $i < $max; $i++){
$crawler->status('Using ' . $crawler->memoryUsage() . ' amount of memory');

//$crawler -> truncateAllButOne();
//$crawler ->addGoogleSearchTerms();
//$crawler->fixAllDomain();

if($crawler->isEnabled()){
    
$crawler->startDownload();
$downloaded = $crawler->getAllDomains();
if ( $downloaded < $crawler->downloadMax) {
 
    $crawler->getAllOtherLinks($crawler->downloadMax-$downloaded);
};


$crawler->endDownload();


$crawler->analyzeAll(); // Analyze all where isDownloaded = 1 and isAnalyzed = 0;

$crawler->crawlAll(); // Crawl all where isCrawled = 0 and isDownloaded 1;

$crawler->createNewDomainList();    // Add new domains to list in cases where not root URL is known.
flush();
}

$crawler->status("Total of " . $crawler->timeSpent() . " seconds<br>");

unset($crawler);
/*
  }
  else{
       print ( "Ending crawler seconds after ".time()-$timeStart." seconds<br>");
       unset($crawler);
  }
//}*/