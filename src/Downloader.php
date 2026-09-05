<?php

namespace Downloader\Downloader;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

use function Downloader\Utils\{assetToFilename, pageToDirname, pageToFilename};

const ASSET_ATTRIBUTES = [
    'img' => 'src',
    'link' => 'href',
    'script' => 'src',
];

function createLogger(string $dirname): Logger
{
    $logger = new Logger('page-loader');
    $logger->pushHandler(new StreamHandler("{$dirname}/page-loader.log", Level::Debug));

    return $logger;
}

/**
 * Абсолютный адрес ресурса, если он лежит на том же сайте, иначе null.
 */
function toLocalUrl(string $rawUrl, string $scheme, string $host): ?string
{
    $parsedUrl = parse_url($rawUrl);

    if (!is_array($parsedUrl) || !isset($parsedUrl['path'])) {
        return null;
    }

    if (isset($parsedUrl['host']) && $parsedUrl['host'] !== $host) {
        return null;
    }

    $path = ltrim($parsedUrl['path'], '/');

    return "{$scheme}://{$host}/{$path}";
}

/**
 * Переписывает ссылки на локальные ресурсы и возвращает список того,
 * что предстоит скачать.
 *
 * @return array<int, array{url: string, filename: string}>
 */
function collectAssets(DOMDocument $document, string $scheme, string $host, string $assetsDirname): array
{
    $assets = [];

    foreach (ASSET_ATTRIBUTES as $tagName => $attributeName) {
        foreach ($document->getElementsByTagName($tagName) as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $rawUrl = $element->getAttribute($attributeName);

            if ($rawUrl === '') {
                continue;
            }

            $url = toLocalUrl($rawUrl, $scheme, $host);

            if ($url === null) {
                continue;
            }

            $filename = assetToFilename($url);
            $assets[] = ['url' => $url, 'filename' => $filename];
            $element->setAttribute($attributeName, "{$assetsDirname}/{$filename}");
        }
    }

    return $assets;
}

/**
 * Скачивает страницу и её ресурсы, возвращает путь к сохранённой странице.
 */
function downloadPage(string $url, string $outputDir = '', string $clientClass = Client::class): string
{
    $parsedUrl = parse_url($url);

    if (!is_array($parsedUrl) || !isset($parsedUrl['host'])) {
        throw new \Exception("Неверный адрес страницы: {$url}");
    }

    $scheme = $parsedUrl['scheme'] ?? 'http';
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] ?? '';
    $slug = "{$host}{$path}";

    $outputDirPath = realpath($outputDir);

    if ($outputDirPath === false || !is_dir($outputDirPath)) {
        throw new \Exception("Директория для сохранения недоступна: {$outputDir}");
    }

    $pagePath = "{$outputDirPath}/" . pageToFilename($slug);
    $assetsDirname = pageToDirname($slug);
    $assetsDirPath = "{$outputDirPath}/{$assetsDirname}";

    $client = new $clientClass();
    $html = $client->get($url)->getBody()->getContents();

    $document = new DOMDocument();
    @$document->loadHTML($html, LIBXML_HTML_NODEFDTD);

    $assets = collectAssets($document, $scheme, $host, $assetsDirname);

    $logger = createLogger($outputDirPath);
    $logger->info("Создаю директорию для ресурсов: {$assetsDirPath}");

    if (file_exists($assetsDirPath)) {
        throw new \Exception("Директория {$assetsDirPath} уже существует");
    }

    if (!mkdir($assetsDirPath)) {
        throw new \Exception("Не удалось создать директорию {$assetsDirPath}");
    }

    $documentHtml = $document->saveHTML($document);

    if ($documentHtml === false) {
        throw new \Exception('Не удалось собрать HTML страницы обратно в текст');
    }

    $logger->info("Сохраняю страницу: {$pagePath}");

    if (file_put_contents($pagePath, trim($documentHtml)) === false) {
        throw new \Exception("Не удалось записать страницу в {$pagePath}");
    }

    foreach ($assets as $asset) {
        ['url' => $assetUrl, 'filename' => $filename] = $asset;
        $logger->info("Скачиваю ресурс {$assetUrl} -> {$filename}");

        try {
            $client->request('GET', $assetUrl, ['sink' => "{$assetsDirPath}/{$filename}"]);
        } catch (\Exception $exception) {
            $logger->warning($exception->getMessage());
        }
    }

    return $pagePath;
}
