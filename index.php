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

            $poster = $this->vimeoThumbnails()->isNotEmpty() ? $this->vimeoThumbnails()->toStructure()->last()->link() : '';
            if (!empty($options['poster'])) {
                $poster = $options['poster'];
            }

            $videoContainerArgs = ['class' => 'player-container'];
            $videoSources       = [];
            $videoArgs          = [
                'class'   => 'video-player',
                'poster'  => $poster,
                'width'   => '100%',
                'height'  => 'auto',
                'preload' => 'auto',
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

            if ($this->vimeoFiles()->isNotEmpty()) {
                if ($hls = $this->vimeoHls()->first()) {
                    $videoArgs['data-stream'] = $hls->link();
                }

                if ($this->vimeoHD()->last()) {
                    $hd                   = $this->vimeoHD()->last()->link();
                    $videoArgs['data-hd'] = $hd;
                    $videoSources[]       = Html::tag('source', null, ['src' => $hd, 'type' => 'video/mp4']);
                }
                if ($this->vimeoSD()->last()) {
                    $sd                   = $this->vimeoSD()->last()->link();
                    $videoArgs['data-sd'] = $sd;
                    if (!isset($hd)) {
                        $videoSources[] = Html::tag('source', null, ['src' => $sd, 'type' => 'video/mp4']);
                    }
                }
            }

            $video          = Html::tag('video', [implode('', $videoSources)], $videoArgs);
            $videoContainer = Html::tag('div', [$video], $videoContainerArgs);

            return $videoContainer;
        },
    ],
]);
