<?php

declare(strict_types=1);

namespace AiSdk\KlingAi\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\TimeoutException;
use AiSdk\Generate;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\Requests\SpeechRequest;
use AiSdk\Responses\SpeechResponse;
use AiSdk\Results\AudioData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class KlingAiSpeechModel extends BaseModel implements SpeechModelInterface
{
    public function __construct(private readonly string $modelId, private readonly KlingAiOptions $options) {}

    public function provider(): string
    {
        return KlingAiOptions::PROVIDER_NAME;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function generate(SpeechRequest $r): SpeechResponse
    {
        $o = $r->providerOptionsFor($this->provider());
        $body = ['text' => $r->input, 'voice_id' => $r->voice ?? $o['voiceId'] ?? 'oversea_male1', 'voice_language' => $o['voiceLanguage'] ?? 'en', 'voice_speed' => $o['voiceSpeed'] ?? 1.0];
        $p = $this->runner($this->options->sdk)->postJson(Url::joinPath($this->options->baseUrl, '/v1/audio/tts'), $body, $this->options->authHeaders(), $this->provider());
        $id = $p['data']['task_id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Kling AI TTS returned no task id.', ['body' => $p]);
        }$start = hrtime(true);
        $timeout = (int) ($o['pollTimeoutMs'] ?? 300000);
        do {
            $status = (string) ($p['data']['task_status'] ?? '');
            if ($status === 'succeed') {
                break;
            }if ($status === 'failed') {
                throw InvalidResponseException::forProvider($this->provider(), (string) ($p['data']['task_status_msg'] ?? 'Kling AI TTS failed.'), ['body' => $p]);
            }usleep(max(0, (int) ($o['pollIntervalMs'] ?? 3000)) * 1000);
            $p = $this->runner($this->options->sdk)->getJson(Url::joinPath($this->options->baseUrl, '/v1/audio/tts/'.rawurlencode($id)), $this->options->authHeaders(), $this->provider());
        } while ((hrtime(true) - $start) / 1_000_000 < $timeout);
        if (($p['data']['task_status'] ?? null) !== 'succeed') {
            throw new TimeoutException('Kling AI TTS timed out.', ['taskId' => $id]);
        }$a = $p['data']['task_result']['audios'][0] ?? null;
        $url = is_array($a) ? ($a['url'] ?? null) : null;
        if (! is_string($url) || $url === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Kling AI TTS completed without an audio URL.', ['body' => $p]);
        }$sdk = $this->options->sdk ?? Generate::sdk();
        $res = $sdk->httpClient->sendRequest($sdk->requestFactory->createRequest('GET', $url));
        $data = (string) $res->getBody();
        if ($res->getStatusCode() < 200 || $res->getStatusCode() >= 300 || $data === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Unable to download Kling AI TTS audio.', ['url' => $url, 'status' => $res->getStatusCode()]);
        }

        return new SpeechResponse(new AudioData($data, $res->getHeaderLine('Content-Type') ?: 'audio/mpeg', isset($a['duration']) ? (float) $a['duration'] : null), Usage::empty(), $p, [$this->provider() => ['taskId' => $id, 'audioUrl' => $url]]);
    }
}
