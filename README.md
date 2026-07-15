# Kling AI provider

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
