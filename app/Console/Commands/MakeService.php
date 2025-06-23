<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeService extends Command
{
    protected $signature = 'make:service {name}';
    protected $description = 'Generate a new service class';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $name = $this->argument('name');
        $path = app_path("Services/{$name}.php");

        if (file_exists($path)) {
            $this->error('Service already exists!');
            return;
        }

        (new Filesystem)->ensureDirectoryExists(app_path('Services'));

        $template = <<<EOT
<?php

namespace App\Services;

class {$name}
{

    public function index()
    {
        // Handle logic
    }
}
EOT;

        file_put_contents($path, $template);

        $this->info("Service class {$name} created successfully.");
    }
}
