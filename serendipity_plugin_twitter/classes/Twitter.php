<?php # $Id$
/*
 * Contributed by Grischa Brockhaus <s9ycoder@brockha.us>
 *
 * Implements access to the twitter api
 * 
 */

include dirname(__FILE__) . '/json.php4.include.php';
include dirname(__FILE__) . '/twitter_entry_defs.include.php';

class Twitter {

    var $use_identica = false;
    
    var $last_error = 200; // success
    var $error_response = '';
    
    var $twitter_errors = array(
        200 => "OK",
        304 => "Not Modified",
        400 => "Bad Request",
        401 => "Not Authorized",
        403 => "Forbidden",
        404 => "Not Found",
        406 => "Not Acceptable",
        500 => "Internal Server Error",
        502 => "Bad Gateway: Twitter is down or being upgraded",
        503 => "Service Unavailable"
    );
    
    /**
     * The constructor of this class.
     * @param boolean use_identi_ca default=false (use twitter mode)
     */
    function Twitter($use_identi_ca = false) {
        $this->use_identica = $use_identi_ca;
    }
    
    /**
     * Base URL of service
     * @access   private
     */
    function get_base_url() {
        if ($this->use_identica) {
            return "http://identi.ca/";
        }
        else {
            return "http://twitter.com/";
        }
    }
    
    /**
     * API URL of service
     * @access   private
     */
    function get_api_url() {
        if ($this->use_identica) {
            return "http://identi.ca/api/";
        }
        else {
            return "http://twitter.com/";
        }
    }
    
    /**
     * API URL for searches of service
     * @access   private
     */
    function get_search_url() 
    {
        if ($this->use_identica) {
            return "http://identi.ca/api/search";
        }
        else {
            return "http://search.twitter.com/search";
        }
    }
    
    var $search_rss_encoding = 'UTF-8';
    
    /**
     * searches twitter. 
     * 
     * Returns an array of entry arrays (id=> entry).
     * 
     * entries have the following fields set: 
     * id, login, realname, email, tweet, pubdate, retweet
     * url_autor, url_img, url_tweet
     * 
     * @param string search urldecoded query
     * @param string[] checkresultfor As twitter is casinsensitive, here you may specify al list of strings, from what *one* string must be found inside the result
     * @param entry[] a prior search result. Search result will be added, if not null or empty
     * @return entry[] results as array of entry arrays or false, if an error occured
     */
    function search($search, $checkresultfor=null, $entries=null, $fetchall=true) {
        require_once S9Y_PEAR_PATH . 'HTTP/Request.php';
        
        $search_uri = $this->get_search_url() . '.json?q=' . $search;

        // Special Twitter search params 
        if (!$this->use_identica) {
            $search_uri .= "&rpp=100&include_entities=1";
        }
        
        //echo "Searching: $search_uri <br/>"; 

        $paging = true;
        
        while ($paging) {        
        
            if (function_exists('serendipity_request_start')) serendipity_request_start();
            $req = new HTTP_Request($search_uri, array('timeout' => 20, 'readTimeout' => array(5,0)));
            $req->sendRequest();
            $this->last_error = $req->getResponseCode();
            if ($req->getResponseCode() != 200) {
                $this->last_error = $req->getResponseCode();
                $this->error_response = trim($req->getResponseBody());
                if (function_exists('serendipity_request_start')) serendipity_request_end();
                return false;
            }
            $response = trim($req->getResponseBody());
            if (function_exists('serendipity_request_start')) serendipity_request_end();
            
            $json = @json_decode($response);
            
            if (!is_array($entries) || empty($entries)) $entries = array();
            foreach ($json->results as $item) {
                $entry = $this->parse_entry_json( $item );
                
                // Debug: remember the search executed
                $entry[TWITTER_SEARCHRESULT_URL_QUERY] = $search;
                
                // Watch out: If $item->id is interpreted as int, high values produce problems
                // So I force strings as array keys here.
                $entries[$entry[TWITTER_SEARCHRESULT_ID]] = $entry; // overwrite old entry, if already have one
            }
            
            $paging = !empty($json->next_page);
            if ($fetchall && $paging) {
                $search_uri = $this->get_search_url() . '.json' . $json->next_page; 
            }
        }
        
        return $entries;   
    }
    
