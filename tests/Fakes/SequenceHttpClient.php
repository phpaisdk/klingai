<?php

declare(strict_types=1);

namespace AiSdk\KlingAi\Tests\Fakes;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

final class SequenceHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|Throwable> $responses */
    public function __construct(private array $responses) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? throw new RuntimeException('Unexpected HTTP request.');
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
