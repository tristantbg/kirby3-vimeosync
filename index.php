<?php

@include_once __DIR__ . '/vendor/autoload.php';

@include_once __DIR__ . '/src/VimeoSync.php';

@include_once __DIR__ . '/src/models/vimeo.items.php';
@include_once __DIR__ . '/src/models/vimeo.video.php';

Kirby::plugin('tristanb/kirby-vimeosync', [
    'options'    => [
        'cache.api' => false,
    ],
    'routes'     => [
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
    'blueprints' => [
        'pages/vimeo.items' => __DIR__ . '/src/blueprints/vimeo.items.yml',
        'pages/vimeo.video' => __DIR__ . '/src/blueprints/vimeo.video.yml',
    ],
    'pageMethods' => [
      'vimeoSD' => function(){
        return $this->vimeoFiles()->toStructure()->filterBy('quality', 'sd');
      },
      'vimeoHD' => function(){
        return $this->vimeoFiles()->toStructure()->filterBy('quality', 'hd');
      },
      'vimeoHls' => function(){
        return $this->vimeoFiles()->toStructure()->filterBy('quality', 'hls');
      }
    ]
]);
