<?php

return [
    'bnomei.janitor.jobs' => [
        'vimeosync_getvideos'     => function (Kirby\Cms\Page $page = null, string $data = null) {

            $job = \VimeoSync\App::getVideos();

            return [
                'status' => $job ? 200 : 404,
                'reload' => true,
                'label'  => 'Synced!',
            ];
        },
        'vimeosync_getthumbnails' => function (Kirby\Cms\Page $page = null, string $data = null) {

            if ($page->intendedTemplate() == 'vimeo.video') {
              $job = \VimeoSync\App::getThumbnails($page);
            } else {
              $job = \VimeoSync\App::getThumbnails();
            }

            return [
                'status' => $job ? 200 : 404,
                'reload' => true,
                'label'  => 'Synced!',
            ];
        },
    ]
];
