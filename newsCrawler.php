<?php

class NewsCrawler extends CommonCrawler{
// Disable time limit to keep the script running

public $analyzed;
public $newLinks;
public $newDomains;
public $conn;
public $crawlMax;
public $downloadMax;

public $analyzeTime;

public $crawlTime;
public $newDomainTime;

function __construct($downloadMax, $crawlMax, $sameSiteDeflator){
  global $conn;
  parent::__construct('crawler');
$this->crawlerName = 'newsCrawler';
$this->downloaded = 0;
$this->analyzed = 0;
$this->newLinks = 0;
$this->newDomains = 0;
$this->crawlMax  = $crawlMax;
$this->downloadMax = $downloadMax;
$this->sameSiteDeflator = $sameSiteDeflator;
    if (!$conn) {
        //   Control::error('getConn()', 'Error connecting to DB ('.$cloud.')', mysqli_connect_error());
        print "Connect error " . mysqli_connect_error();
        die(mysqli_connect_error());
    } else {
        //  Control::log('Control::getConn)=(', 'Actually logged in', 'yay');
    }
}


function extractLinks($domain, $subDomain, $url, $content, $inheritedRelevance, $distance){
    $content_tag = "body";
  
    $rounds = 0;
    // Array to hold all domains to check
  
   
    $this->out( "Extracting links from $url");

    // Reset the relevance indicators     
    // Loop through each found tag of the specified type in the dom
    // and search for links.
    // Store the links
    // Maximum size of domain stack
    // Loop through each "a"-tag in the dom
    // and add its href domain to the domain stack if it is not an internal link
  
 
    
    if(  stripos($content, '{') === 0 ){
      
        $this->out("This is a google API result, using JSON parse method");
      
        $googleObject = json_decode(utf8_encode($content));
        if(json_last_error()!= 0){
            $this->out('Error number :'.json_last_error());
            return false;
        }
        $i = 0;
        $j = 0;
        $n = 0;
        if($googleObject){
          
          
            if(!isset($googleObject->responseData)){
                // This is usually the result of Quota Exceeded
                $this->status( 'Quota exceeded for google stuff');
              
                return true;
            }
            
            
            foreach ($googleObject->responseData->results as $key => $value){
                $n++;
              // $this->out('Google result : '.$key.' '.$value);
                $i += $this->writeLink($domain, $subDomain, $value->unescapedUrl, $inheritedRelevance, $distance +1, $value->title);
            }
            $this->status("Processed $n links, added $i new");
            $this->newLinks += $i;
            return true;
        }
        else{
            $this->out('failed to parse json data for '.$domain);
           
            $json_errors = array([
               'No error has occurred',
               'The maximum stack depth has been exceeded',
               'JSON_ERROR_STATE_MISMATCH',
               'Control character error, possibly incorrectly encoded',
               'Syntax error',
               'UTF8 Encoding issue']
            );
            $this->status('Error number :'.json_last_error());
            $this->status( 'Last error : ', $json_errors[json_last_error()], PHP_EOL, PHP_EOL);
        }
        
        return true;
    }
    
    $doc = new DOMDocument();
    
    // Get the sourcecode of the domain
    @$doc->loadHTML($content);
    $n = 0;
    $i = 0;
    foreach ($doc->getElementsByTagName('a') as $link) {
       
        $href = $link->getAttribute('href');
        $this->out($href. 'Found link');
        // this is likely a json result
        $n++;
        // Downloaded firstpage
        if (stripos($href, 'http://') === false) {
            // Same site link or broken link
            // Attempt to add the domain to it.
           // $href_array = explode("/", $href);
            $href = ltrim($href, '.');
            $this->out('Adding http:// to internal link on '.$domain);
            $i += $this->writeLink(rtrim($domain, '/'), rtrim($domain, '/'), "http://" .rtrim($url, '/').$href, $inheritedRelevance, $distance);
        }
        else{
            $i += $this->writeLink($domain, $subDomain, $href, $inheritedRelevance/2, $distance);
        }
      $i++;
    }
    $this->status("Processed $n links, added $i new");
    
    $this->newLinks += $i;
    return $this->isCrawled($url);
    
}

// This adds points to domains depending on words in their domain name.
function domainWeight($domain, $url) {
    $locale = substr($domain, strrpos($domain, '.') + 1);
    $needle = $this->getQueries($locale);
    $points = 0;
    


    foreach ($needle as $query) {
        if (stripos($url, $query) !== false) {
            $points += 10;
        }
    }

    return $points;
}

function writeLink($domain, $subDomain, $URL, $inheritedRelevance, $distance,  $title = '') {
    global $conn;
    $subDomain = parse_url($URL, PHP_URL_HOST);
    if(!$domain){
        $subDomain = substr($URL, stripos($URL, 'http://'));
        $subDomain = parse_url($subDomain, PHP_URL_HOST);
        if(!$subDomain){
            if(stripos($URL, '/') === false){
                $subDomain = $URL;
            }
            else{
                $this->deleteURL($URL);
            }
        }
    }
    $domain = $this->getDomain($URL);
    $locale = $this->getLocale($domain);
    $needle = $this->getQueries($locale);
    $urlRelevance = $this->filter($URL, $needle);
    $sql = "INSERT INTO CRAWLER "
            . "(domain, subDomain, URL,"
            . " relevance, inheritedRelevance, urlRelevance, "
            . "title , distance, locale, "
            . "timestamp) "
            . "values(?,?, ?,  ?, ?, ?, ?, ?, ?, ?)";
 
    $stmt = $conn->prepare($sql);
    if ($stmt == false) {
        print 'Error in sql<br>';
    }
    $time = time();
  
    $stmt->bind_param('sssiiisisi', 
            $domain, $subDomain, $URL, 
            $relevance, $inheritedRelevance, $urlRelevance, 
            $title, $distance, $locale, 
            $time);
   
    $stmt->execute();
    if ($stmt->errno > 0 ) {
        switch ($stmt->errno){
            case 1062:
                  $this->out( "Already existing : $URL <br>");
                  return 0;
                break;
            default:
                $this->out($stmt->error);
                return $stmt->affected_rows;
            break;
        }
    }
    $this->out( "Stored : $URL ");
    if($domain == $URL){
        $this->newDomains += $stmt->affected_rows;
    }
    return  $stmt->affected_rows;
}

/*
 *  
 */

function isAnalyzed($domain, $URL,  $locale, $relevance, $contains, $title = '') {
    global $conn;
    $sql = "UPDATE CRAWLER SET title = ?, domain = ?,  isAnalyzed = 1, relevance =  ?, contains = ?, locale = ? WHERE URL = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
           $this->out('Error preparing SQL statement ');
           die('prepare() failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param('ssisss',$title, $domain, $relevance, $contains, $locale,  $URL );
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->errno > 0) {
        print $stmt->error;
    }
    
    $this->out("Analyzed $URL with result $relevance");
    return $stmt->affected_rows;
}

/*
 * IsCrawled 
 */

function isCrawled($URL) {
    global $conn;
    $sql = "UPDATE CRAWLER SET isCrawled = 1 WHERE URL = ?";
    $stmt = $conn->prepare($sql);
    if($stmt === false){
         die(mysqli_connect_error());
    }
    $stmt->bind_param('s', $URL);
    $stmt->execute();
    if ($stmt->errno > 0) {
        print $stmt->error;
    }
    $this->out("Finished crawling $URL<br>");
    return true;
}

/*
 * Store a cron entry job.
 */

function buildArray() {
    global $conn;
    $crawled = 0;
    $sql = "SELECT URL, weight from CRAWLER WHERE isCrawled = ? Order by contains";

    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        print 'Error in sql';
    }
    $stmt->bind_param('i', $crawled);
    $stmt->bind_result($URL, $weight);
    if (!$stmt->execute()) {
        print 'Failed execution';
        error_log("Didn't work");
    }
    $array = false;
    while ($stmt->fetch()) {
        $array['URL'][] = $URL;
        $array['weight'][] = $weight;
    }
    if (!$array) {
        return array();
    }
    return $array;
}

