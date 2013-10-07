<?php
/*
 * This file is part of the Neton Silex framework.
 *
 * (c) Otávio Fernandes <otavio@netonsolucoes.com.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Neton\Silex\Framework\Provider;

use Direct\DirectServiceProvider;
use Silex\Application;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\ServiceProviderInterface;
use Neton\Silex\Framework\Framework;
use Silex\Provider\ServiceControllerServiceProvider;

/**
 * Neton Framework Service Provider
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class FrameworkServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['neton.framework'] = $app->share(function($app){
            return new Framework($app);
        });

        $app['neton.framework.bundles'] = array();
        $app['neton.framework.src_dir'] = null;
        $app['neton.framework.config_dir'] = null;
        $app['neton.framework.requires'] = array();

        $this->registerRequiredProviders($app);
    }

    /**
     * Registra os provedores de serviço necessários ao funcionamento do framework.
     *
     * @param Application $app
     */
    private function registerRequiredProviders(Application $app)
    {
        $app->register(new ServiceControllerServiceProvider());
        $app->register(new DirectServiceProvider());
        $app->register(new TwigServiceProvider());
        $app->register(new SessionServiceProvider());
        $app->register(new UrlGeneratorServiceProvider());
    }

    public function boot(Application $app)
    {
    }
}