<?php

return [
    'url' => env('GITEA_URL'),
    'token' => env('GITEA_TOKEN'),
    'clone_timeout' => env('GITEA_CLONE_TIMEOUT', 300),
];
