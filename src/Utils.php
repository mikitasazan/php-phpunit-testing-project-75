<?php

namespace Downloader\Utils;

/**
 * Склеивает части имени в один «слаг»: точки, дефисы, слеши и подчёркивания
 * становятся одним разделителем.
 */
function toSlug(string $name, string $separator = '-'): string
{
    $parts = preg_split('/[-._\/]/', $name);

    if ($parts === false) {
        throw new \Exception("Не удалось разобрать имя: {$name}");
    }

    return implode($separator, $parts);
}

/**
 * Имя файла страницы: site.com/blog/about -> site-com-blog-about.html
 */
function pageToFilename(string $slug): string
{
    return toSlug($slug) . '.html';
}

/**
 * Имя директории с ресурсами: site.com/blog/about -> site-com-blog-about_files
 */
function pageToDirname(string $slug, string $postfix = '_files'): string
{
    return toSlug($slug) . $postfix;
}

/**
 * Имя файла ресурса: расширение сохраняется, а если его нет — берётся html.
 */
function assetToFilename(string $url, string $defaultExtension = 'html'): string
{
    $parsedUrl = parse_url($url);

    if (!is_array($parsedUrl) || !isset($parsedUrl['path'])) {
        throw new \Exception("В адресе нет пути: {$url}");
    }

    $host = $parsedUrl['host'] ?? '';
    $pathParts = pathinfo($parsedUrl['path']);
    $dirname = $pathParts['dirname'] ?? '';
    $filename = $pathParts['filename'];
    $extension = $pathParts['extension'] ?? $defaultExtension;

    return toSlug("{$host}{$dirname}/{$filename}") . ".{$extension}";
}
