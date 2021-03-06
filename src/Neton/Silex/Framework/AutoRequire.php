<?php

namespace Neton\Silex\Framework;


use Symfony\Component\Finder\Finder;

class AutoRequire
{
    /**
     * Diretórios de auto-inclusão.
     *
     * @var array
     */
    protected $requireDirs = array();

    /**
     * Inicializa a classe de auto inclusão.
     *
     * @param array $directories
     * @param \Silex\Application $app
     */
    public function __construct($directories, $app)
    {
        $this->requireDirs = $directories;
        $this->app = $app;
    }

    /**
     * Faz a auto inclusão de scripts dentro dos diretórios informados.
     */
    public function requires()
    {
        $app = $this->app;

        if (!empty($this->requireDirs)){
            $finder = new Finder();
            $finder->in($this->requireDirs)->name('*.php');

            foreach ($finder as $file) {
                require_once $file->getRealpath();
            }
        }
    }
}