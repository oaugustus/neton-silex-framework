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
use Silex\Provider\TwigServiceProvider;
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

        $app->register(new ServiceControllerServiceProvider());
        $app->register(new DirectServiceProvider());
        $app->register(new TwigServiceProvider());
    }

    public function boot(Application $app)
    {
    }
}