/*
 * Store a cron entry job
 */


/*
 * GetQueries 
 */

function getQueries($locale){
    $needle = array();
   
     switch ($locale) {
        case 'no' :
            $needle[] = 'nyheter';
            $needle[] = 'aktuelt';
            $needle[] = 'abonner';
            $needle[] = 'annonsér';
            $needle[] = 'annonser';
            $needle[] = 'siste nytt';
            $needle[] = 'journalist';
            break;
        case 'se' :
            $needle[] = 'nyheter';
            $needle[] = 'tidning';
            $needle[] = 'tidningen';
            $needle[] = 'allehanda';
            $needle[] = 'aktuelt';
            $needle[] = 'prenumer';
            $needle[] = 'annonsera';
            $needle[] = 'senaste nytt';
            $needle[] = 'journalist';
            $needle[] = 'redaktion';
            $needle[] = 'utgivare';
            
            break;
        case 'de':
            $needle[] = 'nachricten';
            break;
        case 'es':
            $needle[] = 'noticias';
            $needle[] = 'notícies';

            break;
        case 'pl':
            $needle[] = 'aktualności';
            break;
        case 'sa':
            $needle[] = 'nuus';
          
            break;
        case 'fr':
            $needle[] = 'nouvelles';
            break;
        case 'dk':
            $needle[] = 'nyheder';
            $needle[] = 'aktuelt';
            $needle[] = 'seneste nyt';
            $needle[] = 'siste nyt';
            $needle[] = 'annoncer';
            $needle[] = 'journalist';
            break;
        case 'fi':
            $needle[] = 'uutiset';
            break;
        case 'cz':
            $needle[] = 'novinky';
            break;
        case 'ru':
            $needle[] = 'новости';
            break;
        case 'is':
            $needle[] = 'fréttir';
            break;
        case 'gr':
            $needle[] = 'Ειδήσεις';
            break;
        case 'lv':
            $needle[] = 'ziņas';
            break;
        case 'lt':
            $needle[] = 'naujienos';
            break;
        default:
            $needle[] = 'news';
            $needle[] = 'sport';
            $needle[] = 'finance';
            $needle[] = 'markets';
            $needle[] = 'travel';
            $needle[] = 'fashion';
            $needle[] = 'fashion';
            $needle[] = 'follow';
            break;
    }
    return $needle;
}
/*
 * Crawl the next 100 sites, prioritize the 
 */
