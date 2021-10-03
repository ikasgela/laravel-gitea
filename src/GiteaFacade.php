<?php

namespace Ikasgela\Gitea;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ikasgela\Gitea\GiteaClient
 */
class GiteaFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-gitea';
    }
}
