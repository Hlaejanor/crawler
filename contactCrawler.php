<?php

/* 
 * CLASS ContactCrawler
 * Used to search for pages which contain 
 * and open the template in the editor.
 */



class ContactCrawler extends CommonCrawler{
    function __construct() {
        parent::__construct('contactCrawler');
    }
    // Overload default queries with queries relating to contact results. 
    // Ranks pages based on if they contain contact us and assoicateds strings.
    function getQueries($locale){
    $needle = array();
   
     switch ($locale) {
        case 'no' :
            $needle[] = 'kontakt';
            $needle[] = 'utgitt';
            $needle[] = 'redaktør';
            $needle[] = 'direktør';
            break;
        case 'se' :
            $needle[] = 'kontakta';
            $needle[] = 'utgivare';
            $needle[] = 'redaktör';
            $needle[] = 'försäljningschef';
            break;
        case 'de':
            $needle[] = '';
            break;
        case 'es':
            $needle[] = '';
            $needle[] = '';

            break;
        case 'pl':
            $needle[] = '';
            break;
        case 'sa':
            $needle[] = '';
          
            break;
        case 'fr':
            $needle[] = '';
            break;
        case 'dk':
            $needle[] = 'kontakt';
            $needle[] = 'redaktør';
            $needle[] = 'udgiver';
          
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
            $needle[] = 'contact';
            $needle[] = 'editor';
          
            break;
    }
    return $needle;
}


// Insert all links which are relevant from the main crawler table into
// the contactCrawler table

function getRelevantURLsFromCrawler($limit){
    global $conn;
    $sql = "SELECT domain, content, URL, "
            . "isDownloaded, isAnalyzed, relevance, urlRelevance, locale "
            . "FROM crawler "
            . "LIMIT ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('i', $limit);
    $stmt->execute(); 
    
    $stmt->bind_result($domain, $content, $URL, $isDownloaded, $isAnalyzed, $relevance, $urlRelevance, $locale);
    $stmt->execute();
    $i = 0;
    $stmt->store_result();
    while ($stmt->fetch()) {
         if(!$domain){
         $domain = $this->getDomain($url);
         }
         if(!$locale){
            $locale = $this->getLocale($domain);
         }
         $needle = $this->getQueries($locale);
         
         $urlRelevance = $this->filter($url, $needle);
    
         //  $this->out('UR :'.$urlRelevance.' , R'.$relevance);
         if( $urlRelevance > 0){
               if(!$isDownloaded){
                    $content =  $this->download($URL);
               }  
               $relevance = $this->filter($content, $needle);
               
               $i += $this->upsert($domain, $subDomain, $URL, $relevance, $urlRelevance, $content);
         } 
        
    }
   $this->status("Updated  $i contact records");
}

// Download all relevant URLs that have been placed in the contactcrawler table
function downloadAll(){
    global $conn;
    $sql = "SELECT URL from $this->tableName WHERE isDownloaded = 0 '
            . 'ORDER by urlRelevance DESC LIMIT 10";
    // Get the language of the site
   
    $stmt = $conn->preare($sql);
   
    $stmt->bind_result($url);
            
    while ($stmt->fetch()) {
          $this->download($URL);
    }        
            
}

// Analyze all downloaded urls in main crawler table, and extract emails.

function analyzeAll($limit = null){
    global $conn;
   
    $sql = "SELECT URL, content, relevance, urlRelevance, locale from crawler WHERE isDownloaded = 1 AND content LIKE '%@%' 
            ORDER by urlRelevance DESC LIMIT ?";
   
    // Get the language of the site
   
    $stmt = $conn->prepare($sql);
    
    $stmt -> bind_param('i', $limit);
    if($stmt->errno > 0){
        print 'Error '.$stmt->error;
    }
    $stmt->execute();
    $stmt->bind_result($url, $content, $relevance, $urlRelevance, $locale);
    $affected = 0; 
    $stmt->store_result();
    while ($stmt->fetch()) {
        $extractedEmails += $this -> getEmail($content, $domain, $url);
    }    
    $this->out("Analyzed $stmt->num_rows and extracted $extractedEmails new emails");
   // return $affected;
}

function getEmail($content, $domain, $url){
    global $conn;
    preg_match_all('/([\w+\.]*\w+@[\w+\.]*\w+[\w+\-\w+]*\.\w+)/is',$content,$results);
    // Add the email to the email list array
    $insertCount=0;
    
    $sql = "INSERT INTO crawl_email (email, domain, URL) VALUES (?,?,?)";
    
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param('sss' , $curEmail, $domain, $url);
    foreach($results[1] as $curEmail)
    {
             $stmt->execute();
             if ($stmt->errno > 0 ) {
                   switch ($stmt->errno){
                    case 1062:
                        // The email already exist, not an error but not increment emails
                        
                     break;
                     default : 
                         $this->out('Email detect found error '.$stmt->error);
                         return false;
                     break;
                }
            }
            else{
                $insertCount++;
            }
    return $insertCount;
    }
}
}
