<?php

declare(strict_types=1);

use AiSdk\Exceptions\AiSdkException;
use AiSdk\Exceptions\APIConnectionException;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\RemoteFetchException;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\KlingAi\Models\KlingAiSpeechModel;
use AiSdk\KlingAi\Tests\Fakes\SequenceHttpClient;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

require_once __DIR__ . '/Fakes/SequenceHttpClient.php';

/**
 * @param list<ResponseInterface|Throwable> $responses
 * @return array{0: KlingAiSpeechModel, 1: SequenceHttpClient}
 */
function audioModel(string $url, array $responses, ?Closure $resolver = null): array
{
    $payload = ['data' => ['task_id' => 'tts-1', 'task_status' => 'succeed', 'task_result' => ['audios' => [['url' => $url]]]]];
    $client = new SequenceHttpClient(array_values([new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR)), ...$responses]));
    $factory = new Psr17Factory();
    $options = new KlingAiOptions(apiKey: 'private-key', sdk: new Sdk($client, $factory, $factory));

    return [new KlingAiSpeechModel('tts', $options, $resolver ?? fn() => ['93.184.216.34']), $client];
}

it('downloads public audio without provider credentials and follows safe redirects', function () {
    [$model, $client] = audioModel('https://media.example/start?signature=secret', [
        new Response(302, ['Location' => 'https://cdn.example/audio']),
        new Response(200, ['Content-Type' => 'audio/mpeg'], 'audio-bytes'),
    ]);
    $result = $model->generate(new SpeechRequest('Hello'));
    expect($result->audio->data)->toBe('audio-bytes')->and($result->audio->mimeType)->toBe('audio/mpeg');
    expect($client->requests)->toHaveCount(3);
    foreach (array_slice($client->requests, 1) as $request) {
        expect($request->getHeaderLine('Authorization'))->toBe('')
            ->and($request->getHeaderLine('Accept-Encoding'))->toBe('identity');
    }
});

it('rejects unsafe audio URLs before the download request', function (string $url, string $exception) {
    [$model, $client] = audioModel($url, []);
    expect(fn() => $model->generate(new SpeechRequest('Hello')))->toThrow($exception);
    expect($client->requests)->toHaveCount(1);
})->with([
    ['file:///secret?signature=secret', InvalidArgumentException::class],
    ['https://user:pass@media.example/audio', InvalidArgumentException::class],
    ['http://127.0.0.1/audio', RemoteFetchException::class],
]);

it('rejects private DNS responses and private redirects', function (bool $redirect) {
    [$model, $client] = audioModel('https://media.example/audio', $redirect ? [new Response(302, ['Location' => 'http://169.254.169.254/secret'])] : [], $redirect ? null : fn() => ['127.0.0.1']);
    expect(fn() => $model->generate(new SpeechRequest('Hello')))->toThrow(RemoteFetchException::class);
    expect($client->requests)->toHaveCount($redirect ? 2 : 1);
})->with([false, true]);

it('enforces the default 25 MiB download limit', function (bool $withLength) {
    [$model] = audioModel('https://media.example/audio?signature=secret', [new Response(200, $withLength ? ['Content-Length' => '26214401'] : [], $withLength ? '' : str_repeat('a', 26214401))]);

    try {
        $model->generate(new SpeechRequest('Hello'));

        throw new \PHPUnit\Framework\AssertionFailedError('Expected bounded download failure.');
    } catch (RemoteFetchException $error) {
        expect($error->context()['reason'])->toBe('response_too_large');
        expect((string) $error)->not->toContain('signature=secret');
    }
})->with([false, true]);

it('maps download failures to safe SDK exceptions', function (bool $transport) {
    [$model] = audioModel('https://media.example/audio?signature=secret', [$transport ? new RuntimeException('https://media.example/audio?signature=secret') : new Response(403, [], 'secret')]);

    try {
        $model->generate(new SpeechRequest('Hello'));

        throw new \PHPUnit\Framework\AssertionFailedError('Expected download failure.');
    } catch (AiSdkException $error) {
        expect((string) $error)->not->toContain('signature=secret')->and($error->getPrevious())->toBeNull();
        if ($transport) {
            expect($error)->toBeInstanceOf(APIConnectionException::class);
        }
    }
})->with([false, true]);

it('keeps failed task messages and empty downloads free of signed URLs', function () {
    $factory = new Psr17Factory();
    $client = new SequenceHttpClient([new Response(200, [], '{"data":{"task_id":"tts-1","task_status":"failed","task_status_msg":"https://media.example/?signature=secret"}}')]);
    $model = new KlingAiSpeechModel('tts', new KlingAiOptions(apiKey: 'key', sdk: new Sdk($client, $factory, $factory)), fn() => ['93.184.216.34']);
    expect(fn() => $model->generate(new SpeechRequest('Hello')))->toThrow(\AiSdk\Exceptions\InvalidResponseException::class, 'Kling AI TTS failed.');
    expect($client->requests)->toHaveCount(1);
    [$model] = audioModel('https://media.example/?signature=secret', [new Response(200, [], '')]);
    expect(fn() => $model->generate(new SpeechRequest('Hello')))->toThrow(\AiSdk\Exceptions\InvalidResponseException::class, 'Unable to download Kling AI TTS audio.');
});
