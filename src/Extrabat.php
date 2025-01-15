<?php

namespace I5Agency\Extrabat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

class Extrabat
{
    private const VALID_VERBS = ['get', 'post', 'put', 'patch', 'delete'];
    private const TOKEN_URL = 'https://www.myextrabat.com/authentification/oauth2/token';
    private const API_URL = 'https://api.extrabat.com/';

    private string $token;
    private string $clientId;
    private string $clientSecret;
    private Client $client;

    public function __construct(string $clientId, string $clientSecret)
    {
        if (empty($clientId) || empty($clientSecret)) {
            throw new \InvalidArgumentException('Client ID and client secret are required to connect to the Extrabat API.');
        }

        $this->clientId        = $clientId;
        $this->clientSecret    = $clientSecret;

        $this->httpClient       = new Client();
        $this->accessToken      = $this->getAccessToken();
    }

    /**
     * Get access token
     *
     * @param $clientId
     * @param $clientSecret
     * @return false
     * @throws GuzzleException
     */
    private function getAccessToken(): string
    {
        try {
            $response = $this->httpClient->post(self::TOKEN_URL, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['access_token'])) {
                throw new \RuntimeException('Access token not found in response.');
            }

            return $responseData['access_token'];
        } catch (RequestException $e) {
            throw new \RuntimeException('Failed to fetch access token: ' . $e->getMessage());
        }
    }

    /**
     * Make the call to Extrabat, only synchronous calls at present
     *
     * @param string $verb
     * @param string $endpoint
     * @param array $options
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function call(string $verb, string $endpoint, array $options = [], $decodeJson = false): array
    {
        if (!in_array(strtolower($verb), self::VALID_VERBS, true)) {
            throw new \InvalidArgumentException("Invalid HTTP verb: $verb");
        }

        $headers = $options['headers'] ?? [];
        $headers['Authorization'] = 'Bearer ' . $this->accessToken;

        try {
            if ($decodeJson) {
                $body = json_decode($options['body']);
            } else {
                $body = $options['body'] ?? [];
            }
            $response = $this->httpClient->request($verb, self::API_URL . $endpoint, [
                'headers' => $headers,
                'json' => $body ?? [],
            ]);

            return $this->parseResponse($response);
        } catch (RequestException $e) {
            throw new \RuntimeException('API call failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse the response from
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();

        return [
            'code' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $this->isValidJson($body) ? json_decode($body, true) : $body,
        ];
    }

    /**
     * Checks a string to see if it's JSON. True if it is, false if it's not
     *
     * @param string $string
     * @return bool
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get ID by parameter by name
     *
     * @param string $value
     * @param string $name
     * @param $field
     * @param $option
     * @return string|array|null
     * @throws GuzzleException
     */
    public function getIDParameterByName(string $value, string $name, $field = 'libelle', $option = null): string|array|null
    {
        $endpointMap = [
            'civility' => 'v1/parametres/civilites',
            'phone-type' => 'v1/parametres/type-telephone',
            'address-type' => 'v1/parametres/type-adresse',
            'status' => 'v1/parametres/client-statuts',
            'group' => 'v1/parametres/regroupements',
            'question' => 'v1/parametres/questions-complementaires',
            'origine' => 'v1/parametres/origines-contact',
            'users' => 'v1/utilisateurs',
        ];

        if (!isset($endpointMap[$name])) {
            throw new \InvalidArgumentException("Unknown parameter name: $name");
        }

        $response = $this->call('GET', $endpointMap[$name]);

        if (isset($response['body']) && is_array($response['body'])) {
            foreach ($response['body'] as $item) {
                if (isset($item[$field]) && $item[$field] === $value) {
                    if (is_null($option)) {
                        return $item['id'] ?? null;
                    } else {
                        foreach ($item['options'] as $optionItem) {
                            if ($optionItem['optionVal'] === $option) {
                                return [
                                    'questionId' => $item['id'] ?? null,
                                    'valueId' => $optionItem['id'] ?? null,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
