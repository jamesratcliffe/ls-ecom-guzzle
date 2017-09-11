<?php

namespace LightspeedHQ\Ecom;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

/**
* EcomClient is an extension of the Guzzle HTTP client for Lightspeed eCommerce.
*
* Middleware is added to the client to handle rate limiting and retry in case
* of a temporary connection error.
*/
class EcomClient extends Client
{
    const CLUSTERS = [
        'EU1' => 'https://api.webshopapp.com/',
        'US1' => 'https://api.shoplightspeed.com/'
    ];

    public $last_req_time;
    private $rate_limit_remaining = [300, 3000, 12000];
    private $rate_limit_reset = [];

    /**
     * The constructor takes the account ID and a refresh token and OAuth
     * client credentials. New access tokens will be requested as needed.
     *
     * @param string $account_id The Lightspeed Retail account ID
     * @param string $refresh_token A refresh token for that account.
     * @param string $client_id The OAuth client ID.
     * @param string $client_secret The OAuth client secret.
     */
    public function __construct($cluster, $language, $key, $secret)
    {
        $host = self::CLUSTERS[strtoupper($cluster)];

        parent::__construct([
            'base_uri' => $host . $language . '/',
            'auth' => [$key, $secret],
            'handler' => $this->createHandlerStack()
        ]);
    }

    /**
    * Builds the HandlerStack for use by the Guzzle Client.
    *
    * It uses the default stack, plus:
    *
    * - A retry Handler defined by retryDecider() and retryDelay().
    * - A request mapper that adds the current access token to each request.
    * - A request mapper to run checkBucket() before each request.
    * - A response mapper to get the new bucket level after each response.
    */
    protected function createHandlerStack()
    {
        $stack = HandlerStack::create(new CurlHandler());
        // RetryMiddleware handles errors (including token refresh)
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        // Add Authorization header with current access token
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            $uri = $request->getUri();
            $path = $uri->getPath();
            $path .= '.json';
            $uri = $uri->withPath($path);
            return $request->withUri($uri);
        }));
        // Check rate limits before sending
        $stack->push(Middleware::mapRequest($this->checkRateLimit()));
        // After response, get rate limits state
        $stack->push(Middleware::mapResponse($this->getRateLimit()));
        return $stack;
    }

    /**
    * A middleware method to decide when to retry requests.
    *
    * This will run even for sucessful requests. We want to retry up to 5 times
    * on connection errors (which can sometimes come back as 502, 503 or 504
    * HTTP errors) and 429 Too Many Requests errors.
    *
    * @return callable
    */
    protected function retryDecider()
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            RequestException $exception = null
        ) {
            // Limit the number of retries to 5
            if ($retries >= 5) {
                return false;
            }

            $should_retry = false;
            $log_message = null;
            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                $should_retry = true;
                $log_message = 'Connection Error: ' . $exception->getMessage();
            }
            if ($response) {
                $code = $response->getStatusCode();
                $log_message = 'HTTP Error ' . $code . ":\n" . $response->getBody();
                // 429, 502, 503, 504: try again
                if (in_array($code, [429, 502, 503, 504])) {
                    $should_retry = true;
                }
            }
            if ($should_retry) {
                if ($log_message) {
                    error_log($log_message, 0);
                }
                if ($retries > 0) {
                    error_log('Retry ' . $retries . '…', 0);
                }
            }
            return $should_retry;
        };
    }

    /**
    * A middleware method to decide how long to wait before retrying.
    *
    * For 401 and 429 errors, we don't wait.
    * For connection errors we wait 1 second before the first retry, 2 seconds
    * before the second, and so on.
    *
    * @return callable
    */
    protected function retryDelay()
    {
        return function ($numberOfRetries, ResponseInterface $response = null) {
            // No delay for 401 or 429 responses
            if ($response) {
                $code = $response->getStatusCode();
                if (in_array($code, [429])) {
                    return 0;
                }
            }
            // Increasing delay otherwise
            return 1000 * $numberOfRetries;
        };
    }

    /**
    * A middleware method to check the bucket state before each request.
    *
    * Go through the rate_limit_reset and only keep values where the
    * corresponding value in rate_limit_remaining is 0.
    * If this filter array is non-empty, we take the highest value and
    * sleep for that number of seconds.
    *
    * @return callable
    */
    protected function checkRateLimit()
    {
        return function (RequestInterface $request) {
            $filtered_reset_times = array_filter(
                $this->rate_limit_reset,
                function ($key) {
                    return $this->rate_limit_remaining[$key] <= 0;
                },
                ARRAY_FILTER_USE_KEY
            );
            if (count($filtered_reset_times) > 0) {
                $time_since_last_req = time() - $this->last_req_time;
                $sleep_time = max($filtered_reset_times) - $time_since_last_req;
                if ($sleep_time > 0) {
                    error_log('Sleeping ' . $sleep_time . ' seconds…', 0);
                    sleep($sleep_time);
                }
            }
            return $request;
        };
    }

    /**
    * A middleware method to read the rate limit status from each reponse.
    *
    * The number of requests is limited per 5 minutes, hour and 24 hours.
    * The 2 rate limit reponse headers are strings separated by '/'.
    * The X-RateLimit-Remaining header tells us how many requests are remaining
    * for each time period. The X-RateLimit-Reset header tells us how many
    * seconds remain until the rate limit is reset for each time period.
    * This method turns these headers into arrays and stores them.
    *
    * @return callable
    */
    protected function getRateLimit()
    {
        return function (ResponseInterface $response) {
            $rate_limit_header = $response->getHeader('X-RateLimit-Remaining');
            if (count($rate_limit_header) > 0) {
                $this->rate_limit_remaining = explode('/', $rate_limit_header[0]);
                $this->rate_limit_reset = explode('/', $response->getHeader('X-RateLimit-Reset')[0]);
            }
            $this->last_req_time = time();
            return $response;
        };
    }
}
