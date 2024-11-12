<?php

declare(strict_types=1);

namespace RPurinton\BlueSkyPHP;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BlueSkyPHP
{
    private string $identifier;
    private string $password;
    private string $pdsHost;
    private string $accessJwt;
    private string $refreshJwt;
    private Client $client;

    public function __construct(string $identifier, string $password, string $pdsHost)
    {
        $this->identifier = $identifier;
        $this->password = $password;
        $this->pdsHost = $pdsHost;
        $this->client = new Client([
            'base_uri' => $this->pdsHost,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function createSession(): void
    {
        $endpoint = '/xrpc/com.atproto.server.createSession';
        $body = [
            'identifier' => $this->identifier,
            'password' => $this->password,
        ];

        try {
            $response = $this->client->post($endpoint, [
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessJwt = $data['accessJwt'];
            $this->refreshJwt = $data['refreshJwt'];
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to create session: ' . $e->getMessage());
        }
    }

    public function createPost(string $text): array
    {
        $this->createSession();
        $endpoint = '/xrpc/com.atproto.repo.createRecord';
        $body = [
            'repo' => $this->identifier,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $text,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessJwt,
                ],
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to create post: ' . $e->getMessage());
        }
    }
}
