<?php
require_once(__DIR__.'/phpseclib/Math/BigInteger.php');
require_once(__DIR__.'/phpseclib/Crypt/RSA.php');
class HilinkConnector
{
    const HILINK_BASEURL = 'http://192.168.8.1';
    const HILINK_URL_TOKENS = '/api/webserver/SesTokInfo';
    const HILINK_URL_PUBKEY = '/api/webserver/publickey';
    const HILINK_URL_MONITORINGSTATUS = '/api/monitoring/status';
    const HILINK_URL_CONVERGEDSTATUS = '/api/monitoring/converged-status';
    const HILINK_URL_SIGNAL = '/api/device/signal';
    const HILINK_URL_OPERATE = '/api/pin/operate';
    const HILINK_URL_SAVEPIN = '/api/pin/save-pin';
    const HILINK_URL_PINSTATUS = '/api/pin/status';
    const HILINK_URL_INFOS = '/api/device/information';
    const HILINK_SUPPORTED_DEVICES = ['E3372'];
    const CURLOPTS = array(
        CURLOPT_RETURNTRANSFER => true,   // return content
        CURLOPT_HEADER         => false,  // don't return headers
        CURLOPT_CONNECTTIMEOUT => 5,    // time-out on connect : 5 seconds
    );

    //curl_setopt($process, CURLOPT_TIMEOUT, 30); 
    public $SessionID = "";
    public $SessionToken = "";
    public $PublicKey = NULL;
    public $headers = array();

    public $warnings = array();

    function __construct(){

        $xmltokens = self::get(self::HILINK_BASEURL . self::HILINK_URL_TOKENS);
        if($xmltokens == false){
            throw new RuntimeException('No compatible device found');
        }

        $tokens = new SimpleXMLElement($xmltokens);
        $this->SessionToken = $tokens->TokInfo;
        $this->SessionID = substr($tokens->SesInfo, strpos($tokens->SesInfo, '=')+1);

        $this->headers[] = 'Cookie: SessionID=' .$this->SessionID;
        $this->headers[] = '__RequestVerificationToken: '.$this->SessionToken; 
        $this->headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';

        $xmlinformations = self::get(self::HILINK_BASEURL.self::HILINK_URL_INFOS);
        $informations = new SimpleXMLElement($xmlinformations);
        if(strcasecmp($informations->Classify, "hilink")){
            throw new RuntimeException('Device found but not compatible');
        }

        if(!in_array($informations->DeviceName, self::HILINK_SUPPORTED_DEVICES)){
            $this->warnings[] = "Device not in supported devices list";
        }
    }

    public function get($url){
        $curl = curl_init($url);
        curl_setopt_array($curl,self::CURLOPTS);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        $ret = curl_exec($curl);
        return $ret;
        
    }

    public function post($url, $content){
        $curl = curl_init($url);
        curl_setopt_array($curl,self::CURLOPTS);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        $ret = curl_exec($curl);
        return $ret;
    }

    public function get_public_key(){
        $xmlPubKey = self::get(self::HILINK_BASEURL.self::HILINK_URL_PUBKEY);
        $pubkey = new SimpleXMLElement($xmlPubKey);
        
        $modulus = new Math_BigInteger($pubkey->encpubkeyn, 16);
        $exponent = new Math_BigInteger($pubkey->encpubkeye, 16);

        $rsa = new Crypt_RSA();
        $rsa->loadkey(array('n' => $modulus, 'e' => $exponent));
        $rsa->setPublicKey();
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $this->PublicKey = $rsa;
    }
    public function post_encrypted($url, $cleartext){
        if(!isset($this->PublicKey) || $this->PublicKey == NULL ){
            self::get_public_key();
        }

        $b64cleartext = base64_encode($cleartext);
        $encrypted = $this->PublicKey->encrypt($b64cleartext);
        $hexencrypted = bin2hex($encrypted);
        if(strlen($hexencrypted) & 1 != 0){
            $hexencrypted .= '0';
        }

        $curl = curl_init($url);
        curl_setopt_array($curl,self::CURLOPTS);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge($this->headers, array('encrypt_transmit: encrypt_transmit')));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $hexencrypted);
        $ret = curl_exec($curl);
        return $ret;
    }

    function validate_pin($pincode){
        $request = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request></request>');
        $request->addChild('OperateType', 0);
        $request->addChild('CurrentPin', $pincode);
        $request->addChild('NewPin');
        $request->addChild('PukCode');
        $ret = self::post_encrypted(self::HILINK_BASEURL.self::HILINK_URL_OPERATE, $request->asXML());
        return $ret;
    }
} 