function crawlAll(){
    global $conn;
    $timeStart = time();
    $this->out('<h3>Crawling new URLs</h3>');
    $sql = "SELECT domain, subDomain, URL, distance, relevance, inheritedRelevance, content from CRAWLER WHERE "
            . "isCrawled = 0 and isDownloaded > 0 AND distance < 34 ORDER BY relevance DESC LIMIT ?";
    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        print 'Error in sql';
    }
    $stmt->bind_param('i', $this->crawlMax);
    $stmt->bind_result( $domain, $subDomain, $URL, $distance, $relevance, $inheritedRelevance, $content);
    
    if (!$stmt->execute()) {
        print 'Failed execution';
        error_log("Didn't work");
    }
 
    
    $i = 0;
    $stmt->store_result();
    while ($stmt->fetch()) {
       
        if($this->extractLinks($domain, $subDomain, $URL, $content, $relevance+$inheritedRelevance, $distance)){
             $i++;
             $this->isCrawled($URL);
        }
        else{
            $this->out('Failed to extract links from '.$URL);
        }
       
    }
  
    $this->status("Extracted links from ".$i. " out of ".$stmt->num_rows." number of rows");
    $this->crawlTime = time()-$timeStart;
    $this->status('Finished crawling pages for new links '.$this->crawlTime.' seconds');
}
/*
 * generalized curl call
 */
