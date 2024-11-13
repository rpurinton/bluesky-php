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
        $facets = $this->getFacets($text);
        $endpoint = '/xrpc/com.atproto.repo.createRecord';
        $body = [
            'repo' => $this->identifier,
            'collection' => 'app.bsky.feed.post',
            'record' => [
                'text' => $text,
                'facets' => $facets,
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

    private function getFacets($message)
    {
        // Initialize the facets array
        $facets = [];

        // Use regular expression to find all hashtags
        // The pattern matches '#' followed by one or more word characters (letters, digits, or underscores)
        // The 'u' modifier ensures the pattern works with UTF-8 encoded strings
        $pattern = '/#\w+/u';

        // Use preg_match_all to find all matches
        if (preg_match_all($pattern, $message, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $hashtag = $match[0];       // The matched hashtag, e.g., '#example'
                $offset = $match[1];        // The byte offset position in the string

                // Calculate byteStart and byteEnd
                $byteStart = $this->calculateByteOffset($message, $offset);
                $byteEnd = $this->calculateByteOffset($message, $offset + mb_strlen($hashtag));

                // Build the facet array for this hashtag
                $facet = [
                    'index' => [
                        'byteStart' => $byteStart,
                        'byteEnd' => $byteEnd,
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#tag',
                            'tag' => mb_substr($hashtag, 1), // Remove the '#' character
                        ],
                    ],
                ];

                // Add the facet to the facets array
                $facets[] = $facet;
            }
        }

        return $facets;
    }

    private function calculateByteOffset($string, $charOffset)
    {
        // Get the substring up to the character offset
        $substring = mb_substr($string, 0, $charOffset, 'UTF-8');

        // Return the length in bytes of the substring
        return strlen(mb_convert_encoding($substring, 'UTF-8'));
    }
}
