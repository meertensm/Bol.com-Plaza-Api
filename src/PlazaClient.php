<?php namespace MCS;
 
use Exception;
use SimpleXMLElement;
use GuzzleHttp\Psr7\Request;

class PlazaClient{
  
    private $httpMethod = 'GET';
    
    private $test = false;
    
    private $keys = [
        'public' => null,
        'private' => null,
    ];
    
    private $url = null;
    
    private $uri = null;
    
    const API_VERSION = 'v1';
    const TEST_URL = 'https://test-plazaapi.bol.com:443';
    const URL = 'https://plazaapi.bol.com:443';
    const CONTENT_TYPE = 'application/xml';

    /**
     * @param string  $key1 
     * @param string  $key2 
     * @param boolean $test
     */
    public function __construct($key1, $key2, $test = false)
    {
        $this->test = $test;
        $this->url = ( $test ? self::TEST_URL : self::URL );
        $this->keys['public'] = preg_replace('/\s+/', '', $key1);
        $this->keys['private'] = preg_replace('/\s+/', '', $key2);
    }
    
    /**
     * Set the api resource
     * @param string $resource
     */
    public function setResource($resource)
    {
        switch ($resource) {
            case 'orders':
                $this->uri = '/services/rest/orders/' . self::API_VERSION . '/open';
                break;
            case 'process':
                $this->httpMethod = 'POST';
                $this->uri = '/services/rest/orders/' . self::API_VERSION . '/process';
                break;
            default:
                throw new Exception('Unknown resource');
        }
    }
        
    /**
     * Build the authorisation header and call the webservice
     * @param  string [$post = null] If method is POST
     * @return object
     */
    public function call($post = null)
    {
        try{

            $date = gmdate('D, d M Y H:i:s T');
            $signature = implode(PHP_EOL, [
                $this->httpMethod . PHP_EOL,
                self::CONTENT_TYPE,
                $date,
                "x-bol-date:" . $date,
                $this->uri
            ]);

            $signature = $this->keys['public'] . ':' 
                . base64_encode(
                    hash_hmac(
                        'SHA256', 
                        $signature, 
                        $this->keys['private'], 
                        true
                    )
                );	

            $client = new \GuzzleHttp\Client();  

            $headers = [
                'Content-type' => self::CONTENT_TYPE,
                'X-BOL-Date' => $date,
                'X-BOL-Authorization' => $signature
            ];    

            $request = new Request($this->httpMethod, $this->url . $this->uri, $headers, $post);   
           
            $response = $client->send($request);
    
            $a = simplexml_load_string($response->getBody());

            $body = $response->getBody();
            $body->seek(0);
            $size = $body->getSize();
            $file = $body->read($size);

            $data = simplexml_load_string(str_replace('bns:', '', $file));

            return $data;
            
        }
        catch(Exception $e){
            throw new Exception($e->getMessage());
        }
        catch(ClientException $e){
            throw new Exception($e->getMessage()); 
        }
    }
}