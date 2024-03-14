<?php

class Lemmy extends Plugin
{
    private const LEMMY_DOMAINS = [
        'ani.social',
        'aussie.zone',
        'beehaw.org',
        'derp.foo',
        'discuss.tchncs.de',
        'feddit.de',
        'feddit.nl',
        'feddit.uk',
        'infosec.pub',
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

    private PluginHost $host;

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
        $this->host = $host;
        $this->host->add_hook($host::HOOK_ARTICLE_FILTER, $this, 49);
        $this->host->add_hook($host::HOOK_FEED_FETCHED, $this);
        $this->host->add_hook($host::HOOK_IFRAME_WHITELISTED, $this);
        $this->host->add_hook($host::HOOK_PREFS_TAB, $this);
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
            if (!in_array($host, static::LEMMY_DOMAINS, true)) {
                break;
            }

            preg_match_all('/href="(.+?)"/', $article['content'], $matches);
            $inlineHtml = '';
            foreach ($matches[1] ?? [] as $uri) {
                $inlineHtml .= static::inlineMedia($uri);
            }

            $article['content'] = $inlineHtml . $article['content'];
            $article['tags'][] = $host;

            preg_match('/href="https:\/\/' . $host . '\/c\/(.+?)\/?"/', $article['content'], $matches);
            if (!empty($matches[1])) {
                $article['tags'][] = $matches[1];
                $article['tags'][] = $matches[1] . '@' . $host;
            }

            break;
        }

        return $article;
    }

    function hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed)
    {
        $host = parse_url($fetch_url, PHP_URL_HOST);
        if (!in_array($host, static::LEMMY_DOMAINS, true)) {
            return $feed_data;
        }

        if (!$this->host->get($this, 'link_to_comments')) {
            return $feed_data;
        }

        $feed = new DOMDocument();
        if (!$feed->loadXML($feed_data)) {
            return $feed_data;
        }

        foreach ($feed->getElementsByTagName('item') as $feedItem) {
            $itemContent = $feedItem->getElementsByTagName('description')->item(0);
            if (empty($itemContent) || empty($itemContent->nodeValue)) {
                continue;
            }

            $itemLink = $feedItem->getElementsByTagName('link')->item(0);
            if (empty($itemLink)) {
                continue;
            }

            preg_match('/href="(https:\/\/' . $host . '\/post\/\d+?\/?)".+?comments?<\/a>/', $itemContent->nodeValue, $matches);
            if (!empty($matches[1])) {
                $itemLink->nodeValue = $matches[1];
            }
        }

        return $feed->saveXML() ?: $feed_data;
    }

    function hook_iframe_whitelisted($url)
    {
        return $url === 'www.youtube-nocookie.com';
    }

    function hook_prefs_tab($args)
    {
        if ($args !== 'prefFeeds') {
            return;
        }

        $title = __('Lemmy');
        $pluginHandlerTags = \Controls\pluginhandler_tags($this, 'save_settings');
        $linkToCommentsCheckbox = \Controls\checkbox_tag('lemmy_link_to_comments', $this->host->get($this, 'link_to_comments') === true, id: 'lemmy_link_to_comments');
        $enableGlobally = __('Link article to Lemmy comments');
        $submitTag = \Controls\submit_tag(__('Save settings'));

        echo <<<EOT
<div dojoType="dijit.layout.AccordionPane" title="<i class='material-icons'>image</i> {$title}">
    <form dojoType='dijit.form.Form'>
        {$pluginHandlerTags}
        <script type="dojo/method" event="onSubmit" args="evt">
            evt.preventDefault();
            if (this.validate()) {
                Notify.progress('Saving data...', true);
                xhr.post("backend.php", this.getValues(), (reply) => {
                    Notify.info(reply);
                })
            }
        </script>

        <fieldset>
            <label for="lemmy_link_to_comments" class="checkbox">{$linkToCommentsCheckbox} {$enableGlobally}</label>
        </fieldset>

        <fieldset>
            {$submitTag}
        </fieldset>
    </form>
</div>
EOT;
    }

    public function save_settings()
    {
        $this->host->set($this, 'link_to_comments', ($_POST["lemmy_link_to_comments"] ?? "") === "on");
        echo __("Lemmy: Settings saved");
    }

    private static function inlineMedia(string $uri): string
    {
        $uriParts = parse_url($uri);
        if ($uriParts === false || empty($uriParts['host'])) {
            return '';
        }

        if ($uriParts['host'] === 'youtu.be') {
            return '<iframe src="https://www.youtube-nocookie.com/embed/' . htmlspecialchars($uriParts['path']) . '" allow="clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen></iframe>';
        }

        if ($uriParts['host'] === 'www.youtube.com') {
            if (empty($uriParts['query'])) {
                return '';
            }

            parse_str($uriParts['query'], $query);
            if (empty($query['v'])) {
                return '';
            }

            return '<iframe src="https://www.youtube-nocookie.com/embed/' . htmlspecialchars($query['v']) . '" allow="clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen></iframe>';
        }

        $pathinfo = pathinfo($uriParts['path'] ?? '');
        $extension = $pathinfo['extension'] ?? null;

        if (array_key_exists($extension, static::VIDEO_MIME_TYPES)) {
            return '<p><video preload="metadata" controls="true"><source src="' . htmlspecialchars($uri) . '" type="' . static::VIDEO_MIME_TYPES[$extension] . '"></video></p>';
        }

        if (in_array($extension, static::IMG_FILE_TYPES, true)) {
            return '<p><img loading="lazy" src="' . htmlspecialchars($uri) . '"></p>';
        }

        if ($extension === 'gifv') {
            foreach ([
                'imgur.com',
                'tumblr.com',
            ] as $domain) {
                if ($uriParts['host'] !== $domain && !str_ends_with($uriParts['host'], '.' . $domain)) {
                    return '';
                }

                return '<p><video preload="metadata" controls="true"><source src="' . htmlspecialchars(str_replace('gifv', 'mp4', $uri)) . '" type="' . static::VIDEO_MIME_TYPES['mp4'] . '"></video></p>';
            }
        }

        return '';
    }
}
