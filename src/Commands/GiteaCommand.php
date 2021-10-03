<?php

namespace Ikasgela\Gitea\Commands;

use Illuminate\Console\Command;

class GiteaCommand extends Command
{
    public $signature = 'laravel-gitea';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
