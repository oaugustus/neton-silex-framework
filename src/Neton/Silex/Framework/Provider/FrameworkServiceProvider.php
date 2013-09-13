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

use Silex\Application;
use Silex\ServiceProviderInterface;
use Neton\Silex\Framework\Framework;

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
    }

    public function boot(Application $app)
    {
    }
}