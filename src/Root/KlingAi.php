<?php

declare(strict_types=1);

namespace AiSdk;

use AiSdk\Contracts\Model;
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

    public static function model(string $id): Model
    {
        return self::default()->model($id);
    }
}
