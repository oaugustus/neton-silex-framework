<?php

namespace Neton\Silex\Framework;


use Silex\Application;

class BaseController
{
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}