<?php

return [
    'bnomei.janitor.jobs' => [
        'vimeosync_getvideos'     => function (Kirby\Cms\Page $page = null, string $data = null) {

            \Bnomei\Janitor::log('vimeosync_getvideos.mask ' . time());

            $job = \VimeoSync\App::getVideos();

            \Bnomei\Janitor::log('vimeosync_getvideos.exit ' . time());
            return [
                'status' => $job ? 200 : 404,
                'label'  => 'Synced!',
            ];
        },
        'vimeosync_getthumbnails' => function (Kirby\Cms\Page $page = null, string $data = null) {

            \Bnomei\Janitor::log('vimeosync_getthumbnails.mask ' . time());

            $job = \VimeoSync\App::getThumbnails();

            \Bnomei\Janitor::log('vimeosync_getthumbnails.exit ' . time());
            return [
                'status' => $job ? 200 : 404,
                'label'  => 'Synced!',
            ];
        },
    ]
];