function curl($url){

        
        $cURL = curl_init();
        curl_setopt($cURL, CURLOPT_URL, $url);
        
        curl_setopt($cURL, CURLOPT_HTTPGET, true);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cURL, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($cURL, CURLOPT_REFERER, 'http://www.vxlpay.com');

        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json'
        ));

        $result = curl_exec($cURL);
        return utf8_decode($result);
    
}

/*
 * Downloads the content for an URL
 */

function download($URL){
    global $conn;
    
    if(!$conn){
        $this->out( 'Connection failed');
    }
    $this->out('Attempting to download '.$URL);
    
    $content = $this->curl($URL);
    
    if($content === false){
        $content = 'failed to load';
    }
    
    $sql = "UPDATE CRAWLER SET  content = ?, isDownloaded = 1 where URL = ?";
    try{
     
        $stmt4 = $conn->prepare($sql);
        if ($stmt4->errno) {
           $this->out('Error preparing SQL statement ');
        }
   
        $stmt4->bind_param('ss',  $content, $URL);

        $stmt4->execute();

        $stmt4->store_result();
        // print "Updated the ".$this->id.", affected rows ".$stmt4->affected_rows." balance now ".$this->balance;
        if ($stmt4->errno) {
            $this->out('Unable to persist the result: ');
            $stmt4->close();
            return false;
            
        }
        
        $this->downloaded ++;
        
        return true;
    
    } catch (mysqli_sql_exception $e) { 
         echo $e->errorMessage(); 
    }
   
}

/*
 * Downloads the content for non-downloaded domains
 */

function getAllDomains(){
    global $conn;
    $this->timeStart = time();
    $this->status('Downloading new domains');
    $sql = "SELECT domain, subDomain, URL, inheritedRelevance from CRAWLER "
            . "WHERE isDownloaded = 0 "
            ." HAVING subDomain = URL "
            . "ORDER BY inheritedRelevance DESC "
            . "LIMIT ?";
    
    $stmt = $conn->stmt_init();
   
    $stmt = $conn->prepare($sql);
      $stmt->bind_param('i', $this->downloadMax);
    if ($stmt === false) {
        print 'Error in sql'. mysqli_error($conn);
        die();
    }
   
    $stmt->bind_result($domain, $subDomain, $URL, $inheritedRelevance);
    if (!$stmt->execute()) {
        print 'Failed execution';
      
    }
    $stmt->store_result();
    $i = 0;
    $this->status("Retrieved $stmt->num_rows new document to download");
    while ($stmt->fetch()) {
        $i += $this->download($URL);
        //  $this->out('Downloaded '.$URL);
    }
    $this->status("Downloaded content for $i new domains");
    $this->status("Download took ".(time() - $this->timeStart)." seconds");
    
    return $stmt->num_rows;
}
function removeDoubleSlashes(){
    global $conn;
   
    $this->out('Removing double slashes from links');
    $sql = "SELECT URL from CRAWLER ";
    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        print 'Error in sql'. mysqli_error($conn);
        die();
    }
   
    $stmt->bind_result($URL);
    if (!$stmt->execute()) {
        print 'Failed execution';
      
    }
    $stmt->store_result();
    
    while ($stmt->fetch()) {
        if(stripos($URL, '//' ,8) === false){
           
        }
        else{
            $newURL = str_replace('https:/', 'https://', str_Replace('http:/', 'http://', str_replace('//', '/', $URL)));
            $this->updateURL($newURL, $URL);
        }
      
    }
    
    return $stmt->num_rows;
}
function getAllOtherLinks(){
    global $conn;
    $timeStart = time();
    $this->status('<h3>Downloading other links looking for links</h3>');
    $sql = "SELECT domain, URL, inheritedRelevance from CRAWLER WHERE isDownloaded = 0 "
            . "ORDER BY relevance DESC LIMIT ?";
    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        print 'Error in sql'. mysqli_error($conn);
        die();
    }
    $stmt->bind_param('i', $this->downloadMax);
    $stmt->bind_result($domain, $URL, $inheritedRelevance);
    if (!$stmt->execute()) {
        print 'Failed execution';
      
    }
    $stmt->store_result();
    
    while ($stmt->fetch()) {
        $this->download($URL);
        $this->out('Downloaded '.$URL);
    }
    $this->status("Finished downloading content for ".$stmt->num_rows." new links");
   
    $this->status('Operation took'. time()- $timeStart.' seconds');
}

