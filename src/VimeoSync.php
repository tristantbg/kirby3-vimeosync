<?php

use Kirby\Data\Yaml;
use \Dotenv\Dotenv;
use \Vimeo\Vimeo;

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

        self::$lib = new \Vimeo\Vimeo(self::$config['client_id'], self::$config['$client_secret']);
        self::$lib->setToken($config['access_token']);

        self::$vimeoPageContainer = site()->index()->filterBy('intendedTemplate', 'vimeo.items')->first();

    }

    public static function unlistVideos()
    {

        site()->index()->filterBy('intendedTemplate', 'vimeo.video')->map(function ($p) {
            $p->changeStatus('unlisted');
        });

    }

    public static function deleteUnusedVideos()
    {

        site()->index()->filterBy('intendedTemplate', 'vimeo.video')->unlisted()->map(function ($p) {
            $p->delete(true);
        });

    }

    public static function getVideos()
    {

        if (!self::$lib) {
            \VimeoSync\App::init();
        }

        $response = $lib->request('/me/videos', ['fields' => 'uri,name,description,link,pictures,files'], 'GET');

        foreach ($response['body'] as $key => $vimeoItem) {
            \VimeoSync\App::writeInfos($vimeoItem);
        }

    }

    public static function writeInfos($vimeoItem)
    {
        if (!self::$lib) {
            \VimeoSync\App::init();
        }

        if (is_string($vimeoItem)) {
            $response  = self::$lib->request($uri, ['fields' => 'uri,name,description,link,pictures,files', 'per_page' => 1], 'GET');
            $vimeoItem = $response['body'];
        }
        $id              = str_replace('/videos/', '', $vimeoItem['uri']);
        $vimeoThumbnails = isset($vimeoItem['pictures']) ? $vimeoItem['pictures']['sizes'] : [];
        $vimeoFiles      = isset($vimeoItem['files']) ? $vimeoItem['files'] : [];

        usort($vimeoThumbnails, function ($item1, $item2) {
            return $item1['width'] <=> $item2['width'];
        });

        usort($vimeoFiles, function ($item1, $item2) {
            if ($item1['quality'] !== 'hls' && $item2['quality'] !== 'hls') {
                return $item1['width'] <=> $item2['width'];
            }

        });

        if ($vimeoPage = self::$vimeoPageContainer->find($id)) {
            $vimeoPage->update(array(
                'title'            => $vimeoItem['name'],
                'vimeoID'          => $id,
                'vimeoData'        => \Kirby\Data\Yaml::encode($vimeoItem),
                'vimeoName'        => $vimeoItem['name'],
                'vimeoDescription' => $vimeoItem['description'],
                'vimeoURL'         => $vimeoItem['link'],
                'vimeoThumbnails'  => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                'vimeoFiles'       => \Kirby\Data\Yaml::encode($vimeoFiles),
            ));
        } else {

            if (self::$vimeoPageContainer) {
                $vimeoPage = self::$vimeoPageContainer->createChild('slug' => $id,
                    'template'                                                 => 'vimeo.video',
                    'content'                                                  => [
                        'title'            => $vimeoItem['name'],
                        'vimeoID'          => $id,
                        'vimeoData'        => \Kirby\Data\Yaml::encode($vimeoItem),
                        'vimeoName'        => $vimeoItem['name'],
                        'vimeoDescription' => $vimeoItem['description'],
                        'vimeoURL'         => $vimeoItem['link'],
                        'vimeoThumbnails'  => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                        'vimeoFiles'       => \Kirby\Data\Yaml::encode($vimeoFiles),
                    ])
            }

        }

        $vimeoPage->changeStatus('listed');

    }

}
