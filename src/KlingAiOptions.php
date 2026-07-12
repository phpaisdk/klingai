<?php

declare(strict_types=1);

namespace AiSdk\KlingAi;

use AiSdk\KlingAi\Support\KlingAiAuth;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Support\Env;
use AiSdk\Utils\Support\Url;

final class KlingAiOptions
{
    public const string PROVIDER_NAME = 'klingai';

    public const string DEFAULT_BASE_URL = 'https://api-singapore.klingai.com';

    /** @param array<string, string> $headers */
    public function __construct(public readonly ?string $accessKey = null, public readonly ?string $secretKey = null, public readonly ?string $apiKey = null, public readonly string $baseUrl = self::DEFAULT_BASE_URL, public readonly array $headers = [], public readonly ?Sdk $sdk = null) {}

    /** @param array<string, mixed> $c */
    public static function fromArray(array $c = []): self
    {
        $apiKey = Env::loadOptionalSetting(isset($c['apiKey']) ? (string) $c['apiKey'] : null, 'KLINGAI_API_KEY');
        $accessKey = Env::loadOptionalSetting(isset($c['accessKey']) ? (string) $c['accessKey'] : null, 'KLINGAI_ACCESS_KEY');
        $secretKey = Env::loadOptionalSetting(isset($c['secretKey']) ? (string) $c['secretKey'] : null, 'KLINGAI_SECRET_KEY');
        if ($apiKey === null && ($accessKey === null || $secretKey === null)) {
            $accessKey = Env::loadApiKey($accessKey, 'KLINGAI_ACCESS_KEY', self::PROVIDER_NAME);
            $secretKey = Env::loadApiKey($secretKey, 'KLINGAI_SECRET_KEY', self::PROVIDER_NAME);
        }

        return new self($accessKey, $secretKey, $apiKey, Url::withoutTrailingSlash((string) ($c['baseUrl'] ?? $c['baseURL'] ?? self::DEFAULT_BASE_URL)), is_array($c['headers'] ?? null) ? $c['headers'] : [], ($c['sdk'] ?? null) instanceof Sdk ? $c['sdk'] : null);
    }

    /** @return array<string, string> */
    public function authHeaders(): array
    {
        $token = $this->apiKey ?? KlingAiAuth::token((string) $this->accessKey, (string) $this->secretKey);

        return array_merge(['Authorization' => 'Bearer '.$token], $this->headers);
    }
}
