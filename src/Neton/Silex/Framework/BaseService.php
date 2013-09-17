<?php

namespace Neton\Silex\Framework;


use Silex\Application;

class BaseService
{
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
}