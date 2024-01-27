<?php

class Lemmy extends Plugin
{
    private const LEMMY_DOMAINS = [
        'ani.social',
        'beehaw.org',
        'derp.foo',
        'discuss.tchncs.de',
        'feddit.de',
        'feddit.uk',
        'lemm.ee',
        'lemmy.dbzer0.com',
        'lemmy.ml',
        'lemmy.world',
        'lemmy.zip',
        'midwest.social',
        'programming.dev',
        'sh.itjust.works',
        'sopuli.xyz',
    ];

    private const VIDEO_MIME_TYPES = [
        'mov' => 'video/quicktime',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    function about()
    {
        return [null, 'Add content to Lemmy feeds', 'Philip Klempin', false, 'https://github.com/klempin/ttrss-lemmy'];
    }

    function api_version()
    {
        return 2;
    }

    function init($host)
    {
        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
    }

    function hook_article_filter($article)
    {
        foreach ([
            $article['feed']['fetch_url'] ?? null,
            $article['feed']['site_url'] ?? null,
            $article['site_url'] ?? null,
            $article['comments'] ?? null,
            $article['author'] ?? null,
        ] as $uri) {
            if (empty($uri)) {
                continue;
            }

            $host = parse_url($uri, PHP_URL_HOST);
            if (in_array($host, static::LEMMY_DOMAINS, true)) {
                $article = static::addTags($article, $host);
                $article = static::inlineMedia($article);
            }

            break;
        }

        return $article;
    }

    private static function addTags(array $article, string $host): array
    {
        $article['tags'][] = $host;

        preg_match('/href="https:\/\/' . $host . '\/c\/(.+?)\/?"/', $article['content'], $matches);
        if (!empty($matches[1])) {
            $article['tags'][] = $matches[1];
            $article['tags'][] = $matches[1] . '@' . $host;
        }

        return $article;
    }

    private static function inlineMedia(array $article): array
    {
        preg_match_all('/href="(.+?)"/', $article['content'], $matches);
        $inlineHtml = '';
        foreach ($matches[1] ?? [] as $uri) {
            $uriParts = parse_url($uri);
            if ($uriParts === false) {
                continue;
            }

            $pathinfo = pathinfo($uriParts['path'] ?? '');

            if (array_key_exists($pathinfo['extension'] ?? null, static::VIDEO_MIME_TYPES)) {
                $inlineHtml .= '<p><video preload="metadata" controls="true"><source src="' . htmlspecialchars($uri) . '" type="' . static::VIDEO_MIME_TYPES[$pathinfo['extension']] . '"></video></p>';
            }

            if (in_array($pathinfo['extension'] ?? null, ['gif', 'jpeg', 'jpg', 'png', 'webp'], true)) {
                $inlineHtml .= '<p><img loading="lazy" src="' . htmlspecialchars($uri) . '"></p>';
            }
        }

        $article['content'] = $inlineHtml . $article['content'];

        return $article;
    }
}