    /**
     * Searches for multiple keywords
     * @param array keywords The keywords to search for
     * @param string since_id Limit results to entries starting after since_id 
     * @param boolean search_or search using OR or AND
     */
    function search_multiple($keywords, $since_id = null, $search_or = true) {
        $entries = array();
        $query = '';
        $queries = array();
        
        // Filter: tweeds containing links only. 
        // This Filter doesn't work at the moment, will produce an empty result!
        // It is not neccessary for us, only a hint for twitter.
        // rpp: results per page
        $filter = '&rpp=100'; // +filter:links
        if (!empty($since_id)) {
            $filter .= "&since_id=$since_id";
        }
        
        // Filter will be added to the query, so substract it.
        $max_query_len = 139 - strlen($filter);
        
        // Optimize twitter query with OR urls. But a query may not be longer than 140 chars! So split it.
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (empty($keyword)) continue; // something whent wrong while fetching shorturls
            $keyword = preg_replace("/^.*?\:\/\//i", "", $keyword); // twitter doesn't search http
            $keyword_encoded = urlencode($keyword);
            if (empty($query)) $query = $keyword_encoded;
            else {
                $test = $query . '+OR+' .$keyword_encoded;
                if (strlen($test)> $max_query_len) {
                    $queries[] = $query;
                    $query = $keyword_encoded;
                }
                else {
                    $query = $test;
                }
            }
        }
        
        // Add query, something is left for sure!
        $queries[] = $query;

        // Now execute the queries        
        $api = new Twitter();
        foreach ($queries as $q) {
            $continue = true;
            $newentries = $api->search($q . $filter, $keywords, $entries);
            if ($newentries===false) { // Error occured, mostly resultet in an twitter overload!
                $continue = false;
                echo "<b>Search qry</b>: ".$api->get_search_url()."?q={$q}{$filter}<br/>";
                echo "<b>Error code</b>: " . $api->twitter_errors[$api->last_error] . " ({$api->last_error})<br/>";
                if (!empty($api->error_response)) {
                    $response = json_decode($api->error_response);
                    if (!empty($response->error)) $errormsg=$response->error;
                    else {
                        $errormsg=$api->error_response;
                        // TODO Twitter Fix: No result delivers http error at the moment!
                        if ("Exceptions::NoResults"==$api->error_response) {
                            $continue = true;
                        }
                    }
                    echo "<b>Error Resp</b>: {$errormsg}<br/>";
                }
                if (!$continue) break;
            }
            $entries = $newentries;
        }
        
