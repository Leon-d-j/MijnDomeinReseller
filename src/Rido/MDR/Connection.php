<?php

namespace Rido\MDR;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rido\MDR\Exceptions\ApiException;

class Connection
{
    /**
     * @var string
     */
    private $apiUrl = 'https://manager.mijndomeinreseller.nl/api/';

    /**
     * Always use md5.
     *
     * @var string
     */
    private $authType = 'md5';

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $responseData;

    /**
     * @param       $command
     * @param array $additionalParams
     *
     * @return array
     */
    public function get($command, array $additionalParams = [])
    {
        $this->responseData = $additionalParams;

        $request = $this->createRequest($command, $additionalParams);
        $response = $this->client()->send($request);

        return $this->parseResponse($response);
    }

    /**
     * @param string $command
     * @param array  $attributes
     *
     * @return array
     */
    public function put($command, array $attributes)
    {
        $request = $this->createRequest($command, $attributes);
        $response = $this->client()->send($request);

        return $this->parseResponse($response);
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @param bool   $passwordIsMd5
     */
    public function setPassword($password, $passwordIsMd5 = false)
    {
        $this->password = $passwordIsMd5 ? $password : md5($password);
    }

    /**
     * @param        $command
     * @param array  $additionalParams
     * @param string $method
     *
     * @return Request
     */
    private function createRequest($command, array $additionalParams = [], $method = 'GET')
    {
        $url = $this->generateUrl($command, $additionalParams);

        $request = new Request($method, $url);

        return $request;
    }

    /**
     * @param string $command
     * @param array  $additionalParams
     *
     * @return string
     *
     * @internal param null|string $type
     * @internal param array $additionalAttributes
     */
    private function generateUrl($command, array $additionalParams = [])
    {
        $params = [
            'user'     => $this->username,
            'pass'     => $this->password,
            'authtype' => $this->authType,
            'command'  => $command,
        ];

        $params = array_merge($params, $additionalParams);

        return $this->apiUrl.'?'.http_build_query($params);
    }

    /**
     * @return Client
     */
    private function client()
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new Client([
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        return $this->client;
    }

    /**
     * @param Response $response
     *
     * @throws ApiException
     *
     * @return array
     */
    private function parseResponse(Response $response)
    {
        try {
            $content = utf8_encode($response->getBody()->getContents());

            $content = str_replace("\n", '<br>', $content);

            $lines = explode('<br>', $content);

            if ($lines && $this->parseResponseLines($lines)) {
                return $this->responseData;
            } else {
                throw new ApiException('No response');
            }
        } catch (Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * @param array $lines
     *
     * @throws ApiException
     *
     * @return bool
     */
    private function parseResponseLines(array $lines)
    {
        $errors = [];



        foreach ($lines as $line) {
            $exp = explode('=', $line, 2);

            if (count($exp) == 2) {
                if (strpos($exp[0], 'errnotxt') === 0) {
                    $errors[] = $exp[1];
                } elseif ($exp[0] != 'errcount' && $exp[0] != 'done' && strpos($exp[0], 'errno') === false) {
                    $this->parseLine($exp);
                }
            }
        }

        if (!$errors) {
            return true;
        } else {
            throw new ApiException(implode('|', $errors));
        }
    }

    /**
     * @param array $line
     */
    private function parseLine(array $line)
    {
        if (preg_match('/^([A-Za-z_]+)\[(\d+)\]/', $line[0], $matches) && count($matches) == 3) {
            $this->responseData['items'][$matches[2]][$matches[1]] = trim($line[1]);
        } else {
            $this->responseData[$line[0]] = trim($line[1]);
        }
    }
}
