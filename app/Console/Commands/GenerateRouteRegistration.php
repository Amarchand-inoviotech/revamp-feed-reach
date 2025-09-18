<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateRouteRegistration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'routes:generate-registration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate individual route registrations for the RouteServiceProvider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating individual route registrations...');

        $fileSystem = new Filesystem;
        $backOfficeRoutes = [];
        $frontOfficeRoutes = [];

        // Routes that should remain singular in URLs (e.g., auth.php -> /auth, not /auths)
        // Add route file names (without .php) that should NOT be pluralized
        $singularRoutes = ['auth', 'admin','guest','subscribe'];

        $routeStats = [
            'back-office' => 0,
            'front-office' => 0
        ];

        // Process back-office routes
        $backOfficeDir = base_path('routes/api/back-office');
        if ($fileSystem->isDirectory($backOfficeDir)) {
            $this->processRouteFiles(
                $fileSystem->files($backOfficeDir),
                'back-office',
                'back-offices',
                $singularRoutes,
                $backOfficeRoutes
            );
            $routeStats['back-office'] = count($backOfficeRoutes);
        }

        // Process front-office routes
        $frontOfficeDir = base_path('routes/api/front-office');
        if ($fileSystem->isDirectory($frontOfficeDir)) {
            $this->processRouteFiles(
                $fileSystem->files($frontOfficeDir),
                'front-office',
                'front-offices',
                $singularRoutes,
                $frontOfficeRoutes
            );
            $routeStats['front-office'] = count($frontOfficeRoutes);
        }

        // Sort the route registrations alphabetically for consistency
        sort($backOfficeRoutes);
        sort($frontOfficeRoutes);

        // Generate a completely new RouteServiceProvider file with proper indentation
        $providerPath = app_path('Providers/RouteServiceProvider.php');

        // Create the entire file content with proper indentation
        $updatedContent = $this->generateRouteServiceProvider($backOfficeRoutes, $frontOfficeRoutes);

        // Write the updated provider file
        file_put_contents($providerPath, $updatedContent);

        // Display summary
        $totalRoutes = $routeStats['back-office'] + $routeStats['front-office'];
        $this->info('Individual route registrations generated successfully with ' . $totalRoutes . ' routes.');
        $this->info('');
        $this->info('Summary:');
        $this->info('- Back office routes: ' . $routeStats['back-office']);
        $this->info('- Front office routes: ' . $routeStats['front-office']);
    }

    /**
     * Process route files to generate registrations
     *
     * @param array $files
     * @param string $dirType
     * @param string $pluralDirType
     * @param array $singularRoutes
     * @param array &$routeRegistrations
     * @return void
     */
    protected function processRouteFiles(array $files, string $dirType, string $pluralDirType, array $singularRoutes, array &$routeRegistrations): void
    {
        foreach ($files as $file) {
            $baseName = $file->getBasename();
            $parseName = pathinfo($baseName, PATHINFO_FILENAME);

            // Skip files that don't look like route files
            if (!Str::endsWith($baseName, '.php')) {
                continue;
            }

            $pluralName = in_array($parseName, $singularRoutes)
                ? $parseName
                : Str::plural($parseName);

            $routeRegistrations[] = "Route::as('{$pluralName}.')->prefix('{$pluralName}')->group(base_path('routes/api/{$dirType}/{$baseName}'));";
            $this->info("Added route registration for {$dirType}/{$baseName}");
        }
    }

    /**
     * Generate the complete RouteServiceProvider content
     *
     * @param array $backOfficeRoutes
     * @param array $frontOfficeRoutes
     * @return string
     */
    protected function generateRouteServiceProvider(array $backOfficeRoutes, array $frontOfficeRoutes): string
    {
        $backOfficeRoutesFormatted = $this->formatGroupedRoutes($backOfficeRoutes, 16);
        $frontOfficeRoutesFormatted = $this->formatGroupedRoutes($frontOfficeRoutes, 16);

        return <<<EOT
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        \$this->mapApiRoutes();
        \$this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')
            ->group(function () {
                \$this->mapBackOfficeRoutes();
                \$this->mapFrontOfficeRoutes();
            });
    }

    /**
     * Map back office routes - Individual route registrations generated automatically
     */
    protected function mapBackOfficeRoutes(): void
    {
        Route::as('api.back-offices.')
            ->prefix('api/back-offices')
            ->group(function () {
{$backOfficeRoutesFormatted}
            });
    }

    /**
     * Map front office routes - Individual route registrations generated automatically
     */
    protected function mapFrontOfficeRoutes(): void
    {
        Route::as('api.front-offices.')
            ->prefix('api/front-offices')
            ->group(function () {
{$frontOfficeRoutesFormatted}
            });
    }
}
EOT;
    }

    /**
     * Format route registrations with proper indentation for grouped routes
     *
     * @param array $routes
     * @param int $indentSpaces
     * @return string
     */
    protected function formatGroupedRoutes(array $routes, int $indentSpaces): string
    {
        $indent = str_repeat(' ', $indentSpaces);
        return implode("\n", array_map(function ($route) use ($indent) {
            return $indent . $route;
        }, $routes));
    }

    /**
     * Format route registrations with proper indentation (legacy method)
     *
     * @param array $routes
     * @param int $indentSpaces
     * @return string
     */
    protected function formatRoutes(array $routes, int $indentSpaces): string
    {
        $indent = str_repeat(' ', $indentSpaces);
        return implode("\n", array_map(function ($route) use ($indent) {
            return $indent . $route;
        }, $routes));
    }
}
