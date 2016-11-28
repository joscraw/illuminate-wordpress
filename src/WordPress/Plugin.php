<?php
namespace FatPanda\Illuminate\WordPress;

use Illuminate\Support\Str;
use FatPanda\Illuminate\WordPress\Http\Router;
use Illuminate\Container\Container;
use FatPanda\Illuminate\WordPress\Models\CustomPostType;
use FatPanda\Illuminate\WordPress\Models\CustomTaxonomy;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model as Eloquent;


/**
 * Baseclass for all WordPress plugins, extends a Laravel Container.
 */
abstract class Plugin extends Container {

	protected $mainFile;

	protected $basePath;

	protected $name;

	protected $pluginUri;

	protected $description;

	protected $version;

	protected $author;

	protected $authorUri;

	protected $pluginData;

	protected $registeredDataTypes = [];

	protected $routerNamespace;

	protected $routerVersion;

	protected $reflection;

 /**
  * The service binding methods that have been executed.
  *
  * @var array
  */
  protected $ranServiceBinders = [];

	/**
   * The available container bindings and their respective load methods.
   *
   * @var array
   */
  public $availableBindings = [
    'db' => 'registerDatabaseBindings',
    'events' => 'registerEventBindings',
    'config' => 'registerConfigBindings',
    'files' => 'registerFilesBindings',
    'view' => 'registerViewBindings',
    'Illuminate\Contracts\View\Factory' => 'registerViewBindings',
    'user' => 'registerUserBindings',
    'Illuminate\Database\Eloquent\Factory' => 'registerDatabaseBindings',
    
    // 'auth' => 'registerAuthBindings',
    // 'auth.driver' => 'registerAuthBindings',
    // 'Illuminate\Contracts\Auth\Guard' => 'registerAuthBindings',
    // 'Illuminate\Contracts\Auth\Access\Gate' => 'registerAuthBindings',
    // 'Illuminate\Contracts\Broadcasting\Broadcaster' => 'registerBroadcastingBindings',
    // 'Illuminate\Contracts\Broadcasting\Factory' => 'registerBroadcastingBindings',
    // 'Illuminate\Contracts\Bus\Dispatcher' => 'registerBusBindings',
    // 'cache' => 'registerCacheBindings',
    // 'cache.store' => 'registerCacheBindings',
    // 'Illuminate\Contracts\Cache\Factory' => 'registerCacheBindings',
    // 'Illuminate\Contracts\Cache\Repository' => 'registerCacheBindings',
    // 'composer' => 'registerComposerBindings',
    // 'encrypter' => 'registerEncrypterBindings',
    // 'Illuminate\Contracts\Encryption\Encrypter' => 'registerEncrypterBindings',
    // 'Illuminate\Contracts\Events\Dispatcher' => 'registerEventBindings',
    // 'hash' => 'registerHashBindings',
    // 'Illuminate\Contracts\Hashing\Hasher' => 'registerHashBindings',
    // 'log' => 'registerLogBindings',
    // 'Psr\Log\LoggerInterface' => 'registerLogBindings',
    // 'queue' => 'registerQueueBindings',
    // 'queue.connection' => 'registerQueueBindings',
    // 'Illuminate\Contracts\Queue\Factory' => 'registerQueueBindings',
    // 'Illuminate\Contracts\Queue\Queue' => 'registerQueueBindings',
    // 'request' => 'registerRequestBindings',
    // 'Psr\Http\Message\ServerRequestInterface' => 'registerPsrRequestBindings',
    // 'Psr\Http\Message\ResponseInterface' => 'registerPsrResponseBindings',
    // 'Illuminate\Http\Request' => 'registerRequestBindings',
    // 'translator' => 'registerTranslationBindings',
    // 'url' => 'registerUrlGeneratorBindings',
    // 'validator' => 'registerValidatorBindings',
    // 'Illuminate\Contracts\Validation\Factory' => 'registerValidatorBindings',
    
  ];

  /**
   * All of the loaded configuration files.
   *
   * @var array
   */
  protected $loadedConfigurations = [];

  /**
   * The loaded service providers.
   *
   * @var array
   */
  protected $loadedProviders = [];

