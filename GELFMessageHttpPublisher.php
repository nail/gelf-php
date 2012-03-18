<?php
class GELFMessageHttpPublisher extends GELFMessagePublisher {

    private $protocol;
    private $ch;
    private $curlError;
    private $httpError;

    /**
     * Creates a new publisher that sends errors to a Graylog2 server via HTTP Post
     *
     * @throws InvalidArgumentException
     * @param string $hostname
     * @param integer $port
     */
    public function __construct($hostname, $port = self::GRAYLOG2_DEFAULT_PORT, $protocol = 'http') {
      parent::__construct($hostname, $port);
      $this->protocol = $protocol;
      $url = sprintf("%s://%s:%s/gelf", $this->protocol, $this->hostname, $this->port);
      $this->initChannel($url);
    }

    /**
     * Publishes a GELFMessage, returns false if an error occured during write
     *
     * @throws UnexpectedValueException
     * @param unknown_type $message
     * @return boolean
     */
    public function publish(GELFMessage $message) {
        // Check if required message parameters are set
        if(!$message->getShortMessage() || !$message->getHost()) {
            throw new UnexpectedValueException(
                'Missing required data parameter: "version", "short_message" and "host" are required.'
            );
        }

        // Set Graylog protocol version
        $message->setVersion(self::GRAYLOG2_PROTOCOL_VERSION);

        // Encode the message as json string and compress it using gzip
        $preparedMessage = $this->getPreparedMessage($message);
        if (!$this->sendMessage($preparedMessage)) {
          return false;
        }

        return true;
    }

    /**
     * @param GELFMessage $message
     * @return string
     */
    protected function getPreparedMessage(GELFMessage $message) {
        return json_encode($message->toArray());
    }


    /**
     * @return resource
     */
    protected function initChannel($url) {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($this->ch, CURLOPT_POST, 1); 
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, true);
        return $this->ch;
    }


    /**
     * @return boolean
     */
    protected function sendMessage($message) {
      $this->curlError = null;
      $this->httpError = null;
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $message);
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($message)));
      $result = curl_exec($this->ch);
      if ($result === false) {
        $this->curlError = curl_error($this->ch);
        return false;
      }

      $responseCode = intval(curl_getinfo($this->ch, CURLINFO_HTTP_CODE));
      if ($responseCode !== 200) {
        $this->httpError = $responseCode;
        return false;
      }
      return true;
    }

}
