<?php

use Dotenv\Dotenv;
use Kirby\Data\Yaml;
use Kirby\Toolkit\Str;
use Vimeo\Vimeo;

namespace VimeoSync;

// require 'helpers.php';

$dotenv = new \Dotenv\Dotenv(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 1));
$dotenv->load();

class App
{
    private static $config             = [];
    private static $lib                = null;
    private static $vimeoPageContainer = null;

    public static function init()
    {

        self::$config = [
            'client_id'     => $_ENV['CLIENT_ID'],
            'client_secret' => $_ENV['CLIENT_SECRET'],
            'access_token'  => $_ENV['ACCESS_TOKEN'],
        ];

        self::$lib = new \Vimeo\Vimeo(self::$config['client_id'], self::$config['client_secret']);
        self::$lib->setToken(self::$config['access_token']);

        self::$vimeoPageContainer = site()->index()->filterBy('intendedTemplate', 'vimeo.items')->first();

    }

    public static function vimeoPages()
    {

        return site()->index()->filterBy('intendedTemplate', 'vimeo.video');

    }

    public static function unlistVideos()
    {

        foreach (\VimeoSync\App::vimeoPages() as $key => $vimeoPage) {
            // $vimeoPage->update(['vimeoAvailable' => false]);
            $vimeoPage->changeStatus('unlisted');
        }

    }

    public static function deleteUnusedVideos()
    {

        $unusedVideos = site()->index()->filterBy('intendedTemplate', 'vimeo.video')->filter(function ($child) {
            return $child->vimeoAvailable()->bool();
        });

        foreach ($unusedVideos as $key => $vimeoPage) {
          $vimeoPage->delete();
        }

    }

    public static function getVideos($uri = null, $options = null)
    {

        if (!self::$lib) {
            \VimeoSync\App::init();
        }

        \VimeoSync\App::unlistVideos();

        if ($uri) {
          $response = self::$lib->request($uri, [], 'GET');
        } else {
          $response = self::$lib->request('/me/videos', ['fields' => 'uri,modified_time,created_time,name,description,link,pictures,files', 'per_page' => 100], 'GET');
        }

        foreach ($response['body']['data'] as $key => $vimeoItem) {
            \VimeoSync\App::writeInfos($vimeoItem, $options);
        }

        if ($nextPage = $response['body']['paging']['next']) {
          \VimeoSync\App::getVideos($nextPage, $options);
        }

        // \VimeoSync\App::getThumbnails();

        // \VimeoSync\App::deleteUnusedVideos();

        return true;

    }

    public static function getThumbnails($vimeoPage = null)
    {

        if ($vimeoPage) {
          $videos = [$vimeoPage];
        } else {
          $videos = \VimeoSync\App::vimeoPages();
        }

        foreach ($videos as $key => $vimeoPage) {
            $vimeoThumbnails = $vimeoPage->vimeoThumbnails()->toStructure();
            if ($vimeoThumbnails->count() > 0) {
                $url       = strtok($vimeoThumbnails->last()->link(), '?');
                $imagedata = file_get_contents($url);
                \Kirby\Toolkit\F::write(kirby()->root('content') . '/' . $vimeoPage->diruri() . '/cover.jpg', $imagedata);
            }
        }

        return true;
    }

    public static function writeInfos($vimeoItem, $options = null)
    {
        if (!self::$lib) {
            \VimeoSync\App::init();
        }

        $defaultOptions = ['new_items' => false];
        $options = $options ? array_merge($defaultOptions, $options) : $defaultOptions;

        kirby()->impersonate('kirby');

        if ($vimeoItem && is_string($vimeoItem)) {
            $response  = self::$lib->request($uri, ['fields' => 'uri,modified_time,created_time,name,description,link,pictures,files', 'per_page' => 1], 'GET');
            $vimeoItem = $response['body'];
        }
        $id                = str_replace('/videos/', '', $vimeoItem['uri']);
        $vimeoThumbnails   = isset($vimeoItem['pictures']) ? $vimeoItem['pictures']['sizes'] : [];
        $vimeoFiles        = isset($vimeoItem['files']) ? $vimeoItem['files'] : [];
        $vimeoModifiedTime = date('Y-m-d H:i', strtotime($vimeoItem['modified_time']));
        $vimeoCreatedTime  = date('Y-m-d H:i', strtotime($vimeoItem['created_time']));

        usort($vimeoThumbnails, function ($item1, $item2) {
            return $item1['width'] <=> $item2['width'];
        });

        usort($vimeoFiles, function ($item1, $item2) {
            if ($item1['quality'] !== 'hls' && $item2['quality'] !== 'hls') {
                return $item1['width'] <=> $item2['width'];
            }

        });

        $vimeoPage = page(self::$vimeoPageContainer->id() . '/' . \Kirby\Toolkit\Str::slug($id));

        if ($vimeoPage) {

            if ($vimeoPage->vimeoModifiedTime() != $vimeoModifiedTime || $vimeoPage->cover()->empty()) {

              $vimeoPage->update([
                  'title'             => $vimeoItem['name'],
                  'cover'             => '- cover.jpg',
                  'vimeoID'           => \Kirby\Toolkit\Str::slug($id),
                  'vimeoData'         => \Kirby\Data\Yaml::encode($vimeoItem),
                  'vimeoCreatedTime'  => $vimeoCreatedTime,
                  'vimeoModifiedTime' => $vimeoModifiedTime,
                  'vimeoName'         => $vimeoItem['name'],
                  'vimeoDescription'  => $vimeoItem['description'],
                  'vimeoURL'          => $vimeoItem['link'],
                  'vimeoThumbnails'   => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                  'vimeoFiles'        => \Kirby\Data\Yaml::encode($vimeoFiles),
                  'vimeoAvailable'    => 'true',
              ]);

              $vimeoPage->changeStatus('unlisted');

              \VimeoSync\App::getThumbnails($vimeoPage);

            }

        } else {

            if (self::$vimeoPageContainer) {

                try {

                  $vimeoPage = self::$vimeoPageContainer->createChild([
                      'slug'     => \Kirby\Toolkit\Str::slug($id),
                      'template' => 'vimeo.video',
                      'draft'    => false,
                      'listed'   => false,
                      'content'  => [
                          'title'              => $vimeoItem['name'],
                          'cover'              => '- cover.jpg',
                          'vimeoID'            => \Kirby\Toolkit\Str::slug($id),
                          'vimeoData'          => \Kirby\Data\Yaml::encode($vimeoItem),
                          'vimeoCreatedTime'   => $vimeoCreatedTime,
                          'vimeoModifiedTime'  => $vimeoModifiedTime,
                          'vimeoName'          => $vimeoItem['name'],
                          'vimeoDescription'   => $vimeoItem['description'],
                          'vimeoURL'           => $vimeoItem['link'],
                          'vimeoThumbnails'    => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                          'vimeoFiles'         => \Kirby\Data\Yaml::encode($vimeoFiles),
                          'vimeoAvailable'     => 'true',
                      ],
                  ]);

                } catch (Exception $e) {

                }

                $vimeoPage->changeStatus('unlisted');

            }

        }

    }

}
