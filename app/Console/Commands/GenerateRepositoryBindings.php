<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateRepositoryBindings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repository:generate-bindings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate individual repository bindings for the RepositoryServiceProvider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating individual repository bindings...');

        $fileSystem = new Filesystem;
        $repositoryBindings = [];
        $bindingStats = [
            'root' => 0,
            'subdirectories' => []
        ];
        $repositoryNamespace = 'App\Repositories\Eloquents';
        $contractsNamespace = 'App\Repositories\Contracts';
        $repositoryPath = app_path('Repositories/Eloquents');

        // Process files in the main directory
        $rootCount = count($repositoryBindings);
        $this->processRepositoryFiles(
            $fileSystem->files($repositoryPath),
            $repositoryNamespace,
            $contractsNamespace,
            $repositoryBindings
        );
        $bindingStats['root'] = count($repositoryBindings) - $rootCount;

        // Process files in subdirectories
        $this->processSubdirectories(
            $fileSystem,
            $repositoryPath,
            app_path('Repositories/Contracts'),
            $repositoryNamespace,
            $contractsNamespace,
            $repositoryBindings,
            $bindingStats
        );

        // Sort the bindings alphabetically for consistency
        sort($repositoryBindings);

        // Generate a completely new RepositoryServiceProvider file with proper indentation
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        // Create the entire file content with proper indentation
        $updatedContent = <<<EOT
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Individual repository bindings generated automatically
{$this->formatBindings($repositoryBindings, 8)}
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
EOT;

        // Write the updated provider file
        file_put_contents($providerPath, $updatedContent);

        // Display summary
        $this->info('Individual repository bindings generated successfully with ' . count($repositoryBindings) . ' repositories.');
        $this->info('');
        $this->info('Summary:');
        $this->info('- Root directory: ' . $bindingStats['root'] . ' bindings');

        foreach ($bindingStats['subdirectories'] as $subDir => $count) {
            $this->info("- {$subDir} subdirectory: {$count} bindings");
        }
    }

    /**
     * Process subdirectories recursively
     *
     * @param Filesystem $fileSystem
     * @param string $repositoryPath
     * @param string $contractsPath
     * @param string $repositoryNamespace
     * @param string $contractsNamespace
     * @param array &$repositoryBindings
     * @param array &$bindingStats
     * @param string $path
     * @return void
     */
    protected function processSubdirectories(
        Filesystem $fileSystem,
        string $repositoryPath,
        string $contractsPath,
        string $repositoryNamespace,
        string $contractsNamespace,
        array &$repositoryBindings,
        array &$bindingStats,
        string $path = ''
    ): void {
        $currentRepoPath = $path ? "{$repositoryPath}/{$path}" : $repositoryPath;

        foreach ($fileSystem->directories($currentRepoPath) as $directory) {
            $subDirName = basename($directory);
            $subPath = $path ? "{$path}/{$subDirName}" : $subDirName;
            $subRepositoryNamespace = "{$repositoryNamespace}\\" . str_replace('/', '\\', $subPath);
            $subContractsNamespace = "{$contractsNamespace}\\" . str_replace('/', '\\', $subPath);
            $contractsSubDir = $path ? "{$contractsPath}/{$path}/{$subDirName}" : "{$contractsPath}/{$subDirName}";

            // Check if the corresponding contracts subdirectory exists
            if (!$fileSystem->isDirectory($contractsSubDir)) {
                $this->warn("Contracts subdirectory not found: {$contractsSubDir}");
                continue;
            }

            $this->info("Processing subdirectory: " . ($path ? "{$path}/{$subDirName}" : $subDirName));

            // Count bindings before processing
            $beforeCount = count($repositoryBindings);

            // Process files in the subdirectory
            $this->processRepositoryFiles(
                $fileSystem->files($directory),
                $subRepositoryNamespace,
                $subContractsNamespace,
                $repositoryBindings
            );

            // Store the count of bindings added for this subdirectory
            $bindingStats['subdirectories'][$subPath] = count($repositoryBindings) - $beforeCount;

            // Process nested subdirectories recursively
            $this->processSubdirectories(
                $fileSystem,
                $repositoryPath,
                $contractsPath,
                $repositoryNamespace,
                $contractsNamespace,
                $repositoryBindings,
                $bindingStats,
                $subPath
            );
        }
    }

    /**
     * Process repository files to generate bindings
     *
     * @param array $files
     * @param string $repositoryNamespace
     * @param string $contractsNamespace
     * @param array &$repositoryBindings
     * @return void
     */
    protected function processRepositoryFiles(array $files, string $repositoryNamespace, string $contractsNamespace, array &$repositoryBindings): void
    {
        foreach ($files as $file) {
            $className = $file->getBasename('.php');

            // Skip files that don't look like repository classes
            if (Str::contains($className, ['Interface', 'Abstract']) || $className === 'BaseRepository') {
                continue;
            }

            $repositoryClass = "{$repositoryNamespace}\\{$className}";
            $contractInterface = "{$contractsNamespace}\\{$className}Contract";

            // Check if both the repository class and contract interface exist
            if (class_exists($repositoryClass) && interface_exists($contractInterface)) {
                $repositoryBindings[] = "\$this->app->bind(\\{$contractInterface}::class, \\{$repositoryClass}::class);";
                $this->info("Added binding for {$className}");
            } else {
                if (!class_exists($repositoryClass)) {
                    $this->warn("Repository class not found: {$repositoryClass}");
                }
                if (!interface_exists($contractInterface)) {
                    $this->warn("Contract interface not found: {$contractInterface}");
                }
            }
        }
    }

    /**
     * Format the repository bindings with proper indentation
     *
     * @param array $bindings
     * @param int $spaces Number of spaces to indent
     * @return string
     */
    protected function formatBindings(array $bindings, int $spaces = 8): string
    {
        // Add proper indentation to each binding
        $indentation = str_repeat(' ', $spaces);

        // Process each binding to ensure consistent indentation
        $formattedBindings = array_map(function ($binding) use ($indentation) {
            // Remove any existing indentation first
            $binding = trim($binding);
            // Add the new indentation
            return $indentation . $binding;
        }, $bindings);

        return implode("\n", $formattedBindings);
    }
}
