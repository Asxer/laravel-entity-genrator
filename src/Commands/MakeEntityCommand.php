<?php

/**
 * Created by PhpStorm.
 * User: roman
 * Date: 18.10.16
 * Time: 8:46
 */

namespace RonasIT\Support\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RonasIT\Support\Events\SuccessCreateMessage;
use RonasIT\Support\Exceptions\EntityCreateException;
use RonasIT\Support\Generators\ControllerGenerator;
use RonasIT\Support\Generators\FactoryGenerator;
use RonasIT\Support\Generators\MigrationsGenerator;
use RonasIT\Support\Generators\ModelGenerator;
use RonasIT\Support\Generators\RepositoryGenerator;
use RonasIT\Support\Generators\RequestsGenerator;
use RonasIT\Support\Generators\ServiceGenerator;
use RonasIT\Support\Generators\TestsGenerator;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
/**
 * @property ControllerGenerator $controllerGenerator
 * @property MigrationsGenerator $migrationsGenerator
 * @property ModelGenerator $modelGenerator
 * @property RepositoryGenerator $repositoryGenerator
 * @property RequestsGenerator $requestsGenerator
 * @property ServiceGenerator $serviceGenerator
 * @property FactoryGenerator $factoryGenerator
 * @property TestsGenerator $testGenerator
 * @property EventDispatcher $eventDispatcher
*/
class MakeEntityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:entity {name : The name of the entity. This name will use as name of models class. }
        {--without-model : Set this flag if you already have model for this entity. Command will find it. This flag is a lower priority than --only-model} 
        {--without-repository : Set if you don\'t want to use Data Access Level. Created Service will use special trait for controlling entity. This flag is a lower priority than --without-repository} 
        {--without-service : Set this flag if you don\'t want to create service} 
        {--without-controller : Set this flag if you don\'t want to create controller. Automatically requests will not create too.} 
        {--without-migrations : Set this flag if you already have table on db. This flag is a lower priority than --only-migrations}
        {--without-requests : Set this flag if you don\'t want to create requests to you controller}
        {--without-factory : Set this flag if you don\'t want to create factory}
        {--without-tests : Set this flag if you don\'t want to create tests. This flag is a lower priority than --only-tests}
        
        {--only-model : Set this flag if you want to create only model. This flag is a higher priority than --without-model, --only-migrations, --only-tests and --only-repository} 
        {--only-repository : Set this flag if you want to create only repository. This flag is a higher priority than --without-repository, --only-tests and --only-migrations}
        {--only-service : Set this flag if you want to create only service.}
        {--only-controller : Set this flag if you want to create only controller.}
        {--only-requests : Set this flag if you want to create only requests.}
        {--only-migrations : Set this flag if you want to create only repository. This flag is a higher priority than --without-migrations and --only-tests}
        {--only-factory : Set this flag if you want to create only factory. This flag is a higher priority than --without-factory}
        {--only-tests : Set this flag if you want to create only tests. This flag is a higher priority than --without-tests}
        
        {--i|integer=* : Add integer field to entity}
        {--I|integer-required=* : Add required integer field to entity. If you want to specify default value you have to do it manually.}
        {--f|float=* : Add float field to entity}
        {--F|float-required=* : Add required float field to entity. If you want to specify default value you have to do it manually.}
        {--s|string=* : Add string field to entity. Default type is VARCHAR(255) but you can change it manually in migration}
        {--S|string-required=* : Add required string field to entity. If you want to specify default value ir size you have to do it manually.}
        {--b|boolean=* : Add boolean field to entity.}
        {--B|boolean-required=* : Add boolean field to entity. If you want to specify default value you have to do it manually.}
        {--t|timestamp=* : Add boolean field to entity. }
        {--T|timestamp-required=* : Add boolean field to entity. If you want to specify default value you have to do it manually.}
        
        {--a|has-one=* : Set hasOne relations between you entity and existed entity. }
        {--A|has-many=* : Set hasMany relations between you entity and existed entity. }
        {--e|belongs-to=* : Set belongsTo relations between you entity and existed entity. }
        {--E|belongs-to-many=* : Set belongsToMany relations between you entity and existed entity. }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make entity with Model, Repository, Service, Migration and Controller.';

    protected $controllerGenerator;
    protected $migrationsGenerator;
    protected $modelGenerator;
    protected $repositoryGenerator;
    protected $requestsGenerator;
    protected $serviceGenerator;
    protected $factoryGenerator;
    protected $testGenerator;
    protected $eventDispatcher;

    protected $rules = [
        'only' => [
            'only-model' => [ModelGenerator::class],
            'only-repository' => [RepositoryGenerator::class],
            'only-service' => [ServiceGenerator::class],
            'only-controller' => [ControllerGenerator::class],
            'only-requests' => [RequestsGenerator::class],
            'only-migrations' => [MigrationsGenerator::class],
            'only-factory' => [FactoryGenerator::class],
            'only-tests' => [FactoryGenerator::class, TestsGenerator::class],
        ],
        'without' => [
            'without-model' => [ModelGenerator::class],
            'without-repository' => [RepositoryGenerator::class],
            'without-service' => [ServiceGenerator::class],
            'without-controller' => [ControllerGenerator::class, RequestsGenerator::class],
            'without-migrations' => [MigrationsGenerator::class],
            'without-requests' => [RequestsGenerator::class],
            'without-factory' => [FactoryGenerator::class],
            'without-tests' => [TestsGenerator::class]
        ]
    ];
    public $generators = [
        ModelGenerator::class, RepositoryGenerator::class, ServiceGenerator::class, RequestsGenerator::class,
        ControllerGenerator::class, MigrationsGenerator::class, FactoryGenerator::class, TestsGenerator::class
    ];

    public function __construct()
    {
        parent::__construct();

        $this->controllerGenerator = app(ControllerGenerator::class);
        $this->migrationsGenerator = app(MigrationsGenerator::class);
        $this->modelGenerator = app(ModelGenerator::class);
        $this->repositoryGenerator = app(RepositoryGenerator::class);
        $this->requestsGenerator = app(RequestsGenerator::class);
        $this->serviceGenerator = app(ServiceGenerator::class);
        $this->factoryGenerator = app(FactoryGenerator::class);
        $this->testGenerator = app(TestsGenerator::class);
        $this->eventDispatcher = app(EventDispatcher::class);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->eventDispatcher->listen(SuccessCreateMessage::class, $this->getSuccessMessageCallback());

        try {
            $this->generate();
        } catch (EntityCreateException $e) {
            $this->error($e->getMessage());
        }
    }

    protected function generate() {
        foreach ($this->rules['only'] as $option => $generators) {
            if ($this->option($option)) {
                foreach ($generators as $generator) {
                    $this->runGeneration($generator);
                }

                return;
            }
        }

        $methods = $this->getMethods();

        foreach ($methods as $option => $generator) {
            $this->runGeneration($generator);
        }
    }

    protected function getMethods() {
        $options = array_filter($this->options(), function ($option) {
            return $option === true;
        });

        $optionsNames = array_keys($options);
        $rules = array_only($this->rules['without'], $optionsNames);

        $exceptGenerators = array_collapse($rules);

        return array_subtraction($this->generators, $exceptGenerators);
    }

    protected function runGeneration($generator) {
        app($generator)
            ->setModel($this->argument('name'))
            ->setFields($this->getFields())
            ->setRelations($this->getRelations())
            ->generate();
    }

    protected function getRelations() {
        return [
            'hasOne' => $this->option('has-one'),
            'hasMany' => $this->option('has-many'),
            'belongsTo' => $this->option('belongs-to'),
            'belongsToMany' => $this->option('belongs-to-many')
        ];
    }

    protected function getSuccessMessageCallback() {
        return function (SuccessCreateMessage $event) {
            $this->info($event->message);
        };
    }

    protected function getFields() {
        return array_only($this->options(), [
            'integer', 'integer-required', 'string-required', 'string', 'float-required', 'float',
            'boolean-required', 'boolean', 'timestamp-required', 'timestamp'
        ]);
    }
}