        /*
        // identica search. Doesn't support OR.. :-(
        $api = new Twitter(true); // identica version
        foreach ($keywords as $keyword) {
            $newentries = $api->search(urlencode($keyword) . $filter, $keywords, $entries, false);
            if ($newentries===false) { // Error occured, mostly resultet in an twitter overload!
                $continue = false;
                echo "<b>Search qry</b>: ".$api->get_search_url()."?q={$q}{$filter}<br/>";
                echo "<b>Error code</b>: " . $api->twitter_errors[$api->last_error] . " ({$api->last_error})<br/>";
                if (!empty($api->error_response)) {
                    echo "<b>Error Resp</b>: {$api->error_response}<br/>";
                }
                break;
            }
            $entries = $newentries;
        }
        */
        return $entries;
    }
    
    function parse_entry_json( $item ) {
        $entry = array();
        
        if (preg_match('/href="([^"]*)"/',html_entity_decode($item->source),$matches)) {
            $source_link = $matches[1][0];
        }
        //$link = str_replace('<a href="','',str_replace('"/a>','',html_entity_decode($item['source'])));
        $entry[TWITTER_SEARCHRESULT_LOGIN] = $item->from_user;
        $entry[TWITTER_SEARCHRESULT_REALNAME] = $item->from_user;
        if( !function_exists('htmlspecialchars_decode') ) {
            $entry[TWITTER_SEARCHRESULT_TWEET] = $item->text; // PHP4 Version w/o html_specialcar decoding. 
        }
        else {
            $entry[TWITTER_SEARCHRESULT_TWEET] = htmlspecialchars_decode($item->text);
        }

        $uniq = (isset($item->id_str) ? $item->id_str : sprintf('%0.0f', $item->id));        
        $entry[TWITTER_SEARCHRESULT_ID] = $uniq;

        $entry[TWITTER_SEARCHRESULT_URL_AUTOR] = $this->get_base_url() . $item->from_user;
        $entry[TWITTER_SEARCHRESULT_URL_IMG] = $item->profile_image_url;
        if ($this->use_identica) {
            $entry[TWITTER_SEARCHRESULT_URL_TWEET] = $this->get_base_url() . '/notice/' . $entry[TWITTER_SEARCHRESULT_ID];
        }
        else {
            $entry[TWITTER_SEARCHRESULT_URL_TWEET] = $this->get_base_url() . $entry[TWITTER_SEARCHRESULT_LOGIN] . '/status/' . $entry[TWITTER_SEARCHRESULT_ID];
        }
        if (!empty($source_link)) $entry[TWITTER_SEARCHRESULT_URL_SRC] = $source_link;
        $entry[TWITTER_SEARCHRESULT_PUBDATE] = $item->created_at;
        $entry[TWITTER_SEARCHRESULT_RETWEET] = preg_match('/^(rt|retweet|retweeting)[ :].*/i',$item->text);
        
        // get expanded urls
        if (!empty($item->entities)) {
            if (!empty($item->entities->urls)) {
                $urls = array();
                $urlsExpanded = array();
                $redirCheck = new RedirectCheck();
                foreach ($item->entities->urls as $url) {
                    if (!empty($url->expanded_url)) {
                        $urls[] =$url->expanded_url;
                    }
                }
                $entry[TWITTER_SEARCHRESULT_URL_ARRAY] = $urls;
            }
        }
        return $entry;
    }
    
    function update( $login, $pass, $update, $geo_lat = NULL, $geo_long = NULL ) {
        require_once S9Y_PEAR_PATH . 'HTTP/Request.php';
        
        if (empty($login) || empty($pass) || empty($update)) return;
        
        $status_url = $this->get_api_url() . 'statuses/update.json';
        
        require_once S9Y_PEAR_PATH . 'HTTP/Request.php';
        if (function_exists('serendipity_request_start')) serendipity_request_start();
        $par['user'] = $login;
        $par['pass'] = $pass;
        $par['method'] = HTTP_REQUEST_METHOD_POST;
        $par['timeout'] = 20;
        $par['readTimeout'] = array(5,0);

        $req = new HTTP_Request($status_url, $par);
        
        $update = urlencode($update);

        $req->addPostData('status',$update, true);
        $req->addPostData('source','s9y', true);
        if (!empty($geo_lat) && !empty($geo_long)) {
            $req->addPostData('lat',$geo_lat, true);
            $req->addPostData('long',$geo_long, true);
        }

        $req->sendRequest();
        $response = $req->getResponseBody();
        $errorcode = $req->getResponseCode();
        if (function_exists('serendipity_request_start')) serendipity_request_end();
        
        if ($errorcode == 200) {
            $json = @json_decode($response);
            if (isset($json->error)) {
                return $json->error;
            }
            else {
                return true;
            }
        }
        else {
            return $errorcode;
        }
    }

    // http://apiwiki.twitter.com/Twitter-REST-API-Method%3A-statuses-friends_timeline
    function timeline( $login, $pass, $count=10, $withfriends=true) {
        if (empty($login) || empty($pass)) return;

        $timeline_url = $this->get_api_url() . 'statuses/friends_timeline.json?';
        
        $timeline_url .= "count=$count";
        
        require_once S9Y_PEAR_PATH . 'HTTP/Request.php';
        if (function_exists('serendipity_request_start')) serendipity_request_start();
        $par['user'] = $login;
        $par['pass'] = $pass;
        $par['method'] = HTTP_REQUEST_METHOD_GET;
        $par['timeout'] = 20;
        $par['readTimeout'] = array(5,0);

        $req = new HTTP_Request($timeline_url, $par);

        $req->sendRequest();
        $response = trim($req->getResponseBody());
        if (function_exists('serendipity_request_start')) serendipity_request_end();
        
        return @json_decode($response);
        
    }
    
    // http://apiwiki.twitter.com/Twitter-REST-API-Method%3A-users%C2%A0show
    function userinfo($screenname) {
        if (empty($screenname)) {
            echo "screenname empty";
            return;
        }
        
        require_once S9Y_PEAR_PATH . 'HTTP/Request.php';
        
        $requrl = $this->get_api_url() . 'users/show.json?screen_name=' . $screenname;

        if (function_exists('serendipity_request_start')) serendipity_request_start();

        $req = new HTTP_Request($requrl);
        $req->sendRequest();
        $response = trim($req->getResponseBody());
        
        if (function_exists('serendipity_request_start')) serendipity_request_end();
        
        return @json_decode($response);
    }
    
    function replace_links_in_status( $status, $linktext_replace = '$1', $class_links = '', $class_user_links = '' ) {
        
        // Regular expression for smart detecting URLs inside of an Text.
        // Found at http://immike.net/blog/2007/04/06/5-regular-expressions-every-web-programmer-should-know/
        $pattern = '{(
  \\b
  # Match the leading part (proto://hostname, or just hostname)
  (
    # http://, or https:// leading part
    (https?)://[-\\w]+(\\.\\w[-\\w]*)+
  |
    # or, try to find a hostname with more specific sub-expression
    (?i: [a-z0-9] (?:[-a-z0-9]*[a-z0-9])? \\. )+ # sub domains
    # Now ending .com, etc. For these, require lowercase
    (?-i: com\\b
        | edu\\b
        | biz\\b
        | gov\\b
        | in(?:t|fo)\\b # .int or .info
        | mil\\b
        | net\\b
        | org\\b
        | [a-z][a-z]\\.[a-z][a-z]\\b # two-letter country code
    )
  )

  # Allow an optional port number
  ( : \\d+ )?

  # The rest of the URL is optional, and begins with /
  (
    /
    # The rest are heuristics for what seems to work well
    [^.!,?;"\\\'<>()\[\]\{\}\s\x7F-\\xFF]*
    (
      [.!,?]+ [^.!,?;"\\\'<>()\\[\\]\{\\}\s\\x7F-\\xFF]+
    )*
  )?
)}ix';
        $class = '';
        if (!empty($class_links)) $class =   'class="' . $class_links . '"';
        $status = preg_replace($pattern, '<a href="$1" ' .$class .'>' . $linktext_replace . '</a>', $status);
        if ($this->use_identica) {
            $status = preg_replace('{#([\w_]*)}','#<a href="' . $this->get_base_url() . 'tag/$1"' . $class . '>$1</a>', $status);
            $status = preg_replace('{!([\w_]*)}','!<a href="' . $this->get_base_url() . 'group/$1"' . $class . '>$1</a>', $status);
        }
    
        $class = '';
        if (!empty($class_user_links)) $class =   'class="' . $class_user_links . '"';
        $status = preg_replace('{@([\w_]*)}','@<a href="' . $this->get_base_url() . '$1" ' . $class . '>$1</a>', $status);
        
        return $status;
    }
    
    function create_status_ago_string($twitter_time_string){

        // Some strtotime versions are not able to handle the long date string. So shorten it!
        $datepart = explode(" ", $twitter_time_string);
        $shortdate = "{$datepart[2]} {$datepart[1]} {$datepart[5]} {$datepart[3]} {$datepart[4]}";

        //$time = (int)time() - @strtotime($twitter_time_string);
        $time = (int)time() - @strtotime($shortdate);
        
        if((int)$time === 0){
            $out = 'a wink';
        }
        elseif($time < 60){
            $out = $time.' second';
        }
        elseif($time >= 60 && $time < 3600){
            $time = $time / 60;
            $out = ($time % 60).' minute';
        }
        elseif($time >= 3600 && $time < 86400){
            $time = $time / 3600;
            $out = ($time % 3600).' hour';
        }
        elseif($time >= 86400 && $time < 604800){
            $time = $time / 86400;
            $out = ($time % 86400).' day';
        }
        elseif($time >= 604800 && $time < 2419200){
            $time = $time / 604800;
            $out = ($time % 604800).' week';
        }
        elseif($time >= 2419200 && $time < 29030400){
            $time = $time / 2419200;
            $out = ($time % 2419200).' month';
        }
        else{
            $time = $time / 29030400;
            $out = ($time % 29030400).' year';
        }

        if((int)$time > 1){
            $out .= 's';
        }

        return $out . ' ago';
    }
    
    function get_status_url( $account, $status_id ) {
        return $this->get_base_url() . ($this->use_identica?'notice/': $account . '/status/') . $status_id;  
    }
    
}
