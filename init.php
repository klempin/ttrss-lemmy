<?php

class Lemmy extends Plugin
{
    private const LEMMY_DOMAINS = [
        'ani.social',
        'beehaw.org',
        'derp.foo',
        'discuss.tchncs.de',
        'feddit.de',
        'feddit.nl',
        'feddit.uk',
        'lemm.ee',
        'lemmy.blahaj.zone',
        'lemmy.ca',
        'lemmy.dbzer0.com',
        'lemmy.ml',
        'lemmy.world',
        'lemmy.zip',
        'midwest.social',
        'programming.dev',
        'reddthat.com',
        'sh.itjust.works',
        'sopuli.xyz',
        'startrek.website',
    ];

    private const IMG_FILE_TYPES = [
        'gif',
        'jpeg',
        'jpg',
        'png',
        'webp',
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
            $extension = $pathinfo['extension'] ?? null;

            if (array_key_exists($extension, static::VIDEO_MIME_TYPES)) {
                $inlineHtml .= '<p><video preload="metadata" controls="true"><source src="' . htmlspecialchars($uri) . '" type="' . static::VIDEO_MIME_TYPES[$extension] . '"></video></p>';
            }

            if (in_array($extension, static::IMG_FILE_TYPES, true)) {
                $inlineHtml .= '<p><img loading="lazy" src="' . htmlspecialchars($uri) . '"></p>';
            }

            if (str_ends_with($uriParts['host'], 'imgur.com') && $extension === 'gifv') {
                $inlineHtml .= '<p><video preload="metadata" controls="true"><source src="' . htmlspecialchars(str_replace('gifv', 'mp4', $uri)) . '" type="' . static::VIDEO_MIME_TYPES['mp4'] . '"></video></p>';
            }
        }

        $article['content'] = $inlineHtml . $article['content'];

        return $article;
    }
}
