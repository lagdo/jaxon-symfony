<?php

namespace Jaxon\AjaxBundle;

use Jaxon\Utils\View\Store;
use Jaxon\Contracts\View as ViewContract;

use Twig\Environment;

class View implements ViewContract
{
    /**
     * The Twig template renderer
     *
     * @var Environment
     */
    protected $xRenderer;

    /**
     * The view namespaces
     *
     * @var array
     */
    protected $aNamespaces = [];

    /**
     * The constructor
     *
     * @param Environment $xRenderer
     */
    public function __construct(Environment $xRenderer)
    {
        $this->xRenderer = $xRenderer;
    }

    /**
     * Add a namespace to this view renderer
     *
     * @param string        $sNamespace         The namespace name
     * @param string        $sDirectory         The namespace directory
     * @param string        $sExtension         The extension to append to template names
     *
     * @return void
     */
    public function addNamespace($sNamespace, $sDirectory, $sExtension = '')
    {
        $this->aNamespaces[$sNamespace] = [
            'directory' => $sDirectory,
            'extension' => $sExtension,
        ];
    }

    /**
     * Render a view
     *
     * @param Store         $store        A store populated with the view data
     *
     * @return string        The string representation of the view
     */
    public function render(Store $store)
    {
        $sExtension = '';
        if(array_key_exists($store->getNamespace(), $this->aNamespaces))
        {
            $sExtension = $this->aNamespaces[$store->getNamespace()]['extension'];
        }
        // Render the template
        return trim($this->xRenderer->render($store->getViewName() . $sExtension, $store->getViewData()), " \t\n");
    }
}
