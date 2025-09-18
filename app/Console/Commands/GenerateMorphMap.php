<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GenerateMorphMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'morph:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate individual model mappers for the MorphMapServiceProvider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating individual model mappers...');

        $fileSystem = new Filesystem;
        $modelMapperCode = [];

        // Scan the Models directory
        foreach ($fileSystem->allFiles(base_path("app/Models")) as $file) {
            // Skip directories and non-PHP files
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $file->getBasename('.php');

            // Skip files that don't look like model classes
            if (Str::contains($className, ['Interface', 'Trait', 'Abstract'])) {
                continue;
            }

            // Create the fully qualified class name
            $fullClassName = "\\App\\Models\\{$className}";

            // Add to the model mapper code
            $modelMapperCode[] = "'{$className}' => {$fullClassName}::class,";
        }

        // Sort the model mappers alphabetically for consistency
        sort($modelMapperCode);

        // Generate the updated MorphMapServiceProvider content
        $providerPath = app_path('Providers/MorphMapServiceProvider.php');

        // Create a completely new file with proper formatting
        $updatedContent = <<<EOT
<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class MorphMapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register individual model mappers for optimal performance
        Relation::morphMap([
{$this->formatModelMappers($modelMapperCode)}
        ]);
    }
}
EOT;

        // Write the updated provider file
        file_put_contents($providerPath, $updatedContent);

        $this->info('Individual model mappers generated successfully with ' . count($modelMapperCode) . ' models.');
    }

    /**
     * Format the model mappers with proper indentation
     *
     * @param array $mappers
     * @return string
     */
    protected function formatModelMappers(array $mappers): string
    {
        // Add proper indentation (12 spaces = 4 spaces for class + 4 spaces for method + 4 spaces for array)
        return implode("\n", array_map(function ($line) {
            return '            ' . trim($line);
        }, $mappers));
    }
}
