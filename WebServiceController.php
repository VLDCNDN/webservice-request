<?php

namespace Controllers\OWS;

use Illuminate\Support\Facades\Log;

class WebServiceController
{
  public $propertyCode;
  private $protocol;
  private $host;
  private $path;
  private $username;
  private $password;
  private $wsseUsername;
  private $wssePassword;

  function __construct($propertyCode)
  {
    $this->propertyCode = $propertyCode;
    $this->protocol = env('Protocol', 'https');
    $this->host = env('Host');
    $this->path = env('Path');
    $this->username = env('Username');
    $this->password = env('Password');
    $this->wsseUsername = env('WsseUsername');
    $this->wssePassword = env('WssePassword');
  }

  /**
   * @param String $SOAPBody this contain the xml body to be posted
   * @param String $SOAPAction this contain header for the request
   * 
   * @return String|Boolean 
   */
  function sendRequest($SOAPBody, $SOAPAction)
  {

    $log = explode('#', $SOAPAction);
    $baseName = basename($log[0], ".wsdl");

    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');

    $zulutime = date("h:i:s", strtotime('-8 hours'));
    $endDate = date('Y-m-d', strtotime("+1 year"));
    $createdatetime = $currentDate . "T" . $zulutime . "Z";
    $expiredatetime = $endDate . "T" . $zulutime . "Z";
    $transactionId = date('Ymdhis');

    $url = $this->protocol . "://" . $this->host . "/" . $this->path . "/" . $baseName;

    $xml = '<?xml version="1.0" encoding="utf-8"?>
      <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:c="http://webservices.micros.com/og/4.3/Common/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:r="http://webservices.micros.com/og/4.3/Reservation/" xmlns:hc="http://webservices.micros.com/og/4.3/HotelCommon/" xmlns:n="http://webservices.micros.com/og/4.3/Name/">
        <soap:Header>
          <wsse:Security xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <wsse:UsernameToken wsu:Id="UsernameToken-89C29635B49E665F5E157002847059328">
              <wsse:Username>' . $this->wsseUsername . '</wsse:Username>
              <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $this->wssePassword . '</wsse:Password>
            </wsse:UsernameToken>
            <wsu:Timestamp wsu:Id="TS-89C29635B49E665F5E157002847059327">
              <wsu:Created>' . $createdatetime . '</wsu:Created>
              <wsu:Expires>' . $expiredatetime . '</wsu:Expires>
          </wsu:Timestamp>
          </wsse:Security>
          <OGHeader transactionID="' . $transactionId . '" primaryLangID="E" timeStamp="' . $currentDate . 'T' . $currentTime . '" xmlns="http://webservices.micros.com/og/4.3/Core/">
            <Origin entityID="KIOSK" systemType="KIOSK" />
            <Destination entityID="TI" systemType="PMS" />
            <Authentication>
              <UserCredentials>
                <UserName>' . $this->username . '</UserName>
                <UserPassword>' . $this->password . '</UserPassword>
                <Domain>' . $this->propertyCode . '</Domain>
              </UserCredentials>
            </Authentication>
          </OGHeader>
        </soap:Header>
        <soap:Body>' . $SOAPBody . '</soap:Body>
      </soap:Envelope>';

    // Log the request
    Log::info('Opera Request Started: ', ['header' => $SOAPAction, 'body' => $SOAPBody]);

    $curlSession = curl_init();

    curl_setopt($curlSession, CURLOPT_URL, $url);
    curl_setopt($curlSession, CURLOPT_VERBOSE, 1);
    curl_setopt($curlSession, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curlSession, CURLOPT_POST, 1);
    curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curlSession, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'SOAPAction:' . $SOAPAction));
    curl_setopt($curlSession, CURLOPT_POSTFIELDS, $xml);

    if (curl_errno($curlSession)) {

      // Log the request
      Log::error('Opera Request Failed: ', ['header' => $SOAPAction, 'body' => $SOAPBody, 'errormessage' => curl_errno($curlSession)]);
      return false;
    } else {

      $response = curl_exec($curlSession);

      //parsing response to simple xml
      $xml = @simplexml_load_string($response);

      curl_close($curlSession);

      // Log the request
      Log::info('Opera Request Success: ', ['header' => $SOAPAction, 'body' => $SOAPBody]);

      return $xml;
    }
  }
}