/*
 * Loops through all unanalyzed domains with content, and analyzes their
 * likelyhood of being a newssite.
 */
function analyzeAll(){
    
    global $conn;
    
    $timeStart = time();
    $this->out('<h3>Analyzing new sites</h3>');
    $sql = "SELECT domain, URL, content from CRAWLER WHERE isAnalyzed < 1 AND isDownloaded >0";
    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        print 'Error in sql';
    }
    
    $stmt->bind_result($domain, $URL, $content);
    if (!$stmt->execute()) {
        print 'Failed execution';
        error_log("Didn't work");
    }
    
    $stmt->store_result();
    $array = false;
    $n = 0;
    $i = 0;
    $this->status("Retrieved $stmt->num_rows new document to analyze");
    while ($stmt->fetch()) {
        $n ++;
        $locale = $this->getLocale($domain);
        $relevance = $this->domainWeight($domain, $URL);
        $response = $this->assessRelevance($domain, $URL, $content);
        $relevance += intval($response['relevance']);
        $i += $this->isAnalyzed($domain, $URL, $locale, $relevance, $response['contains']);
    }
    $this->status("Processed $n, analyzed $i new URLs");
    $this->analyzed += $i;
    $this->analyzeTime = time()- $timeStart;
    $this->status('Finished analyzing content relevance in '.$this->analyzeTime.' seconds');
}
/*
 * Searches the document for words which indicate that the site is
 * a news site. Using words releavant to the country code in which the 
 * domain name belongs
 */
function assessRelevance($domain, $URL, $content){
       
        //$doc = new DOMDocument();
        $contains = '';
        // Get the sourcecode of the domain
        //   @$doc->loadHTMLFile($content);
     
        //$nodes = $doc->getElementsByTagName('title');
        //$title = $nodes->item(0)->nodeValue;
        
        $found = false;
        $this->out( "Analyzing $URL<br>");
        
        // Reset the relevance indicators     
        // Loop through each found tag of the specified type in the dom
        // and search for the specified content
        $locale = substr($domain, strrpos($domain, '.') + 1);
        $needle = $this->getQueries($locale);
        $relevance = 0;
        foreach ($needle as $query) {
           if (stripos($content, $query)){
               $relevance ++;
               $contains .= $query.';';
           }
        }
        $this->out('Assessed '.$URL.' to on locale '.$locale.' to have '.$relevance.' relevance because it contains ('.$contains.')');
        return array('relevance' => $relevance, 'contains' =>$contains);
}

function out($msg){
    
   // print $msg.'<br>';
   
}
function status($msg){
    
    print $msg.'<br>';
   
}
// Write found domains string to specified output file
// To schedule cron 
// cd bruker, cd library, cd LaunchAgents
// 
// 
// brukers-MacBook-Pro:launchAgents bruker$ vim com.vxl.crawler.plist
// usr/
// to remove cron
/*
  <?xml version="1.0" encoding="UTF-8"?>
  <!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
  <plist version="1.0">
  <dict>
  <key>Label</key>
  <string>com.vxl.crawler</string>

  <key>ProgramArguments</key>
  <array>
  <string>/usr/bin/curl</string>
  <string>--silent</string>
  <string>--compressed</string>
  <string>http://www.ra.com/crawler.php?cron=true</string>
  </array>

  <key>RunAtLoad</key>
  <true/>

  <key>Nice</key>
  <integer>1</integer>

  <key>StartInterval</key>
  <integer>60</integer>

  <key>StandardErrorPath</key>
  <string>/Users/bruker/vxl/crawler.err</string>

  <key>StandardOutPath</key>
  <string>/Users/bruker/vxl/crawler.err</string>
  </dict>
  </plist>
 */