  /**
   * Resolve the given type from the container.
   *
   * @param  string  $abstract
   * @param  array   $parameters
   * @return mixed
   */
  public function make($abstract, array $parameters = [])
  {
    $abstract = $this->getAlias($this->normalize($abstract));

    if (array_key_exists($abstract, $this->availableBindings) &&
      ! array_key_exists($this->availableBindings[$abstract], $this->ranServiceBinders)) {
      $this->{$method = $this->availableBindings[$abstract]}();

      $this->ranServiceBinders[$method] = true;
    }

    return parent::make($abstract, $parameters);
  }

  /**
   * Register container bindings for the application.
   *
   * @return void
   */
  protected function registerDatabaseBindings()
  {
    $this->singleton('db', function () {
	    return $this->loadComponent(
	        'database', [
	            'Illuminate\Database\DatabaseServiceProvider',
	            'Illuminate\Pagination\PaginationServiceProvider',
	        ], 'db'
	    );
	  });
	}

	/**
   * Register container bindings for the application.
   *
   * @return void
   */
  protected function registerFilesBindings()
  {
    $this->singleton('files', function () {
      return new Filesystem;
    });
  }

	/**
   * Register container bindings for the application.
   *
   * @return void
   */
  protected function registerViewBindings()
  {
    $this->singleton('view', function () {
      return $this->loadComponent('view', 'Illuminate\View\ViewServiceProvider');
    });
  }

  protected function registerUserBindings()
  {
    $this->singleton('user', function() {
      return function($id = null) {
        
      };
    });
  }

	/**
   * Register container bindings for the application.
   *
   * @return void
   */
  protected function registerEventBindings()
  {
    $this->singleton('events', function () {
      $this->register('Illuminate\Events\EventServiceProvider');

      return $this->make('events');
    });
  }

  /**
   * Register container bindings for the application.
   *
   * @return void
   */
  protected function registerConfigBindings()
  {
    $this->singleton('config', function () {
      return new ConfigRepository;
    });
  }

	function __construct($mainFile)
	{
		if (!file_exists($mainFile)) {
			throw new \Exception("Main file $mainFile does not exist!");
		}

		$this->mainFile = $mainFile;
		$this->basePath = dirname($mainFile);
		$this->bootstrapContainer();
	}

	/**
   * Bootstrap the plugin container.
   *
   * @return void
   */
  protected function bootstrapContainer()
  {
  	$this->instance('app', $this);
    $this->instance('path', $this->path());
    $this->registerContainerAliases();
    $this->bindActionsAndFilters();
  }

  /**
   * Load the Eloquent library for the application.
   *
   * @return void
   */
  public function withEloquent()
  {
    $this->make('db');
  }

  /**
   * Get the path to the plugin's root directory.
   *
   * @return string
   */
  public function path()
  {
      return $this->basePath;
  }

  /**
   * Get the base path for the application.
   * TODO: this is probably unnecessary in context, because
   * even if we're running a command line operation, our Plugin
   * is still providing the proper basepath for execution
   * @param  string|null  $path
   * @return string
   */
  public function basePath($path = null)
	{
    if (isset($this->basePath)) {
      return $this->basePath.($path ? '/'.$path : $path);
    }

    if ($this->runningInConsole()) {
      $this->basePath = getcwd();
    } else {
      $this->basePath = realpath(getcwd().'/../');
    }

    return $this->basePath($path);
  }

 /**
   * Get the storage path for the application.
   *
   * @param  string|null  $path
   * @return string
   */
  public function storagePath($path = null)
  {
    return $this->basePath().'/storage'.($path ? '/'.$path : $path);
  }

  /**
   * Get the path to the resources directory.
   *
   * @return string
   */
  public function resourcePath()
  {
    return $this->basePath().DIRECTORY_SEPARATOR.'resources';
  }

  /**
   * Determine if the application is running in the console.
   *
   * @return bool
   */
  public function runningInConsole()
  {
    return php_sapi_name() == 'cli';
  }

