<?php

namespace Neton\Silex\Framework;


use Igorw\Silex\ConfigServiceProvider;
use Silex\Application;

class ConfigLoader
{
    /**
     * Aplicação Silex.
     *
     * @var Application
     */
    protected $app = null;

    /**
     * Inicializa a classe de configuração do framework.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Carrega os arquivos de configuração da aplicação.
     */
    public function loadConfigs()
    {
        $app = $this->app;

        foreach ($app['neton.framework.configs'] as $file => $config){

            $cfgFile = $app['neton.framework.config_dir']."/".$file;

            if (is_string($config)){
                $config = $app[$config];
            }

            $app->register(new ConfigServiceProvider($cfgFile, $config));
        }

    }
}