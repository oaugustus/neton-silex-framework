<?php

namespace Neton\Silex\Framework\Annotation;

/**
 * Class Method
 *
 * @package Neton\Silex\Framework\Annotation
 *
 * @Annotation
 * @Target("METHOD")
 */
class Method
{
    /**
     * @var array<string>
     */
    public $methods;
}