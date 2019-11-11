<?php

namespace Jaxon\AjaxBundle;

use Symfony\Component\HttpFoundation\Response as HttpResponse;

class Jaxon
{
    use \Jaxon\Features\App;

    /**
     * The application debug option
     *
     * @var bool
     */
    protected $debug;

    /**
     * The template engine
     *
     * @var \Symfony\Component\Templating\EngineInterface
     */
    protected $template;

    /**
     * The bundle configuration
     *
     * @var array
     */
    public $configs;

    /**
     * Create a new Jaxon instance.
     *
     * @return void
     */
    public function __construct($template, $configs, $debug)
    {
        $this->template = $template;
        // The application debug option
        $this->configs = $configs;
        // The application debug option
        $this->debug = $debug;

        // The application URL
        $sJsUrl = '//' . $_SERVER['SERVER_NAME'] . '/jaxon/js';
        // The application web dir
        $sJsDir = $_SERVER['DOCUMENT_ROOT'] . '/jaxon/js';

        $di = jaxon()->di();
        $viewManager = $di->getViewmanager();
        // Set the default view namespace
        $viewManager->addNamespace('default', '', '.html.twig', 'twig');
        // Add the view renderer
        $viewManager->addRenderer('twig', function () {
            return new View($this->template);
        });

        // Set the session manager
        $di->setSessionManager(function () {
            return new Session();
        });

        $this->bootstrap()
            ->lib($this->configs['lib'])
            ->app($this->configs['app'])
            // ->uri($sUri)
            ->js(!$this->debug, $sJsUrl, $sJsDir, !$this->debug)
            ->run();

        // Prevent the Jaxon library from sending the response or exiting
        $jaxon->setOption('core.response.send', false);
        $jaxon->setOption('core.process.exit', false);
    }

    /**
     * Process an incoming Jaxon request, and return the response.
     *
     * @return mixed
     */
    public function processRequest()
    {
        $jaxon = jaxon();
        // Process the jaxon request
        $jaxon->processRequest();
        // Get the reponse to the request
        $jaxonResponse = $jaxon->di()->getResponseManager()->getResponse();

        // Create and return a Symfony HTTP response
        $code = '200';
        $response = new HttpResponse();
        $response->headers->set('Content-Type', $jaxonResponse->getContentType());
        $response->setCharset($jaxonResponse->getCharacterEncoding());
        $response->setStatusCode($code);
        $response->setContent($jaxonResponse->getOutput());
        // prints the HTTP headers followed by the content
        $response->send();
    }
}
