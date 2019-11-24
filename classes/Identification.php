<?php

namespace LibLynx;

class Identification
{
    public $url;
    public $referrer;
    public $user_agent;
    public $ip;
    public $status;
    public $unit_requests;

    public static function fromRequest()
    {
        $id = new Identification;
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $id->ip = $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
            $id->referrer = $_SERVER['HTTP_REFERER'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $id->user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['HTTP_HOST'])) {
            $id->url = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $id->url .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        $id->unit_requests=array();

        return $id;
    }

    public static function fromJSON($response)
    {
        $id = new Identification;
        $id->updateFromJSON($response);
        return $id;
    }

    public function addAuthorizationRequest($authorizationUnit, $right='view')
    {
        $this->unit_requests[]= array(
            'unit_code' => $authorizationUnit,
            'operations' => array(
                array('type' => $right)
            )
        );
    }

    public function updateFromJSON($response)
    {
        $vars = get_object_vars($response);
        foreach ($vars as $name => $value) {
            $this->$name = $value;
        }
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function toJSON()
    {
        $data = get_object_vars($this);
        foreach ($data as $key => $val) {
            if (is_null($val)) {
                unset($data[$key]);
            }
        }
        return json_encode($data);

    }

    public function isIdentified()
    {
        return $this->status == 'identified';
    }

    public function mustAgreeTerms()
    {
        return isset($this->terms) && ($this->terms==='not-agreed');
    }

    public function setTermsAgreed()
    {
        $this->terms='agreed';
    }

    public function getTermsUrl()
    {
        return (($this->mustAgreeTerms()) && isset($this->_links->terms->href)) ? $this->_links->terms->href : null;
    }

    public function requiresWayf()
    {
        return $this->status == 'wayf';
    }

    public function getAccountName()
    {
        if ($this->status == 'identified') {
            return $this->account->account_name;
        } else {
            return null;
        }
    }

    /**
     * Get the publisher's identifier for an account
     * @return string
     */
    public function getAccountIdentifier()
    {
        if ($this->status == 'identified') {
            return isset($this->account->publisher_reference) ? $this->account->publisher_reference : null;
        } else {
            return null;
        }
    }

    public function getId()
    {
        return isset($this->id) ? $this->id : null;
    }

    public function getLink($linkName)
    {
        return isset($this->_links->$linkName->href) ? $this->_links->$linkName->href : null;
    }

    public function getWayfUrl()
    {
        return (($this->status == 'wayf') && isset($this->_links->wayf->href)) ? $this->_links->wayf->href : null;
    }

    public function getUnauthorizedUrl($wantedUnit)
    {
        $url=null;
        if (isset($this->_links->unauthorized->href))
        {
            $url=$this->_links->unauthorized->href;
            $url.="?unit=".urlencode($wantedUnit);
        }
        return $url;
    }



    public function doWayfRedirect()
    {
        $url = $this->getWayfUrl();
        if (!is_null($url)) {
            header("Location: $url");
            exit;
        }
    }

    public function getAuthorization($unitContent, $type = 'view')
    {
        if (isset($this->authorizations->$unitContent->$type)) {
            return $this->authorizations->$unitContent->$type;
        } else {
            return null;
        }
    }

    public function getAuthorizedUnits($type = 'view')
    {
        $units=array();
        if (isset($this->authorizations)){

            foreach($this->authorizations as $unit=>$auth) {

                if ($auth->$type=='authorized') {
                    $units[]=$unit;
                }
            }
        }

        return $units;
    }
}
