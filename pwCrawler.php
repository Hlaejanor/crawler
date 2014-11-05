<?php

/* 
 * CLASS PWCrawler is designed to crawl the site pw.org
 */



class PWCrawler extends CommonCrawler{
    function __construct() {
        parent::__construct('contactCrawler');
    }
    // Overload default queries with queries relating to contact results. 
    // Ranks pages based on if they contain contact us and assoicateds strings.
    
   function createPWTables() {
        global $conn;

        $charset_collate = '';

        if (!empty($conn->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET {$conn->charset}";
        }

        if (!empty($conn->collate)) {
            $charset_collate .= " COLLATE {$conn->collate}";
        }

        $table_name = 'pwData';

        $sql = "CREATE TABLE  IF NOT EXISTS $table_name (
        context varchar(10)  NOT NULL,
        funcName varchar(55)  NOT NULL,
        message varchar(250)  NOT NULL,
        data varchar(250)  NOT NULL
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        if (!dbDelta($sql)) {
            WideScribeWpAdmin::error('createWSTables', 'Error creating vxl log table. If you see this message, then the reason is that the table already existed');
        } // Cr 
        $table_name = $wpdb->prefix . "widescribe_log";



        $sql = "CREATE TABLE  IF NOT EXISTS $table_name (
        context varchar(10)  NOT NULL,
        funcName varchar(55)  NOT NULL,
        message varchar(250)  NOT NULL,
        data varchar(250)  NOT NULL
        ) $charset_collate;";


        if (!dbDelta($sql)) {
            WideScribeWpAdmin::error('createWSTables', 'Error creating vxl error table. If you see this message, then the reason is that the table already existed');
        } // Create the log table
        $message = 'Successfully created WideScribe local log and error tables';
        WideScribeWpAdmin::log('createWSTables', $message);

        return $message;
    }

}
