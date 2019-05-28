<?php

@include_once __DIR__ . '/vendor/autoload.php';

@include_once __DIR__ . '/src/VimeoSync.php';

@include_once __DIR__ . '/src/models/vimeo.items.php';
@include_once __DIR__ . '/src/models/vimeo.video.php';

Kirby::plugin('tristanb/kirby-vimeosync', [
    'options' => [
      'cache.api' => false
    ],
    'blueprints' => [
        'pages/vimeo.items' => __DIR__ . '/src/blueprints/vimeo.items.yml',
        'pages/vimeo.video' => __DIR__ . '/src/blueprints/vimeo.video.yml'
    ]
]);
