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

        self::$vimeoPageContainer = site()->pages()->filterBy('intendedTemplate', 'vimeo.items')->first();

    }

    public static function request($url) {

      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.vimeo.com$url",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
          "Authorization: Bearer " . self::$config['access_token']
        ),
      ));

      $response = curl_exec($curl);

      curl_close($curl);

      return json_decode($response, true);

    }

    public static function vimeoPages()
    {

        if (!self::$vimeoPageContainer) {
            \VimeoSync\App::init();
        }

        return self::$vimeoPageContainer->children();

    }

    public static function unlistVideos()
    {
        $videos = \VimeoSync\App::vimeoPages();

        kirby()->impersonate('kirby');

        foreach ($videos as $key => $vimeoPage) {
            // $vimeoPage->update(['vimeoAvailable' => false]);
            $vimeoPage->changeStatus('unlisted');
        }

    }

    public static function deleteUnusedVideos($ids = false)
    {
        if ($ids) {
          $videos = \VimeoSync\App::vimeoPages();

          kirby()->impersonate('kirby');
          foreach ($videos as $key => $video) {
            if(!in_array($video->vimeoID(), $ids)) $video->delete();
          }
        }

    }

    public static function getVideos($uri = null, $options = null, $ids = [])
    {


        if (!self::$lib) {
            \VimeoSync\App::init();
        }

        // \VimeoSync\App::unlistVideos();

        if ($uri) {
          // $response = self::$lib->request($uri, [], 'GET');
          $response = \VimeoSync\App::request('$uri?fields=uri,modified_time,created_time,name,description,link,pictures,files&per_page=100');
        } else {
          $response = \VimeoSync\App::request('/me/videos?fields=uri,modified_time,created_time,name,description,link,pictures,files&per_page=100');
        }

        foreach ($response['data'] as $key => $vimeoItem) {
            \VimeoSync\App::writeInfos($vimeoItem, $options);
            array_push($ids, \Kirby\Toolkit\Str::slug(str_replace('/videos/', '', $vimeoItem['uri'])));
        }

        if ($nextPage = $response['paging']['next']) {
          // \VimeoSync\App::getVideos($nextPage, $options, $ids);
        } else {
          \VimeoSync\App::deleteUnusedVideos($ids);
        }

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
                if($cover = $vimeoPage->file('cover.jpg')) {
                  $vimeoPage->update([
                    'cover' => $cover->id()
                  ]);
                  // $imagedata = file_get_contents($cover->imgixUrl(['trim' => 'auto']));
                  // \Kirby\Toolkit\F::write(kirby()->root('content') . '/' . $vimeoPage->diruri() . '/cover.jpg', $imagedata);
                }
            }
        }

        return true;
    }

    public static function trimThumbnails($vimeoPage = null)
    {

        if ($vimeoPage) {
          $videos = [$vimeoPage];
        } else {
          $videos = \VimeoSync\App::vimeoPages();
        }

        foreach ($videos as $key => $vimeoPage) {
            if($cover = $vimeoPage->file('cover.jpg')) {
              $imagedata = file_get_contents($cover->imgixUrl(['trim' => 'auto']));
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
            $response  = \VimeoSync\App::request('$uri?fields=uri,modified_time,created_time,name,description,link,pictures,files&per_page=1');
        }
        $id                = str_replace('/videos/', '', $vimeoItem['uri']);
        $vimeoThumbnails   = isset($vimeoItem['pictures']) ? $vimeoItem['pictures']['sizes'] : [];
        $vimeoFiles        = isset($vimeoItem['files']) ? $vimeoItem['files'] : [];
        $vimeoModifiedTime = strftime('%Y-%m-%d %H:%M', strtotime($vimeoItem['modified_time']));
        $vimeoCreatedTime  = strftime('%Y-%m-%d %H:%M', strtotime($vimeoItem['created_time']));

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

            if ($vimeoPage->vimeoModifiedTime()->value() != $vimeoModifiedTime || $vimeoPage->cover()->isEmpty() || $vimeoPage->vimeoFiles()->isEmpty()) {

              try {

                $vimeoPage->update([
                    'title'             => $vimeoItem['name'],
                    'cover'             => \Kirby\Data\Yaml::encode(['cover.jpg']),
                    'vimeoID'           => \Kirby\Toolkit\Str::slug($id),
                    'vimeoData'         => \Kirby\Data\Yaml::encode($vimeoItem),
                    'vimeoCreatedTime'  => $vimeoCreatedTime,
                    'vimeoModifiedTime' => $vimeoModifiedTime,
                    'vimeoName'         => $vimeoItem['name'],
                    'vimeoDescription'  => $vimeoItem['description'],
                    'vimeoURL'          => $vimeoItem['link'],
                    'vimeoThumbnails'   => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                    'vimeoFiles'        => \Kirby\Data\Yaml::encode($vimeoFiles)
                ]);

              } catch (Exception $e) {

              }


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
                          'cover'              => \Kirby\Data\Yaml::encode(['cover.jpg']),
                          'vimeoID'            => \Kirby\Toolkit\Str::slug($id),
                          'vimeoData'          => \Kirby\Data\Yaml::encode($vimeoItem),
                          'vimeoCreatedTime'   => $vimeoCreatedTime,
                          'vimeoModifiedTime'  => $vimeoModifiedTime,
                          'vimeoName'          => $vimeoItem['name'],
                          'vimeoDescription'   => $vimeoItem['description'],
                          'vimeoURL'           => $vimeoItem['link'],
                          'vimeoThumbnails'    => \Kirby\Data\Yaml::encode($vimeoThumbnails),
                          'vimeoFiles'         => \Kirby\Data\Yaml::encode($vimeoFiles)
                      ],
                  ]);

                } catch (Exception $e) {

                }

            }

        }

    }

}
