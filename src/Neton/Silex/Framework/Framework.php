<?php
/*
 * This file is part of the Neton Silex framework.
 *
 * (c) Otávio Fernandes <otavio@netonsolucoes.com.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Neton\Silex\Framework;
use Silex\Application;


/**
 * Neton Framework.
 *
 * @author Otávio Fernandes <otavio@netonsolucoes.com.br>
 */
class Framework
{
    protected  $app;

    /**
     * Cria uma nova instância do framework.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Aciona a inicialização do framework.
     */
    public function initialize()
    {

    }
}