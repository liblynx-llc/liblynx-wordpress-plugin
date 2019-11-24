<?php
/**
 * @package Liblynx_Connect
 * @version 1.0
 */
/*
Plugin Name: LibLynx Connect
Plugin URI: http://www.liblynx.com/
Description: Allows access control to content to be managed with LibLynx Connect, making it easy to provide authenticated access via IP address, library card, Shibboleth, and other mechanisms.
Author: LibLynx LLC
Version: 1.1
Author URI: http://www.liblynx.com/
*/

defined('ABSPATH') or die("Invalid request");

require_once(dirname(__FILE__).'/classes/Client.php');
require_once(dirname(__FILE__).'/classes/Identification.php');
require_once(dirname(__FILE__).'/classes/Logger.php');
require_once(dirname(__FILE__).'/settings.php');
require_once(dirname(__FILE__).'/metabox.php');

function liblynx_start_session(){
    if( !session_id() )
        session_start();
}
add_action('init','liblynx_start_session');


function liblynx_template_redirect()
{
    /*
    if (is_user_logged_in()) {
        return
    }
    */

    //init logger based on session log level
    if (!isset($_SESSION['lllog'])){
        //todo - initialize base on system settings
        $_SESSION['lllog']='debug';
    }

    $logfile=dirname(__FILE__).'/liblynx.log';
    $logger=new LibLynx\Logger($logfile, $_SESSION['lllog']);

    $liblynx_setting=get_option('liblynx_protect', 'nothing');

    //get Liblynx settings for th epost
    $postId=get_the_ID();
    $value = get_post_meta($postId, '_liblynx_protect', false);
    $enabled=is_array($value) && $value[0];

    //no protection enabled?
    if ($liblynx_setting == 'nothing') {
        return;
    }

    //if untagged mode is used, then if the post has the liblynx box checked, it's free to view...
    if (($liblynx_setting == 'untagged') && $enabled) {
        return;
    }

    //for tagged mode, let it go if not checked...
    if (($liblynx_setting == 'tagged') && !$enabled) {
        return;
    }

    //if we reach here, the content is protected via LibLynx
    //$logger->debug("postId $postId _liblynx_protect = ".json_encode($value));

    //get unit code associate with post
    $unitMeta = get_post_meta($postId, '_liblynx_unit', '');
    $unit=is_array($unitMeta) ? $unitMeta[0] : '';
    if (empty($unit)) {
        $unit=get_option('liblynx_unit_code');
    }

    $requestIdentifier=session_id();
    $logger->debug("must authorize access to post $postId requiring unit $unit ($requestIdentifier)");

    $identification=isset($_SESSION['llid']) ? $_SESSION['llid'] : null;
    if ($identification) {

        //see if unit is authorized in this session
        if (isset($identification->authorizations->$unit->view) &&
            ($identification->authorizations->$unit->view=='authorized')) {
            //excellent, we are good to go
            $logger->info("access to $unit authorized by identification in session ($requestIdentifier)");
            return;
        }

        //are we denied?
        if (isset($identification->authorizations->$unit->view) &&
            ($identification->authorizations->$unit->view=='unauthorized')) {

            $logger->notice("access to $unit not authorized by identification in session ($requestIdentifier)");

            $url=$identification->getUnauthorizedUrl($unit);
            wp_redirect($url);
            exit;
        }

        //this plugin is intended for fairly simple use cases but if a complex unit structure is in play, it would
        //be possible to have a final API call here to see if we get get authorization for the desired unit. As this
        //is rarely required in a Wordpress installation, this has not yet been implemented.
        $logger->emergency("need to perform additional authorization but not implemented yet ($requestIdentifier)");
        die('Authorization not configured - please contact techncial support');
    }

    //if we reach here, we're going to perform a new authorization
    $api_key = get_option('liblynx_api_key');
    $api_secret = get_option('liblynx_api_secret');
    $client=new LibLynx\Client($api_key, $api_secret);

    $request=LibLynx\Identification::fromRequest();

    //TODO - check for robots

    //there's a bug in the API where a request with a unit causes a crash
    //when used to transfer...
    if (strpos($request->url, '_llca')===false) {
        $request->addAuthorizationRequest($unit);
    }

    $identification=$client->authorize($request);

    if (isset($identification->status) &&
        ($identification->status=='identified')) {

        $_SESSION['llid']=$identification;

        //we know who you are - can you see this content?
        if (isset($identification->authorizations->$unit->view) &&
        ($identification->authorizations->$unit->view=='authorized')) {
            $logger->info("new identification request for $unit successfully authorized ($requestIdentifier)");
            //excellent, we are good to go
            return;
        } else {
            $logger->notice("new identification request succeeded, but request for $unit not authorized ($requestIdentifier)");
            //denied...we can redirect to the LibLynx denial page
            $url=$identification->getUnauthorizedUrl($unit);
            wp_redirect($url);
            exit;
        }
    }

    if (isset($identification->status) &&
        ($identification->status=='wayf')) {

        $url=$identification->getWayfUrl();

        $logger->info("redirecting to WAYF $url ($requestIdentifier)");

        wp_redirect($url);
        exit;
    }

    //to reach here is a complete failure...

    exit;
}
add_action('template_redirect', 'liblynx_template_redirect');


