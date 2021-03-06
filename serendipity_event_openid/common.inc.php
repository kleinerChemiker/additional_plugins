<?php # $Id$

// Probe for a language include with constants. Still include defines later on, if some constants were missing
$probelang = dirname(__FILE__) . '/' . $serendipity['charset'] . 'lang_' . $serendipity['lang'] . '.inc.php';
if (file_exists($probelang)) {
    include $probelang;
} else {
    include dirname(__FILE__) . '/lang_en.inc.php';
}

class serendipity_common_openid {

    function redir_openidserver($openid_url, $store_path, $wfFlag=1) {
        global $serendipity;
        $path_extra = dirname(__FILE__).DIRECTORY_SEPARATOR.'PHP-openid';
        $path = ini_get('include_path');
        $path = $path_extra . PATH_SEPARATOR . $path;
        ini_set('include_path', $path);
        require_once("Auth/OpenID/Consumer.php");
        require_once("Auth/OpenID/FileStore.php");
        $store = new Auth_OpenID_FileStore($store_path);
        $consumer = new Auth_OpenID_Consumer($store);
        $trust_root = $serendipity['baseURL'];
        switch ($wfFlag) {
            case 1:
                $process_url = $trust_root . 'serendipity_admin.php?serendipity[openidflag]=1';
                break;
            case 3:
                $process_url = $trust_root . 'serendipity_admin.php?serendipity[openidflag]=3'.
                    '&serendipity[adminModule]=event_display&serendipity[adminAction]=profiles';
                break;
            default:
                $process_url = $trust_root . $serendipity['indexFile'] . '?serendipity[subpage]=addopenid&serendipity[openidflag]=2';
        }

        $auth_request = $consumer->begin($openid_url);

        if (!$auth_request) {
            return FALSE;
        }
        
        $auth_request->addExtensionArg('sreg', 'required', 'fullname');
        $auth_request->addExtensionArg('sreg', 'required', 'email');

        $redirect_url = $auth_request->redirectURL($trust_root,
                                                   $process_url);
        header('Status: 302 Found');
        header("Location: ".$redirect_url);
        exit;
    }

    function reauth_openid() {
         global $serendipity;
         if (isset($_SESSION['serendipityOpenID']) && $_SESSION['serendipityOpenID']) {
              $serendipity['serendipityUser']         = $_SESSION['serendipityUser'];
              $serendipity['serendipityPassword']     = $_SESSION['serendipityPassword'];
              $serendipity['serendipityEmail']        = $_SESSION['serendipityEmail'];
              $serendipity['authorid']                = $_SESSION['serendipityAuthorid'];
              $serendipity['serendipityUserlevel']    = $_SESSION['serendipityUserlevel'];
              $serendipity['serendipityAuthedUser']   = $_SESSION['serendipityAuthedUser'];
              $serendipity['serendipityRightPublish'] = $_SESSION['serendipityRightPublish'];
              serendipity_load_configuration($serendipity['authorid']);
              return true;
         }
         return false;
    }

