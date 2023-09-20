<?php

namespace I5Agency;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

class Extrabat
{
    /**
     * An array of valid HTTP verbs.
     */
    private static $valid_verbs = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * An array of valid HTTP verbs.
     */
    private static $token_url = 'https://www.myextrabat.com/authentification/oauth2/token';

    /**
     * @var string The Eventbrite OAuth token.
     */
    private $token;

    /**
     * @var string The Extrabat client ID
     */
    private $client_id;

    /**
     * @var string The Extrabat client secret
     */
    private $client_secret;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function __construct($client_id, $client_secret)
    {
        $this->client_id        = $client_id;
        $this->client_secret    = $client_secret;

        if (! empty($client_id) && ! empty($client_secret)) {
            $this->accessToken   = self::getAccessToken($client_id, $client_secret);
        } else {
            throw new \Exception('Client ID and client Secret is required to connect to the Extrabat API.');
        }
    }

    /**
     * Get access token
     *
     * @param $client_id
     * @param $client_secret
     * @return false
     */
    static function getAccessToken($client_id, $client_secret) {
        $response = self::call('POST', $token_url, [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ]
        ]);


        $test = 'titi';
//        $curl = new \Extrabat\Curl();
//        $curl->setRequestMethod('POST');
//        $curl->setUrl(self::TOKEN_URL);
//        $curl->setPort(self::TOKEN_PORT);
//        $curl->setBody(http_build_query(array (
//            'grant_type'    => 'client_credentials',
//            'client_id'     => $client_id,
//            'client_secret' => $client_secret,
//        )));
//        $response = $curl->exec();
//
//        if (empty($response)) {
//            return FALSE;
//        }
//
//        $response = json_decode($response);
//
//        if (empty($response) || empty($response->access_token)) {
//            return FALSE;
//        }

        return $response->access_token;
    }

    /**
     * Make the call to Extrabat, only synchronous calls at present.
     *
     * @param string $verb
     * @param string $endpoint
     * @param array  $options
     *
     * @return array|mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function call($verb, $endpoint, $options = [])
    {
        if ($this->validMethod($verb)) {
            // Get the headers and body from the options.
            $headers = isset($options['headers']) ? $options['headers'] : [];
            $body = isset($options['body']) ? $options['body'] : null;
            $pv = isset($options['protocol_version']) ? $options['protocol_version'] : '1.1';
            // Make the request.
            $request = new Request($verb, $endpoint, $headers, $body, $pv);
            // Save the request as the last request.
            $this->last_request = $request;
            // Send it.
            $response = $this->client->send($request, $options);
            if ($response instanceof ResponseInterface) {
                // Set the last response.
                $this->last_response = $response;
                // If the caller wants the raw response, give it to them.
                if (isset($options['parse_response']) && $options['parse_response'] === false) {
                    return $response;
                }
                $parsed_response = $this->parseResponse($response);
                return $parsed_response;
            } else {
                // This only really happens when the network is interrupted.
                throw new BadResponseException('A bad response was received.',
                    $request);
            }
        } else {
            throw new \Exception('Unrecognised HTTP verb.');
        }
    }

    /**
     * Checks if the HTTP method being used is correct.
     *
     * @param string $http_method
     *
     * @return bool
     */
    public function validMethod($http_method)
    {
        if (in_array(strtolower($http_method), self::$valid_verbs)) {
            return true;
        }
        return false;
    }

    /**
     * Parses the response from
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array
     */
    public function parseResponse(ResponseInterface $response)
    {
        $body = $response->getBody()->getContents();
        return [
            'code' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => ($this->isValidJson($body)) ? json_decode($body, true) : $body,
        ];
    }

    /**
     * Checks a string to see if it's JSON. True if it is, false if it's not.
     *
     * @param string $string
     *
     * @return bool
     */
    public function isValidJson($string)
    {
        if (is_string($string)) {
            json_decode($string);
            return (json_last_error() === JSON_ERROR_NONE);
        }
        return false;
    }
}