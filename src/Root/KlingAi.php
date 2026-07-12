<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\ImageModelInterface;
use AiSdk\Contracts\SpeechModelInterface;
use AiSdk\Contracts\VideoModelInterface;
use AiSdk\KlingAi\KlingAiOptions;
use AiSdk\KlingAi\KlingAiProvider;

final class KlingAi
{
    private static ?KlingAiProvider $default = null;

    /** @param array<string, mixed> $c */
    public static function create(array $c = []): KlingAiProvider
    {
        return self::$default = new KlingAiProvider(KlingAiOptions::fromArray($c));
    }

    public static function default(): KlingAiProvider
    {
        return self::$default ??= self::create();
    }

    public static function reset(): void
    {
        self::$default = null;
    }

    public static function image(string $id): ImageModelInterface
    {
        return self::default()->imageModel($id);
    }

    public static function speech(string $id = 'tts'): SpeechModelInterface
    {
        return self::default()->speechModel($id);
    }

    public static function video(string $id): VideoModelInterface
    {
        return self::default()->videoModel($id);
    }
}
