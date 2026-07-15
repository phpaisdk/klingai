<?php

declare(strict_types=1);

namespace AiSdk\KlingAi;

use AiSdk\Contracts\BaseProvider;
use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\ImageProviderInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\SpeechProviderInterface;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\Contracts\VideoProviderInterface;
use AiSdk\KlingAi\Models\KlingAiImageModel;
use AiSdk\KlingAi\Models\KlingAiSpeechModel;
use AiSdk\KlingAi\Models\KlingAiVideoModel;

final class KlingAiProvider extends BaseProvider implements ImageProviderInterface, SpeechProviderInterface, VideoProviderInterface
{
    public function __construct(public readonly KlingAiOptions $options) {}

    public function name(): string
    {
        return KlingAiOptions::PROVIDER_NAME;
    }

    protected function imageModel(string $id): ImageModelInterface
    {
        return new KlingAiImageModel($id, $this->options);
    }

    protected function speechModel(string $id): SpeechModelInterface
    {
        return new KlingAiSpeechModel($id, $this->options);
    }

    protected function videoModel(string $id): VideoModelInterface
    {
        return new KlingAiVideoModel($id, $this->options);
    }
}