/*
 * Deletes all the search
 * 
 */
function truncateAllButOne(){
    global $conn;
    $keep = 'www.onlineaviser.no';
    $sql = "DELETE FROM CRAWLER where URL NOT IN (?)";
    $stmt = $conn->prepare($sql);
    if($stmt === false){
         die(mysqli_connect_error());
    }
    $stmt->bind_param('s', $keep);
    $stmt->execute();
    if ($stmt->errno > 0) {
        print $stmt->error;
    }
    print "Deleted the crawl $URL<br>";
    return true;
}

/*
 * Add google search terms for used query the api.
 * Combines cityName with the localiszed word for 'news'
 */

function addGoogleSearchTerms(){
    global $conn;
    $countries = array();
  
    
    $sql = "SELECT countryCode, cityName FROM Cities";

    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        print 'Error in sql';
    }
 
    $stmt->bind_result($countryCode, $cityName);
    if (!$stmt->execute()) {
        print 'Failed execution';
        error_log("Didn't work");
    }
    $array = false;
    $stmt->store_result();
    $domain = "googleapis.com";
    $subDomain = "ajax.googleapis.com";
    while ($stmt->fetch()) {
       
        $needle = $this->getQueries($countryCode);
        foreach ($needle as $query) {
            
           $StrLen = strlen($query);
           $FullStrEnd = substr($FullStr, strlen($FullStr) - $StrLen);
           if($FullStrEnd == 's'){
         //     $url =  'https://duckduckgo.com/d.js?q='.rtrim($cityName, 's').'%20'.$query.'&l=us-en&p=1&s=0';
              $url = 'http://www.google.'.$countryCode.'/#q='.$query.'+'.rtrim($cityName, 's');
              $this-> writeLink($domain, $subDomain, $url, 5, 0); 

           }
          
           $url = 'https://ajax.googleapis.com/ajax/services/search/web?v=1.0&q='.$query.'%20'.$cityName;
           $this-> writeLink($domain, $subDomain,  $url, 5, 0); 
        }
        
    }
}

// Isolate the domains that are linked to but that where the frontpage is not nescessarily read.
// This is to improve the new-site seeking characteristcs of the crawler.

function createNewDomainList(){
    global $conn;
    $timeStart = time();
    
    $sql =    "SELECT domain, subDomain,  avg(relevance), avg(inheritedRelevance), avg(distance), URL from CRAWLER "
            . "WHERE domain > ''"
            . "AND timestamp > $this->startTime "
            . "GROUP BY domain";
 
    $this->out('<h3>Generating new domains</h3>');
    $stmt = $conn->stmt_init();
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        print 'Error in sql';
    }
    
    $stmt->bind_result($domain, $subDomain, $relevance, $inheritedRelevance , $distance, $URL);
    
    
    if (!$stmt->execute()) {
        print 'Failed execution';
        error_log("Didn't work");
    }
    $array = false;
    $stmt->store_result();
    $i = 0;
    while ($stmt->fetch()) {
         $i += $this->writeLink($domain, $subDomain, $subDomain, ($relevance+$inheritedRelevance)/$distance , $distance);
    }
    $this->newDomainTime = time() - $timeStart; 
    $this->status("Added $i new domains to list");
    $this->status('Finished updating domains list in '.$this->newDomainTime.' seconds');
}


function updateURL($newURL, $URL) {
    global $conn;
    $sql = "UPDATE CRAWLER SET URL = ? WHERE URL = ?";
    $stmt = $conn->prepare($sql);
   
    if ($stmt === false) {
           $this->out('Error preparing SQL statement ');
           die('prepare() failed: ' . htmlspecialchars($conn->error));
    }
    
    $stmt->bind_param('ss',$newURL, $URL);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->errno > 0) {
        print $stmt->error;
    }
     $this->out( "Changed $URL to $newURL");
    
    return true;
}


}



?>