<?php

class Lemmy extends Plugin
{
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
        $host = parse_url($article['feed']['fetch_url'], PHP_URL_HOST);
        if (in_array($host, [
            'feddit.de',
            'lemm.ee',
            'lemmy.world',
            'sh.itjust.works',
        ], true)) {
            $article['tags'][] = $host;

            preg_match('/href="https:\/\/' . $host . '\/c\/.+?"/', $article['content'], $matches);
            if (!empty($matches[0])) {
                $article['tags'][] = substr($matches[0], 17 + strlen($host), -1);
            }
        }

        return $article;
    }
}
