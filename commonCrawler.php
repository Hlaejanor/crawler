<?php

/*
 * CLASS COMMONCRAWLER
 * The commoncrawler contains base methods for inserting and updating 
 * The class is designed to be extended by more specific classes used for specific purposes.
 *  
 */

class CommonCrawler {

    public $tableName;
    public $downloaded;
    public $analyzed;
    public $contains;
    public $relevance;
    public $urlRelevance;
    public $startTime;
    public $endTime;
    public $totalTime;
    public $downloadStart;
    public $crawlerName;
    public $enabled;
    function __construct($tableName) {
        global $conn;
        $this->crawlerName = 'common';
        $this->tableName = $tableName;
        $this->downloaded = 0;
        $this->analyzed = 0;
        $this->crawled = 0;
        $this->enabled = 0;
        
        $this->startTime = time() - 1;    // To avoid sub second conditionals on timestamp.
        if (!$conn) {
            //   Control::error('getConn()', 'Error connecting to DB ('.$cloud.')', mysqli_connect_error());
            print "Connect error " . mysqli_connect_error();
            die('Die connection error ' . mysqli_connect_error());
        } else {
            
        }
       
        $sql = "SELECT crawlerName, enabled from crawlControl WHERE crawlerName = ?";

        // Get the language of the site

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $this->crawlerName);
        $stmt->bind_result($this->crawlerName, $this->enabled);
        $stmt->execute();
        $stmt->store_result();
        $stmt->fetch();
        
    }
    function isEnabled(){
        return $this->enabled;
    }
    function startDownload() {
        $this->status('Starting download of URLs');
        $this->downloadStart = time();
        return $this->downloadStart;
    }

    function endDownload() {
        $this->status('Finished downloading URLs');
        $this->downloadTime = time() - $this->downloadStart;
        return $this->downloadTime;
    }

    /**
     * GETDOMAIN
     * 
     * Removes subdomains and URL segment of URLs. 
     */
    function getDomain($subDomain){
        $arr = explode('.', $subDomain);
        if(count($arr)>2){
            $removed = array_shift($arr);
        }
        return implode('.',$arr);
    }
    
    function getDomain2($url, $debug = false) {
        $original = $domain = strtolower($URL);

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }

        $debug ? print('<strong style="color:green">&raquo;</strong> Parsing: ' . $original)  : false;

        $arr = array_slice(array_filter(explode('.', $domain, 4), function($value) {
                    return $value !== 'www';
                }), 0); //rebuild array indexes

        if (count($arr) > 2) {
            $count = count($arr);
            $_sub = explode('.', $count === 4 ? $arr[3] : $arr[2]);

            $debug ? print(" (parts count: {$count})")  : false;

            if (count($_sub) === 2) { // two level TLD
                $removed = array_shift($arr);
                if ($count === 4) { // got a subdomain acting as a domain
                    $removed = array_shift($arr);
                }
                $debug ? print("<br>\n" . '[*] Two level TLD: <strong>' . join('.', $_sub) . '</strong> ')  : false;
            } elseif (count($_sub) === 1) { // one level TLD
                $removed = array_shift($arr); //remove the subdomain

                if (strlen($_sub[0]) === 2 && $count === 3) { // TLD domain must be 2 letters
                    array_unshift($arr, $removed);
                } else {
// non country TLD according to IANA
                    $tlds = array(
                        'aero',
                        'arpa',
                        'asia',
                        'biz',
                        'cat',
                        'com',
                        'coop',
                        'edu',
                        'gov',
                        'info',
                        'jobs',
                        'mil',
                        'mobi',
                        'museum',
                        'name',
                        'net',
                        'org',
                        'post',
                        'pro',
                        'tel',
                        'travel',
                        'xxx',
                    );

                    if (count($arr) > 2 && in_array($_sub[0], $tlds) !== false) { //special TLD don't have a country
                        array_shift($arr);
                    }
                }
                $debug ? print("<br>\n" . '[*] One level TLD: <strong>' . join('.', $_sub) . '</strong> ')  : false;
            } else { // more than 3 levels, something is wrong
                for ($i = count($_sub); $i > 1; $i--) {
                    $removed = array_shift($arr);
                }
                $debug ? print("<br>\n" . '[*] Three level TLD: <strong>' . join('.', $_sub) . '</strong> ')  : false;
            }
        } elseif (count($arr) === 2) {
            $arr0 = array_shift($arr);

            if (strpos(join('.', $arr), '.') === false && in_array($arr[0], array('localhost', 'test', 'invalid')) === false) { // not a reserved domain
                $debug ? print("<br>\n" . 'Seems invalid domain: <strong>' . join('.', $arr) . '</strong> re-adding: <strong>' . $arr0 . '</strong> ')  : false;
// seems invalid domain, restore it
                array_unshift($arr, $arr0);
            }
        }

       // $debug ? print("<br>\n" . '<strong style="color:gray">&laquo;</strong> Done parsing: <span style="color:red">' . $original . '</span> as <span style="color:blue">' . join('.', $arr) . "</span><br>\n")  : false;

        return join('.', $arr);
    }
    
    // String compare filer. Takes any string, and any number of search patterns.
    // Looks through the string giving one point for each pattern found.
    

    function filter($str, $needle) {
        $relevance = 0;

        foreach ($needle as $query) {
            if (stripos($str, $query)) {
                $relevance ++;
            }
        }
        return $relevance;
    }

    // Do a curl script to download a html page.
    function curl($url) {


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
    // Download and update a URL.
    function download($URL) {
        global $conn;

        if (!$conn) {
            $this->out('Connection failed');
        }
        $this->status('Attempting to download ' . $URL);

        $content = $this->curl($URL);

        if ($content === false) {
            $content = 'failed to load';
        }

        $sql = "UPDATE $this->tableName SET  content = ?, isDownloaded = 1 where URL = ?";
        try {

            $stmt4 = $conn->prepare($sql);
            if ($stmt4->errno) {
                $this->out('Error preparing SQL statement ');
            }

            $stmt4->bind_param('ss', $content, $URL);

            $stmt4->execute();

            $stmt4->store_result();
            // print "Updated the ".$this->id.", affected rows ".$stmt4->affected_rows." balance now ".$this->balance;
            if ($stmt4->errno) {
                $this->out('Unable to persist the result: ');
                $stmt4->close();
                return 0;
            }

            $this->downloaded ++;

            return 1;
        } catch (mysqli_sql_exception $e) {
            $this->error( $e->errorMessage());
        }
    }
    // Get the domain locale of a string. Does not work with double-top level. such as
    // co.uk, it will return uk only.
    function getLocale($domain) {

        return substr($domain, strrpos($domain, '.') + 1);
    }
    // Go through all URLs and recalculate the url and relevance calculations.
    function analyzeAll() {
        global $conn;
        $sql = "SELECT URL, content, relevance, urlRelevance, humanRelevance from contactCrawler WHERE isDownloaded = 1 '
                . 'ORDER by urlRelevance DESC  LIMIT 100";

        // Get the language of the site

        $stmt = $conn->prepare($sql);
        
        $stmt->bind_result($url, $content, $relevance, $humanRelevance, $urlRelevance);
        $stmt->execute();
        $stmt->store_result();
        
        while ($stmt->fetch()) {
            $newRelevance = $this->filter($content, $needle);
            $newUrlRelevance = $this->filter($URL, $needle);
            if($newRelevance != $relevance || $newUrlRelevance != $urlRelevance){
                $this->update($domain, $URL, $newRelevance, $newUrlRelevance, $humanRelevance, $content);
            }
        }
    }
    // Updates the domain of all urls. Takes LONG time.
    function updateDomainAll(){
       global $conn;
       $startTime = time();
        $sql = "SELECT domain, subDomain, URL, relevance, urlRelevance, humanRelevance, locale from crawler  ";
                
         //       . " WHERE timestamp < UNIX_TIMESTAMP(?)"
        //       . " LIMIT 100000";
     ;
        // Get the language of the site
      //  $timeCutoff = '2014-09-09 09:40:30';
        $stmt = $conn->prepare($sql);
       // $stmt->bind_param('s', $timeCutoff);
        
        $stmt->execute();
        $stmt->bind_result($domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance, $locale);
        $stmt->store_result();
       
        $this->status ("Returned $stmt->num_rows rows");
        $i = 0;
        $j = 0;
        $n = 0;
        
        while ($stmt->fetch()) {
            $n++;
            //if(!$locale || !$domain || !$subDomain){
               
                if(stripos($URL, '/')=== false){
                   
                        $newSubDomain = $URL;
                     
                }
                else{
                    
                     $newSubDomain = parse_url($URL, PHP_URL_HOST);
                     
                }
            
             
                if(strpos($newSubDomain, 'www.') ==0){
                   $newSubDomain =  str_replace('www.', '', $newSubDomain);
                }
               
                $newDomain = $this->getDomain($newSubDomain);
                if(!$locale){
                    $locale = $this->getLocale($subDomain);
                    $this->updateLocale($locale, $URL);
                }
                
                if($newDomain != $domain || $newSubDomain != $subDomain){
                    $j++;
                  //  $this->status( "$newDomain - $domain and $newSubDomain - $subDomain");
                    $i += $this->update($newDomain, $newSubDomain, $URL, $relevance, $urlRelevance, $humanRelevance);  
                }
           // }
        }
        $this->status("Processed $n , isolated $j , updated  $i domains");
        $this->status('Update domain operation took '.$startTime - time() .' seconds');
    }
    

    /*
      function upsert($tableName, $pk, $pkVal, $arg, $t){
      /*
     *  $arg = array('pk'=> $domain, $fields => array('URL' => $URL, 
      'conactRelevance'=>$contactRelevance,
      'urlRelevance'=>$urlRelevance));
      $this->upsert('contactRelevance', $arg);


      foreach($arg as $key =>$value){
      $fs .= $key.',';
      $vs .= $value.',';
      }
      $fs = rtrim($fs, ',');
      $vs = rtrim($vs, ',');
      $sql = "INSERT INTO $tableName ($fs) values ($vs)";
      $stmt = $conn->prepare($sql);

      if ($stmt == false) {
      print 'Error in sql';
      }

      $stmt->bind_param($t, $arg);

      $stmt->execute();
      if ($stmt->errno > 0 ) {
      switch ($stmt->errno){
      case 1062:
      $sql = 'UPDATE $tablename SET';
      foreach($arg as $key =>$value){

      if($o =='s'){
      $sql .=   $key = "'$value',";
      }
      elseif($o=='i'){
      $sql .=   $key = "$value,";
      }


      $i ++;
      }
      $o =substr($t, $i, 1);
      $sql = rtrim($sql, ',')." WHERE $pk = ";
      if($o =='s'){
      $sql .=   "'$pkVal'";
      }
      elseif($o=='i'){
      $sql .=   "$pkVal";
      }

      $stmt = $conn->prepare($sql);

      if ($stmt == false) {
      print 'Error in update';
      }


      break;
      default:
      return true;
      break;
      }
      }


      }
     * 
     */

    function deleteURL($URL) {
        global $conn;
        $sql = "DELETE FROM  CRAWLER WHERE URL = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $this->out('Error preparing SQL statement ');
            die('prepare() failed: ' . htmlspecialchars($conn->error));
        }
        $stmt->bind_param('s', $URL);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->errno > 0) {
            print $stmt->error;
        }
        $this->out("Deleted $URL as preemptive cleanup");
        return true;
    }

    function out($msg) {

        print $msg . '<br>';
        flush();
    }

    function status($msg) {

        print $msg . '<br>';
        flush();
    }

    function error($context, $msg, $data) {

        print $contenxt . ' - ' . $msg . ' - ' . $data . '<br>';
        flush();
    }
    
    // Upsert is a generic INSERT/UPDATE function which attempts to insert data
    // If mysql instance responds with error 1062, the insert is rejected due to
    // a primary key duplication - it already exists, and should be updated.
    // On handling the error it fires the update method.
    // Returns the number of rows (inserted + updated).
    
    function upsert($domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance,  $content = null) {
       global $conn;
        $domain = $this->getDomain($subDomain);
        
        if (!$content) {
          
               $sql = "INSERT INTO $this->tableName (domain, subDomain, url, relevance, urlRelevance, humanRelevance ) "
                       . "values ( ? , ? , ? ,?, ?, ? )";
              
               $stmt = $conn->prepare($sql);
               if ($stmt === false) {
                print $conn->error;
                    die('insert error ' . mysqli_connect_error());
               }
               $stmt->bind_param('sssiii', $domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance);
        }
        else{
            
                 $sql = "INSERT INTO $this->tableName  (domain, subDomain,  url, relevance, urlRelevance, humanRelevance, content ) "
                         . "values ( ?,? , ? , ? ,?,?, ? )";
                 $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    die('insert error ' . mysqli_connect_error());
                }
               $stmt->bind_param('sssiiis', $domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance, $content);
        }
        if ($stmt === false) {
            $this->out('Error preparing SQL statement ');
            die('prepare() failed: ' . htmlspecialchars($conn->error));
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->errno > 0) {
            switch ($stmt->errno) {
                case 1062:
                 
                    return  $this->update($domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance, $content);
                    break;
                default :
                    
                    $this->out('Insertcontact page error'.$stmt->error);
                    return $stmt->affected_rows;
                    break;
            }
        }
       return $stmt->affected_rows;
    }
    
    
    function updateLocale($locale, $URL){
         global $conn;
           $sql = "UPDATE $this->tableName SET locale = ? WHERE URL = ?";
        $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                die('update locale error ' . $conn->error());
            }
            $stmt->bind_param('ss', $locale, $URL);
            $stmt->execute();
            if ($stmt->errno > 0) {
                $this->status( $stmt->error );
                die();
                return 0;
            }
        
        return $stmt->affected_rows;
    }
    // Update the variables - if the content is null. Works in conjunction with UPSERT
    // which fires this update if the primary key already exists.
    function update($domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance, $content = null) {
        global $conn;
       
        if (!$content) {
          
                $sql = "UPDATE $this->tableName SET domain = ?, subDomain = ?, relevance = ?, urlRelevance = ?, humanRelevance = ? WHERE URL = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    die('update error ' . mysqli_connect_error());
                }
                $stmt->bind_param('ssiiis', $domain, $subDomain, $relevance, $urlRelevance, $humanRelevance, $URL);
        }
        else{
               $sql = "UPDATE $this->tableName SET domain = ?, subDomain = ?, relevance = ?, urlRelevance = ?, humanRelevance = ? , content = ?  WHERE URL = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    die('update error ' . mysqli_connect_error());
                }
               $stmt->bind_param('ssiiiss', $domain, $subDomain, $relevance, $urlRelevance, $humanRelevance, $content, $URL);
        }
        
        $stmt->execute();
        if ($stmt->errno > 0) {
            $this->status( $stmt->error );
            die();
            return 0;
        }
        
        return $stmt->affected_rows;
    }

    function __destruct() {
        if (isset($_GET) && array_key_exists('cron', $_GET)) {
            $cron = 'cronJob';
        } else {
            $cron = 'manual';
        }
        $this->totalTime = time() - $this->startTime;
        $this->cronEntry($cron);
    }

    function cronEntry($cron) {
        global $conn;
        $sql = "INSERT INTO cronEntry (tableName, "
                . "downloaded, analyzed, newlinks, "
                . "downloadMax, crawlMax, downloadTime , "
                . "analyzeTime, crawlTime, timestamp, "
                . "newDomainTime , caller, totalTime) "
                . "values(?,?,?, ?,?,?,?,?,?, FROM_UNIXTIME(?) , ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt == false) {
            print 'Error in sql<br>';
        }
        $time = time();

        $stmt->bind_param('siiiiiiiiiisi', $this->tableName, $this->downloaded, $this->analyzed, $this->newLinks, $this->downloadMax, $this->crawlMax, $this->downloadTime, $this->analyzeTime, $this->crawlTime, $time, $this->newDomainTime, $cron, $this->totalTime);
        $stmt->execute();
        if ($stmt->errno > 0) {
            print $stmt->error;
        }
        return true;
    }

    function timeSpent() {
        return time() - $this->startTime;
    }

    function memoryUsage() {
        $size = memory_get_usage();
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    // Retrieves the most relevandt domains a decided by relevance.
    // Dump
    function topRelevanceDomains($limit, $locale) {
        $startTime = time();
        $this->out('Fetching the top ' . $limit . ' most relevant domains');
        global $conn;
       
        
        $sql = "SELECT domain, subDomain, max(relevance) as relevance, max(urlRelevance) as urlRelevance, max(humanRelevance) as humanRelevance "
                . "FROM CRAWLER "
                . "WHERE locale = ? "
                . "GROUP BY domain "
                . "HAVING relevance > 0 "
                . "ORDER BY relevance DESC "
                . "LIMIT ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param('si', $locale, $limit);

        if ($stmt == false) {
            print 'Error in sql<br>' . $conn->error;
        }
        $stmt->bind_result($domain, $subDomain, $relevance, $urlRelevance, $humanRelevance );
        
        $stmt->execute();
        $stmt->store_result();
        while($stmt->fetch()){
            $this->upsert($domain, $subDomain, $subDomain, $relevance, $urlRelevance, $humanRelevance);
        }
        $endTime = time();
        $this->out('Created top relevance domains in ' . $endTime - $startTime . ' seconds');
        return true;
    }
    
    // Imports a modified file and updated the database with records pertaining to this
    // particular domain.
    
    function importCSV($file){
        global $conn;
        $this->status('Importing from file '.$file);
        $handle = fopen($file,"r");
        if(!$handle){
              $this->status('Could not open csv '.$file);
              return false;
        }
        $data = fgetcsv($handle,1000,",","'");

        $i = 0;
        $j = 0;
        $n = 0;
        while ($data = fgetcsv($handle,1000,",","'")) {
            $domain = $this->getDomain($data[0]);
            $subDomain = $data[0];
            $URL = $data[0];
            
           
            
            $title = '';
            $relevance = $data[2];
            $urlRelevance = $data[3];
            $humanRelevance = $data[4];
            $n++;
         
            if($humanRelevance>0){
               $j ++;
               $i = $this->upsert($domain, $subDomain, $URL, $relevance, $urlRelevance, $humanRelevance);
            }
        } 
        $this->status("Looped through $n rows, isolated $j rows, updated a total of $i rows");
    }
    
    function exportMysqlToCsv($table, $locale) {
        global $conn;
        $csv_terminated = "\n";
        $csv_separator = ",";
        $csv_enclosed = '"';
        $csv_escaped = "\\";
        $sql = "select domain, relevance, urlRelevance, humanRelevance from $table LIMIT 300";

        $stmt = $conn->prepare($sql);
      
        // Gets the data from the database
       // $result = mysql_query($sql_query);
        //$fields_cnt = mysql_num_fields($result);
        $stmt->bind_result($domain,  $relevance, $urlRelevance, $humanRelevance);
        
        $stmt->execute();
        $stmt->store_result();
        $this->status( 'returned '.$stmt->num_rows.' rows');
        $schema_insert = 'domain, relevance, urlRelevance, humanRelevance'.$csv_terminated;
        
        
        while($stmt->fetch()){
            $schema_insert .= "$domain, $relevance, $urlRelevance, $humanRelevance $csv_terminated";
        }
        $out = trim(substr($schema_insert, 0, -1));
      
        // Format the data
        /*
        while ($row = mysql_fetch_array($result)) {
            $schema_insert = '';
            for ($j = 0; $j < $fields_cnt; $j++) {
                if ($row[$j] == '0' || $row[$j] != '') {

                    if ($csv_enclosed == '') {
                        $schema_insert .= $row[$j];
                    } else {
                        $schema_insert .= $csv_enclosed .
                                str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                    }
                } else {
                    $schema_insert .= '';
                }

                if ($j < $fields_cnt - 1) {
                    $schema_insert .= $csv_separator;
                }
            } // end for
*/
          //  $out .= $schema_insert;
           // $out .= $csv_terminated;
       // } // end while
        $today = getdate();
        $this->status('Saving content to file');
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/crawler/csv/top_' . $locale . '.csv', $out);
        
        /*
          header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
          header("Content-Length: " . strlen($out));
          // Output to browser with appropriate mime type, you choose ;)
          header("Content-type: text/x-csv");
          //header("Content-type: text/csv");
          //header("Content-type: application/csv");
          header("Content-Disposition: attachment; filename=$filename");
          echo $out;
         * 
         */
       return true;
    }

    public function getCrawlList() {
        global $conn;

        $sql = "SELECT id, timestamp, caller, downloaded, analyzed, newLinks, downloadMax, crawlMax, analyzeTime, downloadTime, crawlTime, newDomainTime, tableName FROM cronEntry order by timestamp desc ";

        if (!$conn) {
            Control::error('Commoncrawler.getCrawlList', 'Connection failed', '');
            return false;
        }

        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $this->error('Commoncrawler.getCrawlList', 'Wrong SQL:  Error: ' . $conn->error, '');
            return false;
        }
        $stmt->execute();

        $stmt->bind_result($id, $timestamp, $caller, $downloaded, $analyzed, $newLinks, $downloadMax, $crawlMax, $analyzeTime, $downoadTime, $crawlTime, $newDomainTime, $tableName);

        if (!$stmt->store_result()) {
            $this->error('Commoncrawler.getCrawlList', '');
            return false;
        }

        if ($stmt->num_rows > 0) {

            $i = 0;
            print '<h2>Cron entries</h2><table width="2000">';
            print '<tr><td>ID</td>';
            print '<td>Timestamp</td>';
            print '<td>Caller</td>';
            print '<td>Downloaded</td>';
            print '<td>Analyzed</td>';
            print '<td>NewLinks</td>';
            print '<td>DownloadMax</td>';
            print '<td>CrawlMax</td>';
            print '<td>Analyzetime</td>';
            print '<td>Downloadtime</td>';
            print '<td>Crawltime</td>';
            print '<td>NewDomainTime</td>';
            print '<td>Tablename</td></tr>';

            while ($stmt->fetch()) {
                $i ++;
                print '<tr>';
                print '<td>' . $id . '</td>';
                print '<td>' . $timestamp . '</td>';
                print '<td>' . $caller . '</td>';
                print '<td>' . $downloaded . '</td>';
                print '<td>' . $analyzed . '</td>';
                print '<td>' . $newLinks . '</td>';
                print '<td>' . $downloadMax . '</td>';
                print '<td>' . $crawlMax . '</td>';
                print '<td>' . $analyzeTime . '</td>';
                print '<td>' . $downoadTime . '</td>';
                print '<td>' . $crawlTime . '</td>';
                print '<td>' . $newDomainTime . '</td>';
                print '<td>' . $tableName . '</td>';
                print '</tr>';
            }

            print '</table>';
            return true;
        } else {
            print 'No test found';
            return true;
        }
    }
    
}
