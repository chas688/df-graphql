<?php namespace DreamFactory\Core\GraphQL;

use DreamFactory\Core\GraphQL\Components\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    protected function getRouter()
    {
        return $this->app['router'];
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootPublishes();

        $this->bootRouter();
    }

    /**
     * Bootstrap router
     *
     * @return void
     */
    protected function bootRouter()
    {
        if ($this->app['config']->get('graphql.routes') && !$this->app->routesAreCached()) {
            $router = $this->getRouter();
            include __DIR__.'/../routes/routes.php';
        }
    }

    /**
     * Bootstrap events
     *
     * @param GraphQL $graphql
     * @return void
     */
    protected function registerEventListeners(GraphQL $graphql)
    {
        // Update the schema route pattern when schema is added
        $this->app['events']->listen(Events\SchemaAdded::class, function () use ($graphql) {
            $router = $this->getRouter();
            if (method_exists($router, 'pattern')) {
                $schemaNames = array_keys($graphql->getSchemas());
                $router->pattern('graphql_schema', '('.implode('|', $schemaNames).')');
            }
        });
    }

    /**
     * Bootstrap publishes
     *
     * @return void
     */
    protected function bootPublishes()
    {
        $configPath = __DIR__.'/../config';

        $this->mergeConfigFrom($configPath.'/config.php', 'graphql');

        $this->publishes([
            $configPath.'/config.php' => config_path('graphql.php'),
        ], 'config');
    }

    /**
     * Add types from config
     *
     * @param GraphQL $graphql
     * @return void
     */
    protected function addTypes(GraphQL $graphql)
    {
        $types = $this->app['config']->get('graphql.types', []);

        foreach ($types as $name => $type) {
            $graphql->addType($type, is_numeric($name) ? null : $name);
        }
    }

    /**
     * Add schemas from config
     *
     * @param GraphQL $graphql
     * @return void
     */
    protected function addSchemas(GraphQL $graphql)
    {
        $schemas = $this->app['config']->get('graphql.schemas', []);

        foreach ($schemas as $name => $schema) {
            $graphql->addSchema($name, $schema);
        }
    }

    /**
     * Configure security from config
     *
     * @return void
     */
    protected function applySecurityRules()
    {
        $maxQueryComplexity = config('graphql.security.query_max_complexity');
        if ($maxQueryComplexity !== null) {
            /** @var QueryComplexity $queryComplexity */
            $queryComplexity = DocumentValidator::getRule('QueryComplexity');
            $queryComplexity->setMaxQueryComplexity($maxQueryComplexity);
        }

        $maxQueryDepth = config('graphql.security.query_max_depth');
        if ($maxQueryDepth !== null) {
            /** @var QueryDepth $queryDepth */
            $queryDepth = DocumentValidator::getRule('QueryDepth');
            $queryDepth->setMaxQueryDepth($maxQueryDepth);
        }

        $disableIntrospection = config('graphql.security.disable_introspection');
        if ($disableIntrospection === true) {
            /** @var DisableIntrospection $disableIntrospection */
            $disableIntrospection = DocumentValidator::getRule('DisableIntrospection');
            $disableIntrospection->setEnabled(DisableIntrospection::ENABLED);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerGraphQL();
    }

    /**
     * Register GraphQL facade
     *
     * @return void
     */
    protected function registerGraphQL()
    {
        $this->app->singleton('graphql', function ($app) {

            $graphql = new GraphQL($app);

            $this->addTypes($graphql);

            $this->addSchemas($graphql);

            $this->registerEventListeners($graphql);

            $this->applySecurityRules();

            return $graphql;
        });
    }
}
