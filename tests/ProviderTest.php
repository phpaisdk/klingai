<?php

declare(strict_types=1);
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\SpeechProviderInterface;
use AiSdk\Contracts\VideoProviderInterface;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\KlingAi\KlingAiProvider;
use AiSdk\KlingAi\Models\KlingAiVideoModel;
use AiSdk\Requests\VideoRequest;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class KlingAiFakeClient implements ClientInterface
{
    public ?RequestInterface $request = null;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(200, [], json_encode(['data' => ['task_id' => 'job-1']], JSON_THROW_ON_ERROR));
    }
}
it('exposes every implemented Kling AI capability', function () {
    $p = new KlingAiProvider(new KlingAiOptions(apiKey: 'key'));
    expect($p)->toBeInstanceOf(ImageProviderInterface::class)->toBeInstanceOf(SpeechProviderInterface::class)->toBeInstanceOf(VideoProviderInterface::class);
});
it('starts Kling AI Omni video tasks', function () {
    $c = new KlingAiFakeClient;
    $f = new Psr17Factory;
    $m = new KlingAiVideoModel('kling-v3-omni', new KlingAiOptions(apiKey: 'key', sdk: new Sdk($c, $f, $f)));
    $j = $m->generate(new VideoRequest('A scene'));
    expect($j->id)->toBe('job-1')->and($c->request?->getUri()->getPath())->toBe('/v1/videos/omni-video');
});
