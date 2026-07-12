<?php

declare(strict_types=1);

namespace AiSdk\KlingAi\Models;

use AiSdk\Content;
use AiSdk\ContentSource;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\NoSuchModelException;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\Requests\VideoRequest;
use AiSdk\Responses\VideoJob;
use AiSdk\Responses\VideoJobStatus;
use AiSdk\Results\VideoData;
use AiSdk\Support\Usage;
use AiSdk\Utils\Support\Url;

final class KlingAiVideoModel extends BaseModel implements VideoModelInterface
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

    public function generate(VideoRequest $r): VideoJob
    {
        $mode = $this->mode();
        $path = $this->path($mode);
        $o = $r->providerOptionsFor($this->provider());
        if ($r->video !== null && $mode !== 'omni') {
            throw new InvalidArgumentException('Kling source-video input requires an Omni video model.');
        }
        $body = ['model_name' => $this->apiName($mode)];
        if ($mode === 'motion-control') {
            $body = array_replace($body, is_array($o['raw'] ?? null) ? $o['raw'] : []);
        } elseif ($mode === 'omni') {
            $body['prompt'] = $r->prompt;
            if ($r->image !== null) {
                $body['image_list'] = [['image_url' => $this->media($r->image), 'type' => 'first_frame']];
            }
            if ($r->video !== null) {
                $body['video_list'] = [['video_url' => $this->media($r->video), 'refer_type' => 'base', 'keep_original_sound' => $o['keepOriginalSound'] ?? 'no']];
            }
            foreach (['imageList' => 'image_list', 'videoList' => 'video_list', 'elementList' => 'element_list', 'voiceList' => 'voice_list', 'multiPrompt' => 'multi_prompt'] as $source => $target) {
                if (isset($o[$source])) {
                    $body[$target] = $o[$source];
                }
            }
            if (isset($o['sound'])) {
                $body['sound'] = $o['sound'];
            }
            if (isset($o['mode'])) {
                $body['mode'] = $o['mode'];
            }
            if (isset($o['multiShot'])) {
                $body['multi_shot'] = $o['multiShot'];
            }
            if (isset($o['shotType'])) {
                $body['shot_type'] = $o['shotType'];
            }
            if ($r->output?->aspectRatio) {
                $body['aspect_ratio'] = $r->output->aspectRatio;
            }
            if ($r->output?->duration) {
                $body['duration'] = (string) $r->output->duration;
            }
            $body = array_replace($body, is_array($o['raw'] ?? null) ? $o['raw'] : []);
        } else {
            $body['prompt'] = $r->prompt;
            if ($mode === 'i2v' && $r->image) {
                $body['image'] = $this->media($r->image);
            }if (isset($o['negativePrompt'])) {
                $body['negative_prompt'] = $o['negativePrompt'];
            }if (isset($o['cfgScale'])) {
                $body['cfg_scale'] = $o['cfgScale'];
            }if ($r->output?->aspectRatio) {
                $body['aspect_ratio'] = $r->output->aspectRatio;
            }if ($r->output?->duration) {
                $body['duration'] = (string) $r->output->duration;
            }$body = array_replace($body, is_array($o['raw'] ?? null) ? $o['raw'] : []);
        } $p = $this->runner($this->options->sdk)->postJson(Url::joinPath($this->options->baseUrl, $path), $body, $this->options->authHeaders(), $this->provider());
        $id = $p['data']['task_id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw InvalidResponseException::forProvider($this->provider(), 'Kling AI returned no task id.', ['body' => $p]);
        }

        return new VideoJob($id, $this->provider(), $this->modelId, rawResponse: $p, providerMetadata: [$this->provider() => ['taskId' => $id, 'path' => $path, 'pollIntervalMs' => (int) ($o['pollIntervalMs'] ?? 5000), 'pollTimeoutMs' => (int) ($o['pollTimeoutMs'] ?? 600000)]]);
    }

    public function poll(VideoJob $j): VideoJob
    {
        $path = (string) $j->providerMetadata[$this->provider()]['path'];
        $p = $this->runner($this->options->sdk)->getJson(Url::joinPath($this->options->baseUrl, $path.'/'.rawurlencode($j->id)), $this->options->authHeaders(), $this->provider());
        $s = (string) ($p['data']['task_status'] ?? '');
        if ($s === 'succeed') {
            $v = $p['data']['task_result']['videos'][0] ?? null;
            $url = is_array($v) ? ($v['url'] ?? null) : null;

            return is_string($url) && $url !== '' ? new VideoJob($j->id, $j->provider, $j->modelId, VideoJobStatus::Succeeded, new VideoData(url: $url, duration: isset($v['duration']) ? (float) $v['duration'] : null), usage: Usage::empty(), rawResponse: $p, providerMetadata: $j->providerMetadata) : new VideoJob($j->id, $j->provider, $j->modelId, VideoJobStatus::Failed, errorMessage: 'Kling AI completed without a video URL.', rawResponse: $p, providerMetadata: $j->providerMetadata);
        }

        return new VideoJob($j->id, $j->provider, $j->modelId, $s === 'failed' ? VideoJobStatus::Failed : VideoJobStatus::Running, errorMessage: $s === 'failed' ? (string) ($p['data']['task_status_msg'] ?? 'Kling AI video generation failed.') : null, rawResponse: $p, providerMetadata: $j->providerMetadata);
    }

    private function mode(): string
    {
        if (in_array($this->modelId, ['kling-v3-omni', 'kling-video-o1'], true)) {
            return 'omni';
        }
        foreach (['motion-control', 't2v', 'i2v'] as $m) {
            if (str_ends_with($this->modelId, '-'.$m)) {
                return $m;
            }
        }throw NoSuchModelException::for($this->provider(), $this->modelId, 'videoModel');
    }

    private function path(string $m): string
    {
        return match ($m) {
            't2v' => '/v1/videos/text2video', 'i2v' => '/v1/videos/image2video', 'omni' => '/v1/videos/omni-video', default => '/v1/videos/motion-control'
        };
    }

    private function apiName(string $m): string
    {
        if ($m === 'omni') {
            return $this->modelId;
        }
        $s = $m === 'motion-control' ? '-motion-control' : '-'.$m;
        $n = substr($this->modelId, 0, -strlen($s));

        return str_replace('.', '-', preg_replace('/\.0$/', '', $n) ?? $n);
    }

    private function media(Content $c): string
    {
        return $c->source() === ContentSource::Url ? (string) $c->url() : 'data:'.$c->mimeType().';base64,'.$c->base64Data();
    }
}