  /**
   * Register the core container aliases, just like 
   * a Lumen Application would.
   *
   * @return void
   */
  protected function registerContainerAliases()
  {
    $this->aliases = [
      'Illuminate\Contracts\Auth\Factory' => 'auth',
      'Illuminate\Contracts\Auth\Guard' => 'auth.driver',
      'Illuminate\Contracts\Cache\Factory' => 'cache',
      'Illuminate\Contracts\Cache\Repository' => 'cache.store',
      'Illuminate\Contracts\Config\Repository' => 'config',
      'Illuminate\Container\Container' => 'app',
      'Illuminate\Contracts\Container\Container' => 'app',
      'Illuminate\Database\ConnectionResolverInterface' => 'db',
      'Illuminate\Database\DatabaseManager' => 'db',
      'Illuminate\Contracts\Encryption\Encrypter' => 'encrypter',
      'Illuminate\Contracts\Events\Dispatcher' => 'events',
      'Illuminate\Contracts\Hashing\Hasher' => 'hash',
      'log' => 'Psr\Log\LoggerInterface',
      'Illuminate\Contracts\Queue\Factory' => 'queue',
      'Illuminate\Contracts\Queue\Queue' => 'queue.connection',
      'request' => 'Illuminate\Http\Request',
      'Laravel\Lumen\Routing\UrlGenerator' => 'url',
      'Illuminate\Contracts\Validation\Factory' => 'validator',
      'Illuminate\Contracts\View\Factory' => 'view',
      'FatPanda\Illuminate\WordPress\Http\Router' => 'router',
    ];
  }

	/**
	 * Using reflection, put together a list of all the action and filter hooks
	 * defined by this class, and then setup bindings for them.
	 * Action hooks begin with the prefix "on" and filter hooks begin with the
	 * prefix "filter". Additionally, look for the @priority doc comment, and
	 * use that to configure the priority loading order for the hook. Finally, count
	 * the number of parameters in the method signature, and use that to control
	 * the number of arguments that should be passed to the hook when it is invoked.
	 * 
	 * @return void
	 */
	protected function bindActionsAndFilters()
	{
		// setup activation and deactivation hooks
		$this->bindActivationAndDeactivationHooks();

		// reflect on the contents of this class
		$this->reflection = new \ReflectionClass($this);

		// get a list of all the methods on this class
		$methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

		// look for candidates for actions or filter hooks
		foreach($methods as $method) {
			// skip activation/deactivation hooks (handled above)
			if ($method->getName() === 'onActivate' || $method->getName() === 'onDeactivate') {
				continue;
			}

			// look in codedoc for @priority
			$priority = 10;
			$docComment = $method->getDocComment();
			if ($docComment !== false) {
				if (preg_match('/@priority\s+(\d+)/', $docComment, $matches)) {
					$priority = (int) $matches[1];
				}
			}

			// setup some internal event handlers
			add_action('plugins_loaded', [ $this, 'finalOnPluginsLoaded' ], 9);
			add_action('init', [ $this, 'finalOnInit' ], 9);
			
			// action methods begin with "on"
			if ('on' === strtolower(substr($method->getName(), 0, 2))) {
				$action = trim(strtolower(preg_replace('/(?<!\ )[A-Z]/', '_$0', substr($method->getName(), 2))), '_');
				$parameterCount = $method->getNumberOfParameters();
				add_action($action, [ $this, $method->getName() ], $priority, $parameterCount);
			
			// filter methods begin with "filter"
			} else if ('filter' === strtolower(substr($method->getName(), 0, 6))) {
				$filter = trim(strtolower(preg_replace('/(?<!\ )[A-Z]/', '_$0', substr($method->getName(), 6))), '_');
				$parameterCount = $method->getNumberOfParameters();
				add_action($filter, [ $this, $method->getName() ], $priority, $parameterCount);
			}
		}
	}

	/**
	 * Load plugin meta data, finish configuring various features, including
	 * the REST router and text translation.
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
	 * 
	 * @return void
	 */
	final function finalOnPluginsLoaded()
	{
		// if we don't have the get_plugin_data() function, load it
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH.'wp-admin/includes/plugin.php';
		}

		$this->pluginData = get_plugin_data($this->mainFile);
		
		foreach($this->pluginData as $key => $value) {
			$propertyName = Str::camel($key);
			$this->{$propertyName} = $value;
		}

		$this->bootRouter();

