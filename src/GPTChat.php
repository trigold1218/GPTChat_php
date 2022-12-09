<?php

namespace Trigold\GptChat;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;

class GPTChat
{
    /**
     * auth key name
     * @var string
     */
    const AUTH_COOKIE_NAME = "__Secure-next-auth.session-token";

    /**
     * base url
     * @var string
     */
    const BASE_URL = 'https://chat.openai.com';

    const DOMAIN = 'chat.openai.com';

    /**
     * auth url
     * @var string
     */
    const AUTH_URI = '/api/auth/session';

    /**
     * conversation url
     * @var string
     */
    const CONV_URL = '/backend-api/conversation';

    /**
     * default user agent
     * @var array
     */
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)  AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36 Edg/108.0.1462.41';

    /**
     * GuzzleHttp\Client
     * @var Client
     */
    protected Client $client;

    /**
     * user session token
     * @var string
     */
    protected string $sessionToken;

    /**
     * Whether to obtain authorization
     * @var bool
     */
    protected bool $authFlag = false;

    /**
     * request token
     * @var string
     */
    protected string $accessToken;

    /**
     * conversation id
     * @var string
     */
    protected string $conversation_id = '';

    /**
     * parent message id
     * @var string
     */
    protected string $parent_message_id = '';

    public function __construct($sessionToken)
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
        ]);
        $this->sessionToken = $sessionToken;
        $this->parent_message_id = Str::orderedUuid()->toString();
    }

    /**
     * get auth token
     * @return $this
     * @throws GuzzleException
     */
    public function authenticate(): GPTChat
    {
        $this->accessToken = $this->getAccessToken();
        $this->authFlag = true;
        return $this;
    }

    /**
     * send message
     *
     * @param $msg
     *
     * @return void
     * @throws Exception|GuzzleException
     */
    public function send_message($msg)
    {
        if (!$this->authFlag) {
            throw new Exception("In order to send messages you have to authenticate first.");
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
            throw new Exception("Authentication failed with code: {$code}");
        }

        $body = $response->getBody();

        while (!$body->eof()){
            echo $body->read(1024);
        }
    }

    /**
     * get cookieJar
     * @return CookieJar
     */
    protected function getAuthCookie(): CookieJar
    {
        return CookieJar::fromArray([
            self::AUTH_COOKIE_NAME => $this->sessionToken,
        ], self::DOMAIN);
    }

    /**
     * get access token
     * @return mixed
     * @throws GuzzleException
     */
    protected function getAccessToken()
    {
        $agent = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) ",
            "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36",
        ];

        $response = $this->client->get(self::AUTH_URI, [
            'headers' => [
                'User-Agent' => implode(' ', $agent),
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

    /**
     * build message
     * @param $message
     * @param $id
     * @param $conv_id
     * @param $parent_msg_id
     *
     * @return array
     */
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

    /**
     * get conversation headers
     * @return string[]
     */
    protected function convHeaders(): array
    {
        return [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer '.$this->accessToken,
            'Content-Type'  => 'application/json',
            'User-Agent'    => self::DEFAULT_USER_AGENT,
        ];
    }

    /**
     * set conversation id
     * @param $conversation_id
     *
     * @return $this
     */
    public function setConversationId($conversation_id): GPTChat
    {
        $this->conversation_id = $conversation_id;
        return $this;
    }

    /**
     * if you think that the conversation is over, you can reset the conversation id
     * @return $this
     */
    public function clearConversationId(): GPTChat
    {
        $this->conversation_id = '';
        return $this;
    }
}