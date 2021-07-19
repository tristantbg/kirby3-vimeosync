<?php

@include_once __DIR__ . '/vendor/autoload.php';

@include_once __DIR__ . '/src/VimeoSync.php';

@include_once __DIR__ . '/src/models/vimeo.items.php';
@include_once __DIR__ . '/src/models/vimeo.video.php';

Kirby::plugin('tristanb/kirby-vimeosync', [
    'options'     => [
        'cache.api' => false,
    ],
    'routes'      => [
        [
            'pattern' => 'vimeosync/api/videos/get/(:any)',
            'action'  => function ($id) {
                if ($site->user()) {
                    if ($id === 'all') {
                        \VimeoSync\App::getVideos();
                    }
                }
            },
        ],
        [
            'pattern' => 'vimeosync/api/thumbnails/get/(:any)',
            'action'  => function ($id) {
                if ($site->user()) {
                    if ($id === 'all') {
                        \VimeoSync\App::getThumbnails();
                    }
                }
            },
        ],
    ],
    'blueprints'  => [
        'pages/vimeo.items' => __DIR__ . '/src/blueprints/vimeo.items.yml',
        'pages/vimeo.video' => __DIR__ . '/src/blueprints/vimeo.video.yml',
    ],
    'templates'  => [
        'vimeo.items' => __DIR__ . '/src/templates/vimeo.items.php',
        'vimeo.video' => __DIR__ . '/src/templates/vimeo.video.php',
    ],
    'pageMethods' => [
        'vimeoSD'  => function () {
            return $this->vimeoFiles()->toStructure()->filterBy('quality', 'sd');
        },
        'vimeoHD'  => function () {
            return $this->vimeoFiles()->toStructure()->filterBy('quality', 'hd');
        },
        'vimeoHls' => function () {
            return $this->vimeoFiles()->toStructure()->filterBy('quality', 'hls');
        },
        'vimeoTag' => function ($options = array('')) {

            $vimeoPage = $this;

            $thumbnails = $vimeoPage->vimeoThumbnails()->toStructure();

            $thumbSD = $thumbnails->filter(function($thumb) use ($vimeoPage){
              if ($vimeoSD = $vimeoPage->vimeoSD()->last()) {
                return ($vimeoPage->vimeoSD()->last()->width()->int()/$vimeoPage->vimeoSD()->last()->height()->int()) == ($thumb->width()->int()/$thumb->height()->int());
              } else {
                return $thumb->width()->int() <= 640;
              }
            })->last();

            $thumbHD = $thumbnails->filter(function($thumb) use ($vimeoPage){
              if ($vimeoHD = $vimeoPage->vimeoHD()->last()) {
                return ($vimeoPage->vimeoHD()->last()->width()->int()/$vimeoPage->vimeoHD()->last()->height()->int()) == ($thumb->width()->int()/$thumb->height()->int());
              } else {
                return $thumb->width()->int() > 640 && $thumb->width()->int() <= 960;
              }
            })->last();

            if($vimeoPage->vimeoThumbnails()->isNotEmpty() && $thumbSD) {
              $poster = strtok($thumbSD->link(), '?');

            } else if($vimeoPage->vimeoThumbnails()->isNotEmpty() && $thumbHD) {
              $poster = strtok($thumbHD->link(), '?');
            }
            if (!empty($options['poster'])) {
                $poster = $options['poster'];
            }

            if($cover = $vimeoPage->cover()->toFile()) $placeholder = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '. $cover->width() .' '. $cover->height() .'"%3E%3C/svg%3E';
            if(!empty($options['placeholder'])) $placeholder = $options['placeholder'];

            $videoContainerArgs = ['class' => 'player-container', 'g-component' => 'ResponsiveVideo'];
            $videoSources       = [];
            $videoArgs          = [
                'class'       => 'video-player',
                'poster' => isset($placeholder) ? $placeholder : $poster,
                'data-poster' => isset($poster) ? $poster : null,
                'width'       => '100%',
                'height'      => 'auto',
                'preload'     => 'none',
            ];
            if (!empty($options['class'])) {
                $videoArgs['class'] .= ' ' . $options['class'];
            }

            if (!empty($options['controls']) && $options['controls']) {
                $videoArgs['class'] .= ' controls';
            }

            if (!empty($options['controls']) && $options['controls']) {
                $videoArgs['controls'] = true;
            }

            if (!empty($options['loop']) && $options['loop']) {
                $videoArgs['loop'] = 'loop';
            }

            if (!empty($options['muted']) && $options['muted']) {
                $videoArgs['muted'] = 'muted';
            }

            if (!empty($options['playsinline']) && $options['playsinline']) {
                $videoArgs['playsinline'] = 1;
            }

            if (!empty($options['autoplay']) && $options['autoplay']) {
                $videoArgs['autoplay'] = true;
            }

            if ($vimeoPage->vimeoFiles()->isNotEmpty()) {
              if ($options['stream'] !== false) {
                if ($hls = $vimeoPage->vimeoHls()->first()) {
                    $videoArgs['data-stream'] = $hls->link();
                }
              }

              if ($options['hd'] !== false) {
                  if ($vimeoPage->vimeoHD()->last()) {
                    $hd                   = $vimeoPage->vimeoHD()->last()->link();
                    $videoArgs['data-hd'] = $hd;
                    // $videoSources[]       = Html::tag('source', null, ['src' => $hd, 'type' => 'video/mp4']);
                  }
              } else {
                if ($vimeoPage->vimeoSD()->nth(2)) {
                  $hd                   = $vimeoPage->vimeoSD()->nth(2)->link();
                  $videoArgs['data-hd'] = $hd;
                  // $videoSources[]       = Html::tag('source', null, ['src' => $hd, 'type' => 'video/mp4']);
                }
              }
              if ($vimeoPage->vimeoSD()->nth(1)) {
                  $sd                   = $vimeoPage->vimeoSD()->nth(1)->link();
                  $videoArgs['data-sd'] = $sd;
                  if (!isset($hd)) {
                      // $videoSources[] = Html::tag('source', null, ['src' => $sd, 'type' => 'video/mp4']);
                  }
              }
          }

            // $video          = Html::tag('video', [implode('', $videoSources)], $videoArgs);
            $video          = Html::tag('video', [], $videoArgs);
            $videoContainer = Html::tag('div', [$video], $videoContainerArgs);

            return $videoContainer;
        },
    ],
]);
