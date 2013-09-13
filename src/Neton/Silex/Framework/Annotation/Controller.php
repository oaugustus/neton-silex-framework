<?php

namespace Neton\Silex\Framework\Annotation;

/**
 * @Annotation
 * @Target("Class")
 */
class Controller
{
    public $route;
    public $before;
}