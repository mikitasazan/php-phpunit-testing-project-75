<?php

namespace Project\tests;

use function Project\tests\Utils\getFixturePath;

/**
 * Подставной HTTP-клиент: сеть не трогает, отдаёт заранее записанные ответы
 * и умеет отвечать ошибкой на заранее известные адреса.
 */
class FakeClient
{
    private const ASSETS_DIRNAME = 'site-com-blog-about_files';

    /** @var array<string, string> адрес ресурса -> путь к файлу с его содержимым */
    private array $resources;

    private string $pagePath;

    /** @var array<string, string> адрес -> текст ошибки */
    private array $failures;

    public function __construct()
    {
        $this->pagePath = getFixturePath('site-com-blog-about.html');

        $this->resources = [
            'http://site.com/blog/about/assets/styles.css' => getFixturePath(
                'expected',
                self::ASSETS_DIRNAME,
                'site-com-blog-about-assets-styles.css',
            ),
            'http://site.com/photos/me.jpg' => getFixturePath(
                'expected',
                self::ASSETS_DIRNAME,
                'site-com-photos-me.jpg',
            ),
            'http://site.com/assets/scripts.js' => getFixturePath(
                'expected',
                self::ASSETS_DIRNAME,
                'site-com-assets-scripts.js',
            ),
            'http://site.com/blog/about' => getFixturePath(
                'expected',
                self::ASSETS_DIRNAME,
                'site-com-blog-about.html',
            ),
        ];

        $this->failures = [
            'https://badsite.com' => 'Could not resolve host',
            'http://site.com/500' =>
                'Server error: `GET http://site.com/500` resulted in a `500 Internal Server Error` response',
            'http://site.com/404' =>
                'Server error: `GET http://site.com/404` resulted in a `404 Not Found` response',
        ];
    }

    public function get(string $url): FakeClient
    {
        if (isset($this->failures[$url])) {
            throw new \Exception($this->failures[$url]);
        }

        return $this;
    }

    public function getBody(): FakeClient
    {
        return $this;
    }

    public function getContents(): string
    {
        $content = file_get_contents($this->pagePath);

        if ($content === false) {
            throw new \Exception("Не удалось прочитать фикстуру страницы: {$this->pagePath}");
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options): void
    {
        if (mb_strtoupper($method) !== 'GET') {
            throw new \Exception("Неподдерживаемый метод: {$method}");
        }

        if (!isset($options['sink'])) {
            throw new \Exception('Не указан путь, куда сохранять тело ответа');
        }

        if (!isset($this->resources[$url])) {
            throw new \Exception("Неизвестный адрес ресурса: {$url}");
        }

        copy($this->resources[$url], $options['sink']);
    }
}
