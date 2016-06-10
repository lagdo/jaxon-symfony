<?php

namespace Jaxon\Symfony\JaxonBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle as BaseBundle;

class JaxonBundle extends BaseBundle
{
    protected $jaxon = null;
    protected $response = null;
    protected $validator = null;
    protected $view = null;

    protected $preCallback = null;
    protected $postCallback = null;
    protected $initCallback = null;

    // Requested controller and method
    private $controller = null;
    private $method = null;

    /**
     * Create a new Jaxon instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->jaxon = \Jaxon\Jaxon::getInstance();
        $this->response = new \Jaxon\Symfony\Response();
        $this->validator = \Jaxon\Utils\Container::getInstance()->getValidator();
        $this->view = new \Jaxon\Symfony\View();
    }

    public function getNamespace()
    {
        return __NAMESPACE__;
    }

    public function getPath()
    {
        return __DIR__;
    }

    /**
     * Initialise the Jaxon module.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        // initialize the module with the configuration loaded from config.php
        // \Symfony::configure($this, require(__DIR__ . '/config.php'));

        $isDebug = true; // \Symfony::app()->controller->config->item('debug');
        $appConfig = \Symfony::app()->controller->module->app;
        $libConfig = \Symfony::app()->controller->module->lib;
        $appPath = rtrim(\Symfony::getAlias('@app'), '/') . '/';
        $baseUrl = rtrim(\Symfony::getAlias('@web'), '/') . '/';
        $baseDir = rtrim(\Symfony::getAlias('@webroot'), '/') . '/';
        // Jaxon application settings
        $controllerDir = (array_key_exists('dir', $appConfig) ? $appConfig['dir'] : $appPath . 'jaxon');
        $namespace = (array_key_exists('namespace', $appConfig) ? $appConfig['namespace'] : '\\Jaxon\\App');

        $excluded = (array_key_exists('excluded', $appConfig) ? $appConfig['excluded'] : array());
        // The public methods of the Controller base class must not be exported to javascript
        $controllerClass = new \ReflectionClass('\\Jaxon\\Symfony\\Controller');
        foreach ($controllerClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
        {
            $excluded[] = $xMethod->getShortName();
        }
        // Use the Composer autoloader
        $this->jaxon->useComposerAutoloader();
        // Jaxon library default options
        $this->jaxon->setOptions(array(
            'js.app.export' => !$isDebug,
            'js.app.minify' => !$isDebug,
            'js.app.uri' => $baseUrl . 'jaxon/js',
            'js.app.dir' => $baseDir . 'jaxon/js',
        ));
        // Jaxon library settings
        \Jaxon\Config\Config::setOptions($libConfig);
        // Set the request URI
        if(!$this->jaxon->getOption('core.request.uri'))
        {
            $this->jaxon->setOption('core.request.uri', 'jaxon');
        }
        // Register the default Jaxon class directory
        $this->jaxon->addClassDir($controllerDir, $namespace, $excluded);
    }

    /**
     * Check if the current request is an Jaxon request.
     *
     * @return boolean  True if the request is Jaxon, false otherwise.
     */
    public function canProcessRequest()
    {
        return $this->jaxon->canProcessRequest();
    }

    /**
     * Get the Jaxon response.
     *
     * @return object  the Jaxon response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Register the Jaxon classes.
     *
     * @return void
     */
    public function register()
    {
        $this->jaxon->registerClasses();
    }

    /**
     * Register a specified Jaxon class.
     *
     * @return void
     */
    public function registerClass($sClassName)
    {
        $this->jaxon->registerClass($sClassName);
    }

    /**
     * Get the javascript code to be sent to the browser.
     *
     * @return string  the javascript code
     */
    public function script($bIncludeJs = false, $bIncludeCss = false)
    {
        return $this->jaxon->getScript($bIncludeJs, $bIncludeCss);
    }

    /**
     * Get the HTML tags to include Jaxon javascript files into the page.
     *
     * @return string  the javascript code
     */
    public function js()
    {
        return $this->jaxon->getJs();
    }

    /**
     * Get the HTML tags to include Jaxon CSS code and files into the page.
     *
     * @return string  the javascript code
     */
    public function css()
    {
        return $this->jaxon->getCss();
    }

    /**
     * Set the init callback, used to initialise controllers.
     *
     * @param  callable  $callable the callback function
     * @return void
     */
    public function setInitCallback($callable)
    {
        $this->initCallback = $callable;
    }

    /**
     * Set the pre-request processing callback.
     *
     * @param  callable  $callable the callback function
     * @return void
     */
    public function setPreCallback($callable)
    {
        $this->preCallback = $callable;
    }

    /**
     * Set the post-request processing callback.
     *
     * @param  callable  $callable the callback function
     * @return void
     */
    public function setPostCallback($callable)
    {
        $this->postCallback = $callable;
    }

    /**
     * Initialise a controller.
     *
     * @return void
     */
    protected function initController($controller)
    {
        // Si le controller a déjà été initialisé, ne rien faire
        if(!($controller) || ($controller->response))
        {
            return;
        }
        // Placer les données dans le controleur
        $controller->yiiJaxon = $this;
        $controller->response = $this->response;
        if(($this->initCallback))
        {
            $cb = $this->initCallback;
            $cb($controller);
        }
        $controller->init();
        // The default view is used only if there is none already set
        if(!$controller->view)
        {
            $controller->view = $this->view;
        }
    }

    /**
     * Get a controller instance.
     *
     * @param  string  $classname the controller class name
     * @return object  The registered instance of the controller
     */
    public function controller($classname)
    {
        $controller = $this->jaxon->registerClass($classname, true);
        if(!$controller)
        {
            return null;
        }
        $this->initController($controller);
        return $controller;
    }

    /**
     * This is the pre-request processing callback passed to the Jaxon library.
     *
     * @param  boolean  &$bEndRequest if set to true, the request processing is interrupted.
     * @return object  the Jaxon response
     */
    public function preProcess(&$bEndRequest)
    {
        // Validate the inputs
        $class = $_POST['jxncls'];
        $method = $_POST['jxnmthd'];
        if(!$this->validator->validateClass($class) || !$this->validator->validateMethod($method))
        {
            // End the request processing if the input data are not valid.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->response;
        }
        // Instanciate the controller. This will include the required file.
        $this->controller = $this->controller($class);
        $this->method = $method;
        if(!$this->controller)
        {
            // End the request processing if a controller cannot be found.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->response;
        }

        // Call the user defined callback
        if(($this->preCallback))
        {
            $cb = $this->preCallback;
            $cb($this->controller, $method, $bEndRequest);
        }
        return $this->response;
    }

    /**
     * This is the post-request processing callback passed to the Jaxon library.
     *
     * @return object  the Jaxon response
     */
    public function postProcess()
    {
        if(($this->postCallback))
        {
            $cb = $this->postCallback;
            $cb($this->controller, $this->method);
        }
        return $this->response;
    }

    /**
     * Process the current Jaxon request.
     *
     * @return void
     */
    public function processRequest()
    {
        // Process Jaxon Request
        $this->jaxon->register(\Jaxon\Jaxon::PROCESSING_EVENT, \Jaxon\Jaxon::PROCESSING_EVENT_BEFORE, array($this, 'preProcess'));
        $this->jaxon->register(\Jaxon\Jaxon::PROCESSING_EVENT, \Jaxon\Jaxon::PROCESSING_EVENT_AFTER, array($this, 'postProcess'));
        if($this->jaxon->canProcessRequest())
        {
            // Traiter la requete
            $this->jaxon->processRequest();
        }
    }
}
