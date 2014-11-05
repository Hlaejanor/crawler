<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
printf("<h1>VXLPAY contacts crawler</h1><br>");
printf("Started at ".$timeStart."<br>");
$timeStart = time();

$crawler = new ContactCrawler();

if($crawler->isEnabled()){
    // 1. Estimate the contact score for all table contents and upsert on contact table list
    $crawler -> getAllFromCrawler(10);
    // 2. Download all undownloaded contact pages (which have come by url alone)
    $crawler -> analyzeAll(1000000);
    // 3.
}

?>