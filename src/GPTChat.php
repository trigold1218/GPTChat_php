<?php

namespace Trigold\GptChat;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use GuzzleHttp\Cookie\CookieJar;

class GPTChat
{
    const AUTH_COOKIE_NAME = "__Secure-next-auth.session-token";
    const BASE_URL = 'https://chat.openai.com';
    const AUTH_URI = '/api/auth/session';
    const CONV_URL = '/backend-api/conversation';
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36';

    protected Client $client;
    protected string $sessionToken;
    protected string $parent_message_id;
    protected bool $authFlag = false;
    /**
     * @var string
     */
    protected string $accessToken;
    protected string $conversation_id;

    public function __construct($sessionToken)
    {
        $this->client = new Client([
            'base_url' => self::BASE_URL,
        ]);
        $this->sessionToken = $sessionToken;
        $this->parent_message_id = Str::orderedUuid()->toString();
    }

    public function authenticate(): GPTChat
    {
        $this->accessToken = $this->getAccessToken();
        $this->authFlag = true;
        return $this;
    }


    public function send_message($msg)
    {
        if (!$this->authFlag) {
            throw new \Exception("In order to send messages you have to authenticate first.");
        }

        $data = $this->buildMessage($msg, Str::orderedUuid()->toString(), $this->conversation_id,
            $this->parent_message_id);

        $response =  $this->client->post(self::CONV_URL, [
            'headers'=>$this->convHeaders(),
            'body'=>json_encode($data),
            'stream'=> true,
        ]);
        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \Exception("Authentication failed with code: {$code}");
        }

        $body = $response->getBody();

        while (!$body->eof()){
            echo $body->read(1024);
        }
    }


    protected function getAuthCookie(): CookieJar
    {
        return CookieJar::fromArray([
            self::AUTH_COOKIE_NAME => $this->sessionToken,
        ], self::BASE_URL);
    }

    protected function getAccessToken()
    {
        $response = $this->client->get(self::AUTH_URI, [
            'headers' => [
                'User-Agent' => self::DEFAULT_USER_AGENT,
            ],
            'cookies' => $this->getAuthCookie(),
        ]);

        $code = $response->getStatusCode();
        if ($code !== 200) {
            throw new \Exception("Authentication failed with code: {$code}");
        }

        $result = json_decode($response->getBody()->getContents(), true);
        return $result['accessToken'];
    }


    public function buildMessage($message, $id, $conv_id, $parent_msg_id): array
    {
        $data = [
            'action'            => "next",
            'messages'          => [
                [
                    'id'      => $id,
                    'role'    => "user",
                    'content' => [
                        'content_type' => 'text',
                        'parts'        => [$message],
                    ],
                ],
            ],
            'parent_message_id' => $parent_msg_id,
            'model'             => 'text-davinci-002-render',
        ];

        if ($conv_id) {
            $data['conversation_id'] = $conv_id;
        }
        return $data;
    }

    protected function convHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$this->accessToken,
            'Content-Type'  => 'application/json',
            'User-Agent'    => self::DEFAULT_USER_AGENT,
        ];
    }
}