    function authenticate_openid($getData, $store_path, $returnData = false) {
         global $serendipity;

        $path_extra = dirname(__FILE__).DIRECTORY_SEPARATOR.'PHP-openid';
        $path = ini_get('include_path');
        $path = $path_extra . PATH_SEPARATOR . $path;
        ini_set('include_path', $path);
        require_once("Auth/OpenID/Consumer.php");
        require_once("Auth/OpenID/FileStore.php");
        $store = new Auth_OpenID_FileStore($store_path);
        $consumer = new Auth_OpenID_Consumer($store);
        $response = $consumer->complete($getData);
        if ($response->status == Auth_OpenID_CANCEL) {
            $msg = 'Verification cancelled.';
        } else if ($response->status == Auth_OpenID_FAILURE) {
            $msg = "OpenID authentication failed: " . $response->message;
        } else if ($response->status == Auth_OpenID_SUCCESS) {
            $openid = $response->identity_url;
            $esc_identity = htmlspecialchars($openid, ENT_QUOTES);
            $msg = sprintf('You have successfully verified ' .
                               '<a href="%s">%s</a> as your identity.',
                               $esc_identity, $esc_identity);

            if ($response->endpoint->canonicalID) {
                $msg .= '  (XRI CanonicalID: '.$response->endpoint->canonicalID.') ';
            }

            $sreg = $response->extensionResponse('sreg');
        
            if (@$sreg['email']) {
                $msg .= "  You also returned '".$sreg['email']."' as your email.";
                $email = $sreg['email'];
            }
            if (@$sreg['fullname']) {
                $msg .= "  Your name is '".$sreg['fullname']."'";
                $realname = $sreg['fullname'];
            } else {
                $realname = 'Anonymous';
            }
        }

        if (! empty($openid)) {
            if ($returnData) {
                return array('realname'=>$realname, 'email'=>$email, 'openID'=>$openid);
            }
            $password = md5($openid);
            $query = "SELECT DISTINCT a.email, a.authorid, a.userlevel, a.right_publish
                     FROM
                       {$serendipity['dbPrefix']}authors AS a, {$serendipity['dbPrefix']}openid_authors AS oa
                     WHERE
                       oa.openid_url = '".serendipity_db_escape_string($openid)."' and 
                       oa.authorid = a.authorid";
           $row = serendipity_db_query($query, true, 'assoc');
           if (is_array($row)) {
               serendipity_setCookie('old_session', session_id());
               serendipity_setAuthorToken();
               $_SESSION['serendipityUser']        = $serendipity['serendipityUser']         = $realname;
               $_SESSION['serendipityPassword']    = $serendipity['serendipityPassword']     = $password;
               $_SESSION['serendipityEmail']       = $serendipity['serendipityEmail']        = $email;
               $_SESSION['serendipityAuthorid']    = $serendipity['authorid']                = $row['authorid'];
               $_SESSION['serendipityUserlevel']   = $serendipity['serendipityUserlevel']    = $row['userlevel'];
               $_SESSION['serendipityAuthedUser']  = $serendipity['serendipityAuthedUser']   = true;
               $_SESSION['serendipityRightPublish']= $serendipity['serendipityRightPublish'] = $row['right_publish'];
               $_SESSION['serendipityOpenID'] = true;
               serendipity_load_configuration($serendipity['authorid']);
               return true;
           } else {
               $_SESSION['serendipityAuthedUser'] = false;
               @session_destroy();
           }
        }
        return false;
   }

    function getOpenID($userID, $checkExist=false) {
        global $serendipity;
        $q = "SELECT openid_url, authorid FROM {$serendipity['dbPrefix']}openid_authors WHERE authorid = " . (int)$userID;
        $author = serendipity_db_query($q, true);
        if (is_array($author)) {
            if ($checkExist) {
                return $author['authorid'];
            } elseif (! empty($author['openid_url'])) {
                return $author['openid_url'];
            }
        }
        return '';
    }

    function updateOpenID($openid_url, $authorID) {
        global $serendipity;

        if (!is_array(serendipity_db_query("SELECT username FROM {$serendipity['dbPrefix']}openid_authors LIMIT 1", true, 'both', false, false, false, true))) {
            serendipity_db_schema_import("CREATE TABLE {$serendipity['dbPrefix']}openid_authors (
              openid_url varchar(255) default null,
              hash varchar(32) default null,
              authorid int(11) default '0'
            );");
        }

        $hash = md5($openid_url);
        if (serendipity_common_openid::getOpenID($authorID, true)) {
            $retVal = serendipity_db_update('openid_authors',
                                            array('authorid'=>$authorID),
                                            array('openid_url'=> $openid_url,
                                                  'hash'=> $hash));
        } else {
            $retVal = serendipity_db_insert('openid_authors',
                                            array('openid_url'=> $openid_url,
                                            'hash'=> $hash,
                                            'authorid'=>$authorID));
        }
        return ($retVal===true)?true:false;
    }

    function loginform($url, $hidden = array(), $instructions = '') {
        $form = '';
        if (! empty($instructions)) {
            $form = $instructions . '<br /><br />';
        }
        $form .= '<form name="openid" id="openid" method="post" action="' . $url . '">'.
                  "\n   ".'   <input type="hidden" name="serendipity[openidflag]" value="1" />'."\n   ";
        foreach($hidden AS $key => $val) {
            $form .= '<input type="hidden" name="serendipity[' . $key . ']" value="' . htmlspecialchars($val) . '" />' . "\n";
        }
        $form .= "<center>\n".'	OpenID: <input type="text" size="40" name="serendipity[openid_url]" value="" />'."\n".
             '<input type="submit" name="openIDLogin" value="Login" /></form>';
        return $form;
    }
}