		$this->loadTextDomain();
	}

	/**
   * Configure and load the given component and provider.
   *
   * @param  string  $config
   * @param  array|string  $providers
   * @param  string|null  $return
   * @return mixed
   */
  public function loadComponent($config, $providers, $return = null)
  {
      $this->configure($config);

      foreach ((array) $providers as $provider) {
          $this->register($provider);
      }

      return $this->make($return ?: $config);
  }

  /**
	 * Load a configuration file into the application.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function configure($name)
	{
	    if (isset($this->loadedConfigurations[$name])) {
	        return;
	    }

	    $this->loadedConfigurations[$name] = true;

	    $path = $this->getConfigurationPath($name);

	    if ($path) {
	        $this->make('config')->set($name, require $path);
	    }
	}

	/**
   * Register a service provider with the application.
   *
   * @param  \Illuminate\Support\ServiceProvider|string  $provider
   * @param  array  $options
   * @param  bool   $force
   * @return \Illuminate\Support\ServiceProvider
   */
  public function register($provider, $options = [], $force = false)
  {
    if (!$provider instanceof ServiceProvider) {
      $provider = new $provider($this);
    }

    if (array_key_exists($providerName = get_class($provider), $this->loadedProviders)) {
      return;
    }

    $this->loadedProviders[$providerName] = true;

    if (method_exists($provider, 'register')) {
      $provider->register();
    }

    if (method_exists($provider, 'boot')) {
      return $this->call([$provider, 'boot']);
    }
	}

	/**
   * Get the path to the given configuration file.
   *
   * If no name is provided, then we'll return the path to the config folder.
   *
   * @param  string|null  $name
   * @return string
   */
  public function getConfigurationPath($name = null)
  {
    if (! $name) {
      $appConfigDir = $this->basePath('config').'/';

      if (file_exists($appConfigDir)) {
        return $appConfigDir;
      } elseif (file_exists($path = __DIR__.'/../config/')) {
        return $path;
      }
	  } else {
      $appConfigPath = $this->basePath('config').'/'.$name.'.php';

      if (file_exists($appConfigPath)) {
        return $appConfigPath;
      } elseif (file_exists($path = __DIR__.'/../config/'.$name.'.php')) {
        return $path;
      }
    }
  }

	/**
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/init
	 *
	 * @return void
	 */
	final function finalOnInit()
	{
		$this->registerCustomDataTypes();		
	}

	protected function bindActivationAndDeactivationHooks()
	{
		register_activation_hook($this->mainFile, [ $this, 'onActivate' ]);
		register_activation_hook($this->mainFile, [ $this, 'finalOnActivate' ]);
		register_deactivation_hook($this->mainFile, [ $this, 'onDeactivate' ]);
	}

	function finalOnActivate()
	{
		flush_rewrite_rules();
	}

	protected function registerCustomDataTypes()
	{
		if (!empty($this->registeredDataTypes)) {
      $this->withEloquent();

			foreach($this->registeredDataTypes as $class) {
				call_user_func($class . '::register');
			}
		}
	}

	protected function loadTextDomain()
	{
		load_plugin_textdomain( $this->textDomain, false, dirname( plugin_basename($this->mainFile) ) . rtrim($this->domainPath, '/') . '/' );
	}

	protected function bootRouter()
	{
		if (empty($this->routerNamespace)) {
			$this->setRouterNamespace( Str::slug($this->name) );
		}

		if (empty($this->routerVersion)) {
			// just use the major version number
			$routerVersion = 'v1';
			if (!preg_match('/(\d+).*?/', $this->version, $matches)) {
				$routerVersion = $matches[1];
			}

			$this->setRouterVersion($routerVersion);
		}

		$router = new Router($this);
		$router->setNamespace($this->routerNamespace);
		$router->setVersion($this->routerVersion);
		$this->instance('router', $router);

		$plugin = $this;
		require $this->path() . '/src/routes.php';
	}

	function getPluginData()
	{
		return $this->pluginData;
	}

	function setRouterNamespace($namespace)
	{
		$this->routerNamespace = $namespace;
		return $this;
	}

	function setRouterVersion($version)
	{
		$this->routerVersion = $version;
		return $this;
	}

	abstract function onActivate();

	abstract function onDeactivate();

	function registerCustomPostType($class)
	{
		$this->registeredDataTypes[(string) $class] = $class;
	}

	function registerCustomTaxonomy($class)
	{
		$this->registeredDataTypes[(string) $class] = $class;
	}

	function unregister($class)
	{
		unset($this->registeredDataTypes[(string) $class]);
	}

}