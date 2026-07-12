<?php

declare(strict_types=1);

namespace AiSdk\KlingAi\Models;

use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\TimeoutException;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\Requests\ImageRequest;
use AiSdk\Responses\ImageResponse;
use AiSdk\Results\ImageData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class KlingAiImageModel extends BaseModel implements ImageModelInterface
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

    public function generate(ImageRequest $r): ImageResponse
    {
        $o = $r->providerOptionsFor($this->provider());
        $path = str_contains($this->modelId, 'omni') || str_contains($this->modelId, 'image-o1') ? '/v1/images/omni-image' : '/v1/images/generations';
        $body = array_filter(['model_name' => $this->modelId, 'prompt' => $r->prompt, 'n' => $r->count, 'resolution' => $o['resolution'] ?? null, 'aspect_ratio' => $r->aspectRatio, 'result_type' => $o['resultType'] ?? null, 'series_amount' => $o['seriesAmount'] ?? null], fn ($v) => $v !== null);
        if (is_array($o['imageList'] ?? null)) {
            $body['image_list'] = $o['imageList'];
        }$p = $this->runner($this->options->sdk)->postJson(Url::joinPath($this->options->baseUrl, $path), $body, $this->options->authHeaders(), $this->provider());
        $id = $p['data']['task_id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Kling AI image generation returned no task id.', ['body' => $p]);
        }$start = hrtime(true);
        do {
            $s = (string) ($p['data']['task_status'] ?? '');
            if ($s === 'succeed') {
                break;
            }if ($s === 'failed') {
                throw InvalidResponseException::forProvider($this->provider(), (string) ($p['data']['task_status_msg'] ?? 'Kling AI image generation failed.'), ['body' => $p]);
            }usleep(max(0, (int) ($o['pollIntervalMs'] ?? 3000)) * 1000);
            $p = $this->runner($this->options->sdk)->getJson(Url::joinPath($this->options->baseUrl, $path.'/'.rawurlencode($id)), $this->options->authHeaders(), $this->provider());
        } while ((hrtime(true) - $start) / 1_000_000 < (int) ($o['pollTimeoutMs'] ?? 300000));
        if (($p['data']['task_status'] ?? null) !== 'succeed') {
            throw new TimeoutException('Kling AI image generation timed out.', ['taskId' => $id]);
        }$images = [];
        foreach ((array) ($p['data']['task_result']['images'] ?? []) as $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                $images[] = new ImageData(url: $url);
            }
        }if ($images === []) {
            throw InvalidResponseException::forProvider($this->provider(), 'Kling AI completed without images.', ['body' => $p]);
        }

        return new ImageResponse($images, Usage::empty(), $p, [$this->provider() => ['taskId' => $id]]);
    }
}
