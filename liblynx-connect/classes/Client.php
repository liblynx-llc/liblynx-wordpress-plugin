<?php

namespace LibLynx;

/**
* This is a Wordpress-specific LibLynx API client which uses
* the WP HTTP API for transport and WP transients for storage
* of access tokens and other ephemeral data
*/
class Client
{
    const TRANSIENT_TOKEN='liblynx_token';
    const TRANSIENT_ENTRYPOINT='liblynx_entrypoint';

    protected $key;
    protected $secret;
    protected $apiroot;

    public function __construct($key, $secret)
    {
        $this->key=$key;
        $this->secret=$secret;
        $this->apiroot='http://connect.liblynx.com';
    }

    /**
    * request new token
    */
    protected function newToken()
    {
        $url=$this->apiroot.'/oauth/v2/token';
        $authHdr="Basic ".base64_encode($this->key.':'.$this->secret);

        $response = wp_remote_post(
            $url,
            array(
                'method' => 'POST',
                'timeout' => 15,
                'blocking' => true,
                'headers' => array('Authorization'=>$authHdr),
                'body' => array('grant_type' => 'client_credentials')
            )
        );
        if (isset($response['response']['code']) && ($response['response']['code']==200)) {
            $token=json_decode($response['body']);
            return $token;
        }

        //failed
        return null;
    }

    /**
    * Obtain OAuth token from Wordpress transient, fetching
    * a new one if its expired
    */
    public function getToken()
    {
        $token=get_transient(TRANSIENT_TOKEN, null);
        if (!empty($token)) {
            return $token;
        }

        $oauth=$this->newToken();
        if ($oauth) {
            set_transient(TRANSIENT_TOKEN, $oauth->access_token, $oauth->expires_in-60);
            $token=$oauth->access_token;
        }

        return $token;
    }

    /**
    * URLs are discovered through the entrypoint resource, so code can
    * make calls to @new_identification and we'll figure out what URL to
    * to use. If the url doesn't start with @, then its returned unchanged
    */
    protected function transformUrl($url)
    {
        if ($url[0]=='@') {
            $entrypoint=$this->getEntrypoint();
            if (isset($entrypoint->_links->$url)) {
                $url=$entrypoint->_links->$url->href;
            }
        }
        return $url;
    }

    /**
    * Make OAuth secured API call
    */
    protected function callAPI($url, $method = 'GET', $jsonBody = null)
    {
        $token=$this->getToken();
        $authHdr="Bearer ".$token;

        //tranform the $url if shorthand
        $url=$this->transformUrl($url);

        $params= array(
            'method' => $method,
            'timeout' => 15,
            'blocking' => true,
            'headers' => array(
                'Authorization'=>$authHdr,
                'Accept'=>'application/json'
                )
        );
        if (!is_null($jsonBody)) {
            $params['body']=$jsonBody;
            $params['headers']['Content-Type']='application/json';
        }



        $response = wp_remote_post($url, $params);

        if (isset($response['response']['code']) &&
            ($response['response']['code']>=200) &&
            ($response['response']['code']<300)) {
            $data=json_decode($response['body']);
            return $data;
        }

        return null;
    }

    /**
    * Shorthand method for API GET
    */
    protected function apiGET($url)
    {
        return $this->callAPI($url, 'GET');
    }

     /**
    * Shorthand method for API POST
    */
    protected function apiPOST($url, $jsonBody)
    {
        return $this->callAPI($url, 'POST', $jsonBody);
    }

    public function getEntryPoint()
    {
        $json=get_transient(TRANSIENT_ENTRYPOINT);
        if (!empty($json)) {
            return json_decode($json);
        }

        $url=$this->apiroot.'/api';
        $entrypoint=$this->apiGET($url);
        if ($entrypoint) {
            set_transient(TRANSIENT_ENTRYPOINT, json_encode($entrypoint), 86400);
        }

        return $entrypoint;
    }


    /**
     * Authorize given identification object
     * @param Identification $request
     * @return Identification|null
     */
    public function authorize(Identification $request)
    {
        $identification = null;

        $response = $this->apiPOST('@new_identification', $request->toJSON());
        if (isset($response->id)) {
            $identification = Identification::fromJSON($response);
        } else {
            //failed to authenticate

        }

        return $identification;
    }


}
