<?php

namespace Italia\Spid\Spid\Saml\In;

use Italia\Spid\Spid\Saml\SignatureUtils;

/*
* Generates the proper response object at runtime by reading the input XML.
* Validates the response and the signature
* Specific response may complete other tasks upon succesful validation
* such as creating a login session for Response, or destroying the session 
* for Logout resposnes.

* The only case in which a Request is validated instead of a response is
* for Idp Initiated Logout. In this case the input is not a response to a requese
* to a request sent by the SP, but rather a request started by the Idp
*/
class BaseResponse
{
    var $response;
    var $xml;

    public function __construct()
    {
        if (!isset($_POST) || !isset($_POST['SAMLResponse'])) {
            return;
        }
        $xmlString = base64_decode($_POST['SAMLResponse']);
        $this->xml = new \DOMDocument();
        $this->xml->loadXML($xmlString);

        $root = $this->xml->documentElement->tagName;
        
        switch ($root) {
            case 'samlp:Response':
                // When reloading the acs page, POST data is sent again even if login is completed
                // If login session already exists exit without checking the response again
                if (isset($_SESSION['spidSession'])) return;
                $this->response = new Response();
                break;
            case 'samlp:LogoutResponse':
                $this->response = new LogoutResponse();
                break;
            case 'samlp:LogoutRequest':
                $this->response = new LogoutRequest();
                break;
            default:
                throw new \Exception('No valid response found');
                break;
        }
    }

    public function validate($cert) : bool
    {
        if (is_null($this->response)) {
            return true;
        }
        $signatures = $this->xml->getElementsByTagName('Signature');
        if ($signatures->length == 0) throw new \Exception("Invalid Response. Response must contain at least one signature");

        $hasAssertion = $this->xml->getElementsByTagName('Assertion')->length > 0;
        $responseSignature = null;
        $assertionSignature = null;
        foreach ($signatures as $key => $item) {
            if ($item->parentNode->nodeName == 'saml:Assertion') $assertionSignature = $item;
            if ($item->parentNode->nodeName == $this->xml->firstChild->nodeName) $responseSignature = $item;
        }
        if ($hasAssertion && is_null($assertionSignature)) throw new \Exception("Invalid Response. Assertion must be signed");
        
        if (!SignatureUtils::validateXmlSignature($responseSignature, $cert) || !SignatureUtils::validateXmlSignature($assertionSignature, $cert))
            throw new \Exception("Invalid Response. Signature validation failed");
        return $this->response->validate($this->xml);
    }
}
