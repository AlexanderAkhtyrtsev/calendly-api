<?php

namespace App\Custom;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use GuzzleHttp\Client;

class CalendlyApi
{
    /**
     * @const string
     */
    public const EVENT_CREATED = 'invitee.created';

    /**
     * @const string
     */
    public const EVENT_CANCELED = 'invitee.canceled';

    /**
     * @const string
     */
    private const METHOD_GET = 'get';

    /**
     * @const string
     */
    private const METHOD_POST = 'post';

    /**
     * @const string
     */
    private const METHOD_DELETE = 'delete';

    /**
     * @const string
     */
    private const API_URL = 'https://api.calendly.com';

    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client|null $client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?? new Client([
                'base_uri' => self::API_URL,
                'headers'  => [
                    'Authorization' => 'Bearer ' . config('api.calendly.key')
                ],
            ]);
    }

    /**
     * Test authentication token.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function echo(): array
    {
        return $this->callApi(self::METHOD_GET, 'echo');
    }

    /**
     * Create a webhook subscription.
     *
     * @param string $url
     * @param array  $events
     *
     * @return array
     *
     * @throws \Exception
     */
    public function createWebhook($url, $events = []): array
    {
        if (array_diff($events, [self::EVENT_CREATED, self::EVENT_CANCELED])) {
            throw new \Exception('The specified event types do not exist');
        }

        return $this->callApi(self::METHOD_POST, 'webhook_subscriptions', [
            'url'    => $url,
            'events' => $events,
            'organization' => ENV('ORGANIZATION_ID'),
        ]);
    }

    /**
     * Get a webhook subscription by ID.
     *
     * @param int $id
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getWebhook($id): array
    {
        return $this->callApi(self::METHOD_GET, 'webhook_subscriptions/' . $id, [
            'scope' => 'organization',
            'organization' => ENV('ORGANIZATION_ID'),
        ]);
    }

    /**
     * Get list of a webhooks subscription.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getWebhooks(): array
    {
        return $this->callApi(self::METHOD_GET, 'webhook_subscriptions',  [
            'scope' => 'organization',
            'organization' => ENV('ORGANIZATION_ID'),
        ]);
    }

    /**
     * Delete a webhook subscription.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function deleteWebhook($id): void
    {
        try {
            $this->callApi(self::METHOD_DELETE, 'webhook_subscriptions/' . $id);
        } catch (\Exception $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
        }
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     *
     * @return array|null
     *
     * @throws \Exception
     */
    public function callApi($method, $endpoint, array $params = [])
    {
        $url = sprintf('/%s', $endpoint);

        $data = [
            RequestOptions::QUERY => $params,
        ];

        if ($method != self::METHOD_GET) {
            $data = [
                RequestOptions::JSON => $params,
            ];
        }

        try {
            try {
                $response = $this->client->request($method, $url, $data);
            } catch (GuzzleException $e) {
                if ($e instanceof ClientException && $e->getResponse()) {
                    $response = $e->getResponse();
                    $message  = (string)$response->getBody();
                    $headers  = $response->getHeader('content-type');

                    if (count($headers) && strpos($headers[0], 'application/json') === 0) {
                        $message = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        $message = $message['message'];
                    }

                    throw new \Exception( (string)$response->getBody() );
                } else {
                    throw new \Exception('Failed to get Calendly data: ' . $e->getMessage(), $e->getCode());
                }
            }

            $headers = $response->getHeader('content-type');

            if (count($headers) && strpos($headers[0], 'application/json') === 0) {
                $response = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException $e) {
            throw new \Exception('Invalid JSON: ' . $e->getMessage(), 500);
        }

        return $response;
    }
}