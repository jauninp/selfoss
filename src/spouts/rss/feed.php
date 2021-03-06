<?php

namespace spouts\rss;

/**
 * Spout for fetching an rss feed
 *
 * @copyright  Copyright (c) Tobias Zeising (http://www.aditu.de)
 * @license    GPLv3 (https://www.gnu.org/licenses/gpl-3.0.html)
 * @author     Tobias Zeising <tobias.zeising@aditu.de>
 */
class feed extends \spouts\spout {
    use \helpers\ItemsIterator;

    /** @var string name of source */
    public $name = 'RSS Feed';

    /** @var string description of this source type */
    public $description = 'Get posts from plain RSS/Atom feed.';

    /** @var array configurable parameters */
    public $params = [
        'url' => [
            'title' => 'URL',
            'type' => 'url',
            'default' => '',
            'required' => true,
            'validation' => ['notempty']
        ]
    ];

    /** @var ?string URL of the source */
    protected $htmlUrl = null;

    /** @var ?string URL of the favicon */
    protected $faviconUrl = null;

    //
    // Source Methods
    //

    public function load(array $params) {
        // initialize simplepie feed loader
        $this->feed = @new \SimplePie();
        @$this->feed->set_cache_location(\F3::get('cache'));
        @$this->feed->set_cache_duration(1800);
        @$this->feed->set_file_class(\helpers\SimplePieFileGuzzle::class);
        @$this->feed->set_feed_url(htmlspecialchars_decode($params['url']));
        @$this->feed->set_autodiscovery_level(SIMPLEPIE_LOCATOR_AUTODISCOVERY | SIMPLEPIE_LOCATOR_LOCAL_EXTENSION | SIMPLEPIE_LOCATOR_LOCAL_BODY);
        $this->feed->set_useragent(\helpers\WebClient::getUserAgent());

        // fetch items
        @$this->feed->init();

        // on error retry with force_feed
        if (@$this->feed->error()) {
            @$this->feed->set_autodiscovery_level(SIMPLEPIE_LOCATOR_NONE);
            @$this->feed->force_feed(true);
            @$this->feed->init();
        }

        // check for error
        if (@$this->feed->error()) {
            throw new \Exception($this->feed->error());
        } else {
            // save fetched items
            $this->items = @$this->feed->get_items();
        }

        // set html url
        $this->htmlUrl = @$this->feed->get_link();

        $this->spoutTitle = $this->feed->get_title();
    }

    public function getXmlUrl(array $params) {
        return isset($params['url']) ? html_entity_decode($params['url']) : null;
    }

    public function getHtmlUrl() {
        return $this->htmlUrl;
    }

    public function getId() {
        if ($this->items !== null && $this->valid()) {
            $id = @current($this->items)->get_id();
            if (strlen($id) > 255) {
                $id = md5($id);
            }

            return $id;
        }

        return null;
    }

    public function getTitle() {
        if ($this->items !== null && $this->valid()) {
            return htmlspecialchars_decode(@current($this->items)->get_title());
        }

        return null;
    }

    public function getContent() {
        if ($this->items !== null && $this->valid()) {
            return @current($this->items)->get_content();
        }

        return null;
    }

    public function getIcon() {
        if ($this->faviconUrl !== null) {
            return $this->faviconUrl;
        }

        $this->faviconUrl = null;
        $imageHelper = $this->getImageHelper();
        $htmlUrl = $this->getHtmlUrl();
        if ($htmlUrl && $imageHelper->fetchFavicon($htmlUrl, true)) {
            $this->faviconUrl = $imageHelper->getFaviconUrl();
            \F3::get('logger')->debug('icon: using feed homepage favicon: ' . $this->faviconUrl);
        } else {
            $feedLogoUrl = $this->feed->get_image_url();
            if ($feedLogoUrl && $imageHelper->fetchFavicon($feedLogoUrl)) {
                $this->faviconUrl = $imageHelper->getFaviconUrl();
                \F3::get('logger')->debug('icon: using feed logo: ' . $this->faviconUrl);
            }
        }

        return $this->faviconUrl;
    }

    public function getLink() {
        if ($this->items !== null && $this->valid()) {
            $link = @current($this->items)->get_link();

            return $link;
        }

        return null;
    }

    public function getDate() {
        if ($this->items !== null && $this->valid()) {
            $date = @current($this->items)->get_date('Y-m-d H:i:s');
        }
        if (strlen($date) === 0) {
            $date = date('Y-m-d H:i:s');
        }

        return $date;
    }

    public function getAuthor() {
        if ($this->items !== null && $this->valid()) {
            $author = @current($this->items)->get_author();
            if (isset($author)) {
                $name = $author->get_name();
                if (isset($name)) {
                    return htmlspecialchars_decode($name);
                } else {
                    return htmlspecialchars_decode($author->get_email());
                }
            }
        }

        return null;
    }

    public function destroy() {
        $this->feed->__destruct();
        unset($this->items);
        $this->items = null;
    }
}
