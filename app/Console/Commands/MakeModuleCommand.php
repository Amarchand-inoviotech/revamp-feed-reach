<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name : The name of the module} {--dry-run : Preview the files that would be created without actually creating them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module with all necessary files (migration, model, repository, controller, request, resource, route)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $name = $this->generateModuleName($name);
        $singularName = Str::singular($name);
        $pluralName = Str::plural($name);
        $modelName = Str::studly($singularName);
        $tableName = Str::snake($pluralName);
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info("DRY RUN: Previewing {$modelName} module files that would be created...");
        } else {
            $this->info("Creating {$modelName} module...");
            // Create directories if they don't exist
            $this->createDirectories();
        }

        // Create Migration
        $this->createMigration($tableName, $isDryRun);

        // Create Model
        $this->createModel($modelName, $isDryRun);

        // Create Repository Contract
        $this->createRepositoryContract($modelName, $isDryRun);

        // Create Repository Implementation
        $this->createRepository($modelName, $isDryRun);

        // Create Request
        $this->createRequest($modelName, $isDryRun);

        // Create Resource
        $this->createResource($modelName, $isDryRun);

        // Create Controller
        $this->createController($modelName, $isDryRun);

        // Create Route File
        $this->createRouteFile($singularName, $isDryRun);

        // Update RolePermissionSeeder with new module permissions
        if (!$isDryRun) {
            $this->updateRolePermissionSeeder($singularName);

            // Add repository binding to RepositoryServiceProvider
            $this->addRepositoryBinding($modelName);

            // Register the route file
            $this->registerRouteFile($singularName);
        }

        if ($isDryRun) {
            $this->info("DRY RUN: Preview of {$modelName} module files completed. No files were created.");
        } else {
            $this->info("{$modelName} module created successfully!");
        }
    }

    /**
     * Create necessary directories if they don't exist
     */
    protected function createDirectories()
    {
        $directories = [
            app_path('Http/Controllers/Api/Backoffice'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            app_path('Models'),
            app_path('Repositories/Contracts'),
            app_path('Repositories/Eloquents'),
            base_path('routes/api/back-office'),
        ];

        foreach ($directories as $directory) {
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
        }
    }

    /**
     * Create migration file
     */
    protected function createMigration($tableName, $isDryRun = false)
    {

        if ($isDryRun) {
            $timestamp = date('Y_m_d_His');
            $migrationName = "create_{$tableName}_table";
            $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");

            $this->info("DRY RUN: Would create migration: {$migrationPath}");
            $this->info("DRY RUN: Migration would contain: id, uuid, author morphs, name, timestamps, soft deletes");
            return;
        }

        $this->info('Creating migration...');
        Artisan::call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);

        $migrationPath = $this->getLatestMigrationFile();

        if ($migrationPath) {
            $migrationContent = File::get($migrationPath);

            // Add uuid, morph auth, and name to migration
            $migrationContent = str_replace(
                '$table->id();',
                '$table->id();
            $table->uuid()->unique()->index();
            $table->morphs(\'author\');
            $table->string(\'name\', 100);',
                $migrationContent
            );

            $migrationContent = str_replace(
                '$table->timestamps();',
                '$table->timestamps();
            $table->softDeletes();',
                $migrationContent
            );

            File::put($migrationPath, $migrationContent);
            $this->info('Migration created successfully!');
        }
    }

    /**
     * Get the latest migration file path
     */
    protected function getLatestMigrationFile()
    {
        $migrationFiles = File::glob(database_path('migrations/*.php'));
        return end($migrationFiles) ?: null;
    }

    /**
     * Create model file
     */
    protected function createModel($modelName, $isDryRun = false)
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if ($isDryRun) {
            $this->info("DRY RUN: Would create model: {$modelPath}");
            $this->info("DRY RUN: Model would use ModelTrait and have 'name' as fillable field");
            return;
        }

        $this->info('Creating model...');

        $modelContent = <<<EOT
<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {$modelName} extends Model
{
    use ModelTrait, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected \$fillable = [
        'name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected \$hidden = [
        'updated_at',
        'deleted_at'
    ];


}
EOT;

        File::put($modelPath, $modelContent);
        $this->info('Model created successfully!');
    }

    /**
     * Create repository contract
     */
    protected function createRepositoryContract($modelName, $isDryRun = false)
    {
        $contractPath = app_path("Repositories/Contracts/{$modelName}RepositoryContract.php");

        if ($isDryRun) {
            $this->info("DRY RUN: Would create repository contract: {$contractPath}");
            $this->info("DRY RUN: Contract would extend BaseRepositoryContract");
            return;
        }

        $this->info('Creating repository contract...');

        $contractContent = <<<EOT
<?php

namespace App\Repositories\Contracts;

interface {$modelName}RepositoryContract extends BaseRepositoryContract
{
    // Add any model-specific methods here
}
EOT;

        File::put($contractPath, $contractContent);
        $this->info('Repository contract created successfully!');
    }

    /**
     * Create repository implementation
     */
    protected function createRepository($modelName, $isDryRun = false)
    {
        $repositoryPath = app_path("Repositories/Eloquents/{$modelName}Repository.php");

        if ($isDryRun) {
            $this->info("DRY RUN: Would create repository implementation: {$repositoryPath}");
            $this->info("DRY RUN: Repository would implement {$modelName}RepositoryContract and extend BaseRepository");
            return;
        }

        $this->info('Creating repository implementation...');

        $repositoryContent = <<<EOT
<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\\{$modelName}Resource;
use App\Models\\{$modelName};
use App\Repositories\Contracts\\{$modelName}RepositoryContract;

class {$modelName}Repository extends BaseRepository implements {$modelName}RepositoryContract
{
    /**
     * Default relations to load
     */
    protected array \$defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(
        protected {$modelName} \$model,
        protected string \$resource = {$modelName}Resource::class
    ) {}

    /**
     * Get search callback for {$modelName} model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function(\$query, \$search) {
            \$query->where('name', 'like', "%{\$search}%");
        };
    }
}
EOT;

        File::put($repositoryPath, $repositoryContent);
        $this->info('Repository implementation created successfully!');
    }

    /**
     * Create request file
     */
    protected function createRequest($modelName, $isDryRun = false)
    {
        $requestPath = app_path("Http/Requests/{$modelName}Request.php");
        $modelParam = Str::camel($modelName);
        $tableName = Str::plural(Str::snake($modelName));

        if ($isDryRun) {
            $this->info("DRY RUN: Would create request: {$requestPath}");
            $this->info("DRY RUN: Request would validate 'name' field with unique rule");
            return;
        }

        $this->info('Creating request...');

        $requestContent = "<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class {$modelName}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('{$tableName}')->ignore(\$this->route('{$modelParam}'))],
        ];
    }
}";

        File::put($requestPath, $requestContent);
        $this->info('Request created successfully!');
    }

    /**
     * Create resource file
     */
    protected function createResource($modelName, $isDryRun = false)
    {
        $resourcePath = app_path("Http/Resources/{$modelName}Resource.php");

        if ($isDryRun) {
            $this->info("DRY RUN: Would create resource: {$resourcePath}");
            $this->info("DRY RUN: Resource would transform id, name, and created_at fields");
            return;
        }

        $this->info('Creating resource...');

        $resourceContent = <<<EOT
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {$modelName}Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request \$request): array
    {
        return [
            "id" => \$this->uuid,
            "name" => \$this->name,
            "created_at" => \$this->created_at?->format(DATE_FORMAT),
        ];
    }
}
EOT;

        File::put($resourcePath, $resourceContent);
        $this->info('Resource created successfully!');
    }

    /**
     * Create controller file
     */
    protected function createController($modelName, $isDryRun = false)
    {
        $controllerPath = app_path("Http/Controllers/Api/Backoffice/{$modelName}Controller.php");
        $modelParam = strtolower($modelName);

        if ($isDryRun) {
            $this->info("DRY RUN: Would create controller: {$controllerPath}");
            $this->info("DRY RUN: Controller would have CRUD methods (index, store, show, update, destroy)");
            return;
        }

        $this->info('Creating controller...');

        $controllerContent = "<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\\{$modelName}Request;
use App\Http\Requests\IndexMethodRequest;
use App\Models\\{$modelName};
use App\Repositories\Contracts\\{$modelName}RepositoryContract;
use Exception;

class {$modelName}Controller extends Controller
{
    public function __construct(
        protected {$modelName}RepositoryContract \$repo,
        protected string \$model = '{$modelName}'
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest \$request)
    {
        \$validated = \$request->validated();

        // You can add specific filters here if needed
        // Example: Add status filter if provided in the request
        if (\$request->has('status')) {
            \$validated['filters'] = \$validated['filters'] ?? [];
            \$validated['filters']['status'] = \$request->input('status');
        }

        \$params = RequestParams::fromValidatedRequest(\$validated);
        \$response = \$this->repo->getAll(\$params);

        return successResponse(\$response, trans('generic.index', ['model' => \$this->model]), 200, \$params->paginate);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store({$modelName}Request \$request)
    {
        \$payload = \$request->validated();
        try {
            \$response = \$this->repo->storeModel(\$payload);
            return successResponse(\$response, trans('generic.store', ['model' => \$this->model]));
        } catch (Exception \$e) {
            return errorResponse(\$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show({$modelName} \${$modelParam})
    {
        \$response = \$this->repo->showModel(\${$modelParam});
        return successResponse(\$response, trans('generic.show', ['model' => \$this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update({$modelName}Request \$request, {$modelName} \${$modelParam})
    {
        \$payload = \$request->validated();
        try {
            \$response = \$this->repo->updateModel(\${$modelParam}, \$payload);
            return successResponse(\$response, trans('generic.update', ['model' => \$this->model]));
        } catch (Exception \$e) {
            return errorResponse(\$e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy({$modelName} \${$modelParam})
    {
        try {
            \$response = \$this->repo->softDeleteModel(\${$modelParam});
            return successResponse(\$response, trans('generic.destroy', ['model' => \$this->model]));
        } catch (Exception \$e) {
            return errorResponse(\$e->getMessage(), 500);
        }
    }
}";

        File::put($controllerPath, $controllerContent);
        $this->info('Controller created successfully!');
    }

    /**
     * Create route file
     */
    protected function createRouteFile($singularName, $isDryRun = false)
    {
        $modelName = Str::studly($singularName);
        $modelParam = Str::camel($singularName);
        $routeFileNam = Str::kebab($singularName);
        $routePath = base_path("routes/api/back-office/{$routeFileNam}.php");

        if ($isDryRun) {
            $this->info("DRY RUN: Would create route file: {$routePath}");
            $this->info("DRY RUN: Route would register API resource routes for {$modelName}Controller");
            return;
        }

        $this->info('Creating route file...');

        $routeContent = <<<EOT
<?php

use App\Http\Controllers\Api\Backoffice\\{$modelName}Controller;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', {$modelName}Controller::class, ['parameters' => ['' => '{$modelParam}']])
        ->middleware('permission:{$modelParam}');
});
EOT;

        File::put($routePath, $routeContent);
        $this->info('Route file created successfully!');
    }

    /**
     * Update RolePermissionSeeder with new module permissions
     *
     * @param string $singularName
     * @return void
     */
    protected function updateRolePermissionSeeder(string $singularName): void
    {
        $seederPath = database_path('seeders/RolePermissionSeeder.php');

        if (!File::exists($seederPath)) {
            $this->warn('RolePermissionSeeder.php not found. Skipping permission update.');
            return;
        }

        $this->info('Updating RolePermissionSeeder with new module permissions...');

        $seederContent = File::get($seederPath);
        $moduleParam = Str::kebab($singularName);

        // Define the new permissions for the module with correct indentation
        $newPermissions = "            // " . ucfirst($singularName) . " management\n";
        $newPermissions .= "            '{$moduleParam}-view',\n";
        $newPermissions .= "            '{$moduleParam}-create',\n";
        $newPermissions .= "            '{$moduleParam}-edit',\n";
        $newPermissions .= "            '{$moduleParam}-list',\n";
        $newPermissions .= "            '{$moduleParam}-delete',\n";
        $newPermissions .= "            '{$moduleParam}-restore',\n";
        $newPermissions .= "            '{$moduleParam}-export',\n\n";

        // Find the position to insert the new permissions (right before the closing bracket of generalPermissions)
        $pattern = "/(\s+\];\s+if \(\\\$guard == 'admin'\))/s";

        if (preg_match($pattern, $seederContent, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];

            // Insert the new permissions
            $updatedContent = substr($seederContent, 0, $position) . "\n" . $newPermissions . substr($seederContent, $position);

            // Write the updated content back to the file
            File::put($seederPath, $updatedContent);

            $this->info('RolePermissionSeeder updated successfully with new module permissions!');
        } else {
            $this->warn('Could not find the right position to insert permissions in RolePermissionSeeder. Please add them manually.');
        }
    }

    /**
     * Add repository binding to RepositoryServiceProvider
     *
     * @param string $modelName
     * @return void
     */
    protected function addRepositoryBinding(string $modelName): void
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->warn('RepositoryServiceProvider.php not found. Skipping repository binding.');
            return;
        }

        $this->info('Adding repository binding to RepositoryServiceProvider...');

        // Create the binding line
        $repositoryClass = "\\App\\Repositories\\Eloquents\\{$modelName}Repository";
        $contractInterface = "\\App\\Repositories\\Contracts\\{$modelName}RepositoryContract";
        $bindingLine = "        \$this->app->bind({$contractInterface}::class, {$repositoryClass}::class);";

        // Read the file content
        $content = File::get($providerPath);

        // Check if the binding already exists
        if (strpos($content, $contractInterface) !== false) {
            $this->info('Repository binding already exists. Skipping...');
            return;
        }

        // Try to find the last binding line
        if ($this->insertAfterLastBinding($providerPath, $bindingLine)) {
            return;
        }

        // If that fails, try the fallback method
        if ($this->insertBeforeRegisterClosingBrace($providerPath, $bindingLine)) {
            return;
        }

        // If all else fails, show the binding line for manual addition
        $this->warn('Could not find a suitable position to add the binding in RepositoryServiceProvider. Please add the binding manually:');
        $this->line($bindingLine);
    }

    /**
     * Insert binding after the last existing binding
     *
     * @param string $providerPath
     * @param string $bindingLine
     * @return bool
     */
    private function insertAfterLastBinding(string $providerPath, string $bindingLine): bool
    {
        $content = File::get($providerPath);
        $lines = explode("\n", $content);

        $lastBindingLine = null;

        // Find the last binding line
        foreach ($lines as $i => $line) {
            if (strpos($line, '$this->app->bind') !== false) {
                $lastBindingLine = $i;
            }
        }

        if ($lastBindingLine !== null) {
            // Insert the new binding after the last binding
            array_splice($lines, $lastBindingLine + 1, 0, $bindingLine);

            // Join the lines back together and write to file
            File::put($providerPath, implode("\n", $lines));

            $this->info('Repository binding added successfully!');
            return true;
        }

        return false;
    }

    /**
     * Insert binding before the closing brace of the register method
     *
     * @param string $providerPath
     * @param string $bindingLine
     * @return bool
     */
    private function insertBeforeRegisterClosingBrace(string $providerPath, string $bindingLine): bool
    {
        $content = File::get($providerPath);

        $pattern = "/(\s+public function register\(\).*?\n.*?)(\s{4}\})/s";
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $closingBracePosition = $matches[2][1];

            // Insert the binding
            $updatedContent = substr($content, 0, $closingBracePosition) .
                $bindingLine . "\n" .
                substr($content, $closingBracePosition);

            // Write the updated content back to the file
            File::put($providerPath, $updatedContent);

            $this->info('Repository binding added successfully (fallback method)!');
            return true;
        }

        return false;
    }

    /**
     * Register the route file in the RouteServiceProvider
     *
     * @param string $singularName
     * @return void
     */
    protected function registerRouteFile(string $singularName): void
    {
        $providerPath = app_path('Providers/RouteServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->warn('RouteServiceProvider.php not found. The route will be registered automatically during composer dump-autoload.');
            $this->info('Run "composer dump-autoload" to register the route.');
            return;
        }

        $this->info('Adding route registration to RouteServiceProvider...');

        $routeFileName = Str::kebab($singularName);
        $pluralName = Str::plural($routeFileName);

        // Create the route registration line (grouped format)
        $routeRegistration = "                Route::as('{$pluralName}.')->prefix('{$pluralName}')->group(base_path('routes/api/back-office/{$routeFileName}.php'));";

        // Read the file content
        $content = File::get($providerPath);

        // Check if the route registration already exists
        if (strpos($content, "routes/api/back-office/{$routeFileName}.php") !== false) {
            $this->info('Route registration already exists. Skipping...');
            return;
        }

        // Try to find the last route registration in mapBackOfficeRoutes method
        if ($this->insertAfterLastBackOfficeRoute($providerPath, $routeRegistration)) {
            return;
        }

        // If that fails, show the registration line for manual addition
        $this->warn('Could not find a suitable position to add the route registration in RouteServiceProvider. Please add the registration manually:');
        $this->line($routeRegistration);
        $this->info('Or run "php artisan routes:generate-registration" to regenerate all route registrations.');
    }

    /**
     * Insert route registration after the last back office route
     *
     * @param string $providerPath
     * @param string $routeRegistration
     * @return bool
     */
    protected function insertAfterLastBackOfficeRoute(string $providerPath, string $routeRegistration): bool
    {
        $content = File::get($providerPath);

        // Find the mapBackOfficeRoutes method and the last route registration (grouped format)
        $pattern = "/(protected function mapBackOfficeRoutes\(\): void\s*\{.*?->group\(function \(\) \{.*?)(                Route::as\('.*?\);)(\s+\}\);)/s";

        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastRouteEnd = $matches[2][1] + strlen($matches[2][0]);

            // Insert the new route registration
            $updatedContent = substr($content, 0, $lastRouteEnd) .
                "\n" . $routeRegistration .
                substr($content, $lastRouteEnd);

            // Write the updated content back to the file
            File::put($providerPath, $updatedContent);

            $this->info('Route registration added successfully!');
            return true;
        }

        return false;
    }

    /**
     * Generate a standardized module name
     *
     * @param string $string
     * @return string
     */
    protected function generateModuleName(string $string): string
    {
        // Replace underscores, hyphens, and multiple underscores with a space
        $string = preg_replace('/[_\-]+/', ' ', $string);

        // Lowercase the whole string
        $string = strtolower($string);

        // Capitalize each word and remove spaces
        $string = str_replace(' ', '', ucwords($string));

        // Lowercase the first character
        return lcfirst($string);
    }
}