// [if_liblynx unit=XXX right=view]
function liblynx_tag_if_liblynx( $atts, $content ) {

    //this allows for a rights check - we'll have already done a content unit check, but we could
    //add that for pages which need conditional content
    if (!isset($_SESSION['llid'])) {
        //not authenticated, therefore no rights
        return false;
    }

    if (!isset($atts['unit'])) {
        $postId = get_the_ID();
        $value = get_post_meta($postId, '_liblynx_protect', false);
        $enabled = is_array($value) && $value[0];
        if ($enabled) {
            $unitMeta = get_post_meta($postId, '_liblynx_unit', '');
            $unit = is_array($unitMeta) ? $unitMeta[0] : '';
            if (!empty($unit)) {
                //use unit from post
                $atts['unit'] = $unit;
            }
        }
    }
    //use global default
    if (!isset($atts['unit'])) {
        $atts['unit']=get_option('liblynx_unit_code');
    }

    $unit=$atts['unit'];
    $mustHaveUnit=true;

    //assume view right if not specified
    $right=isset($atts['right']) ? $atts['right'] : 'view';
    $mustHaveRight=true;

    if (substr($unit,0,1) == '!') {
        //check that unit is NOT authorized
        $unit=substr($unit, 1);
        $mustHaveUnit=false;
    }

    if (substr($right,0,1) == '!') {
        //check that unit is NOT authorized
        $right=substr($right, 1);
        $mustHaveRight=false;
    }

    //$debug="<div><pre><code>unit=$unit right=$right authorized=$authorized\n".json_encode($_SESSION['llid'],JSON_PRETTY_PRINT)."</code></pre></div>";
    //$debug="<div><pre><code>unit $unit ($mustHaveUnit) right $right ($mustHaveRight)</code></pre></div>";

    $showContent=false;
    if ($mustHaveUnit && $mustHaveRight) {
        //check unit has relevant right
        $showContent = isset($_SESSION['llid']->authorizations->$unit->$right) &&
            $_SESSION['llid']->authorizations->$unit->$right === 'authorized';
    }
    elseif ($mustHaveUnit && !$mustHaveRight) {
        //make sure that either we don't have the unit, or that we don't that the right
        $showContent = (!isset($_SESSION['llid']->authorizations->$unit)) ||
            (!isset($_SESSION['llid']->authorizations->$unit->$right)) ||
            ($_SESSION['llid']->authorizations->$unit->$right !== 'authorized');
    }
    else {
        //make sure unit does not have *any* authorized rights
        $showContent=true;
        if (isset($_SESSION['llid']->authorizations->$unit)) {
            foreach ($_SESSION['llid']->authorizations->$unit as $right=>$setting) {
                if ($setting === 'authorized') {
                    $showContent=false;
                }
            }
        }
    }

    if ($showContent) {
        $body = do_shortcode($content);
    } else {
        $body = '';
    }

    if (isset($debug) && !empty($debug)) {
        $body.="<div><pre><code>$debug</code></pre></div>";
    }

    return $body;
}
add_shortcode( 'if_liblynx', 'liblynx_tag_if_liblynx' );

function liblynx_tag_logout( $atts, $content ) {
    //we just need to forget the session...
    unset($_SESSION['llid']);

    //tag can be empty, but if you do embed anything, we'll show it...
    return do_shortcode($content);
}
add_shortcode( 'liblynx_logout', 'liblynx_tag_logout' );

