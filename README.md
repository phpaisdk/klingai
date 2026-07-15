# Kling AI provider

<a href="https://github.com/phpaisdk/klingai/actions"><img alt="GitHub Workflow Status" src="https://img.shields.io/github/actions/workflow/status/phpaisdk/klingai/tests.yml?branch=main&label=Tests"></a>
<a href="https://packagist.org/packages/aisdk/klingai"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/aisdk/klingai"></a>
<a href="https://packagist.org/packages/aisdk/klingai"><img alt="Latest Version" src="https://img.shields.io/packagist/v/aisdk/klingai"></a>
<a href="https://packagist.org/packages/aisdk/klingai"><img alt="License" src="https://img.shields.io/packagist/l/aisdk/klingai"></a>
<a href="https://whyphp.dev"><img src="https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat-square&labelColor=18181b" alt="Why PHP in 2026"></a>

------

Supports Kling image generation, text-to-speech, text-to-video, image-to-video, motion control, and current Omni image/video workflows with image, video, element, voice, and multi-shot references.

Use the current `KLINGAI_API_KEY`, or legacy `KLINGAI_ACCESS_KEY` and `KLINGAI_SECRET_KEY` credentials.

```php
$video = Generate::video('A cinematic product reveal')
    ->model(KlingAi::model('kling-v3-omni'))
    ->aspectRatio('16:9')
    ->duration(8)
    ->providerOptions('klingai', ['sound' => 'on'])
    ->run(timeout: 600);
```
