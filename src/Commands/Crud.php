<?php

namespace Idb\Crud\Commands;

use App\Models\Migration;
use Illuminate\Console\Command;
use Idb\Crud\Db;
use Illuminate\Support\Facades\Schema;

class Crud extends Command
{
    public $tableName;
    public $modelDir = '';
    public $baseRoute = '';
    public $translatableFields = array();
    public $translatableSuffix = "_translations";
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idb:crud
        {tableName : The name of the table you want to generate crud for.}
    	{--route-base= : Route base.}
    	{--model-dir= : Model directory.}
    	{--translatable-fields= : translatable fields.}
    	{--translate-suffix= : table translation suffix.}
    	';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate crud for a specific table in the database';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        
        
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    	$this->baseRoute = $this->option('route-base');
    	$this->modelDir = $this->option('model-dir');
        $this->tableName = $this->argument('tableName');

        $this->generateTranslateTable();
        $this->generateModel();
        $this->generateRouteModelBinding();
        $this->generateRoute();
        $this->generateController();
        $this->generateViews();

    }

    public function generateRouteModelBinding()
    {
        $declaration = "\$router->model('".$this->route()."', 'App\\".$this->modelClassName()."');";
        $providerFile = app_path('Providers/RouteServiceProvider.php');
        $fileContent = file_get_contents($providerFile);

        if ( strpos( $fileContent, $declaration ) == false )
        {
            $regex = "/(public\s*function\s*boot\s*\(\s*Router\s*.router\s*\)\s*\{)/";
            if( preg_match( $regex, $fileContent ) )
            {
                $fileContent = preg_replace( $regex, "$1\n\t\t".$declaration, $fileContent );
                file_put_contents($providerFile, $fileContent);
                $this->info("Route model binding inserted successfully in ".$providerFile);
                return true;
            }

            // match was not found for some reason
            $this->warn("Could not add route model binding for the route '".$this->route()."'.");
            $this->warn("Please add the following line manually in {$providerFile}:");
            $this->warn($declaration);
            return false;
        }

        // already exists
        $this->info("Model binding for the route: '".$this->route()."' already exists.");
        $this->info("Skipping...");
        return false;
    }

    public function generateRoute()
    {
        $route = "Route::resource('{$this->route(true)}','" . $this->getBaseRoute(true) . "{$this->controllerClassName()}');";

        $routesFile = app_path('Http/routes.php');
        $routesFileContent = file_get_contents($routesFile);

        if ( strpos( $routesFileContent, $route ) == false )
        {
            $routesFileContent = $this->getUpdatedContent($routesFileContent, $route);
            file_put_contents($routesFile,$routesFileContent);
            $this->info("created route: ".$route);
        }else{
            $this->info("Route: '".$route."' already exists.");
            $this->info("Skipping...");
        }

        return true;
    }

    protected function getUpdatedContent ( $existingContent, $route )
    {
        // check if the user has directed to add routes
        $str = "idb-crud routes go here";
        if( strpos( $existingContent, $str ) !== false )
            return str_replace( $str, "{$str}\n\t".$route, $existingContent );

        // check for 'web' middleware group
        $regex = "/(Route\s*\:\:\s*group\s*\(\s*\[\s*\'middleware\'\s*\=\>\s*\[\s*\'web\'\s*\]\s*\]\s*\,\s*function\s*\(\s*\)\s*\{)/";
        if( preg_match( $regex, $existingContent ) )
            return preg_replace( $regex, "$1\n\t".$route, $existingContent );

        // if there is no 'web' middleware group
        return $existingContent."\n".$route;
    }

    public function generateController()
    {
        $controllerFile = $this->controllersDir().($this->baseRoute?"/".$this->baseRoute:"").'/'.$this->controllerClassName().".php";

        if($this->confirmOverwrite($controllerFile))
        {
            $content = view($this->templatesDir().'.controller',['gen' => $this]);
            file_put_contents($controllerFile, $content);
            $this->info( $this->controllerClassName()." generated successfully." );
        }
    }

    public function generateModel()
    {
        $modelFile = $this->modelsDir().($this->modelDir?"/".$this->modelDir:"").'/'.$this->modelClassName().".php";
        if($this->confirmOverwrite($modelFile))
        {
            $content = view( $this->templatesDir().'.model', [
                'gen' => $this,
                'fields' => Db::fields($this->tableName)
            ]);
            file_put_contents($modelFile, $content);
            $this->info( "Model class ".$this->modelClassName()." generated successfully." );
        }
    }

    public function generateViews()
    {
        if( !file_exists($this->viewsDir()) ) mkdir($this->viewsDir());
        foreach ( config('crud.views') as $view ){

            $viewFile = $this->viewsDir()."/".$view.".blade.php";

            if($this->confirmOverwrite($viewFile))
            {
                $content = view( $this->templatesDir().'.views.'.$view, [
                    'gen' => $this,
                    'fields' => Db::fields($this->tableName)
                ]);

                file_put_contents($viewFile, $content);
                $this->info( "View file ".$view." generated successfully." );
            }
        }
    }

    protected function confirmOverwrite($file)
    {
        // if file does not already exist, return
        if( !file_exists($file) ) return true;

        // file exists, get confirmation
        if ($this->confirm($file.' already exists! Do you wish to overwrite this file? [y|N]')) {
            $this->info("overwriting...");
            return true;
        }
        else{
            $this->info("Using existing file ...");
            return false;
        }
    }

    public function route($bToLowerCase = false,$separator = "/")
    {
    	$route = "";
    	if( $this->baseRoute ){
            $route = $this->baseRoute . $separator;
    	}
        if( $bToLowerCase ){
            return strtolower($route.str_slug(str_replace("_"," ", str_singular($this->tableName))));
        }
        return $route.str_slug(str_replace("_"," ", str_singular($this->tableName)));
    }

    public function controllerClassName()
    {
        return studly_case(str_singular($this->tableName))."Controller";
    }

    public function viewsDir()
    {
        $viewDirPath = 'resources/views/'.$this->viewsDirName();
        if( $this->baseRoute ){
            $viewDirPath = 'resources/views/' . strtolower($this->baseRoute). "/" .$this->viewsDirName();
        }
        return base_path($viewDirPath);
    }

    public function viewsDirName()
    {
        return str_singular($this->tableName);
    }

    public function controllersDir()
    {
        return app_path('Http/Controllers');
    }

    public function modelsDir()
    {
        return app_path();
    }

    public function modelClassName()
    {
        return studly_case(str_singular($this->tableName));
    }

    public function modelTranslationClassName(){
        return studly_case(str_singular($this->tableName) . ucfirst(str_singular($this->translatableSuffix)) );
    }

    public function modelVariableName()
    {
        return camel_case(str_singular($this->tableName));
    }

    public function titleSingular()
    {
        return ucwords(str_singular(str_replace("_", " ", $this->tableName)));
    }

    public function titlePlural()
    {
        return ucwords(str_replace("_", " ", $this->tableName));
    }

    public function templatesDir()
    {
        return config('crud.templates');
    }

    public function getModelDir(){
        return $this->modelDir ? '\\' . $this->modelDir : "";
    }
    public function getBaseRoute($bSlashBefore = false){
        if( $bSlashBefore ){
            return $this->baseRoute ? $this->baseRoute . "\\" :"";
        }
        return $this->baseRoute ? "\\".$this->baseRoute:"";
    }

    public function generateRouteAction($action=NULL){
        if( $this->baseRoute ){
            return strtolower($this->baseRoute . "." . $this->modelClassName() . ($action ? "." . $action : "" ));
        }
        return strtolower($this->modelClassName() . ($action ? "." . $action : "" ));
    }

    /**
     * Generate translation table
    **/
    public function generateTranslateTable(){

        if( $this->option('translate-suffix') && $this->option('translate-suffix') != "" ){
            $this->translatableSuffix = $this->option('translate-suffix') ;
            $this->generateTranslateTable();
        }
        if( $this->option('translatable-fields') && $this->option('translatable-fields') != "" ){
            $translatableFields = $this->option('translatable-fields') ;
            $translatableFields = str_replace('\'','"',$translatableFields);
            $translatableFields = str_replace(' ','',$translatableFields);

            if( !strpos($translatableFields,'"') ){
                $translatableFields = str_replace('[','',$translatableFields);
                $translatableFields = str_replace(']','',$translatableFields);
                $this->translatableFields = explode(",",$translatableFields);
            }else{
                $this->translatableFields = json_decode($translatableFields);
            }
        }

        if( !empty($this->translatableFields) ){
            $name = $this->tableName;
            $translationTableName = strtolower(str_singular($name)) . str_plural($this->translatableSuffix);
            $migrationFields = array();

            $migrationFields[] = strtolower(str_singular($this->tableName)) . "_id#int" ;
            $migrationFields[] = "locale#string" ;

            foreach( Db::fields($name) as $fieldName=>$fieldDescription ){
                if( in_array($fieldName,$this->translatableFields) ){
                    $migrationFields[] = $fieldName . "#" .  $fieldDescription->type ;
                }
            }
            $bSuccessFullyMigrationCreated = false ;
            if( Schema::hasTable($translationTableName) ){
                if($this->confirm( "<" .  $translationTableName . "> table already exists! Do you wish to overwrite this table? [y|N] " )){
                    $this->call('make:migration', [
                        'name' => $translationTableName . implode("_",$migrationFields),
                        '--table' => $translationTableName,
                        //'--schema' => implode(",",$migrationFields)
                    ]);
                    //$bSuccessFullyMigrationCreated = true ;
                }
            }else{
                $this->call('crud:migration', [
                    'name' => $translationTableName,
                    '--schema' => implode(",",$migrationFields)
                ]);
                $bSuccessFullyMigrationCreated = true ;
            }

            if( $bSuccessFullyMigrationCreated ){
                $lastMigrateFile = Migration::where('batch','!=',' ')->orderBy('batch','desc')->first();
                $this->call('migrate');
                /*
				$this->call('migrate',[
					'--path' => $lastMigrateFile->migration
				]);*/
            }
        }
        return true ;
    }
}
