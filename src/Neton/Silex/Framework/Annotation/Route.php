<?php

namespace Neton\Silex\Framework\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Route
{
    /**
     * @var string
     */
    public $pattern = null;

    /**
     * @var string
     */
    public $method = null;

    /**
     * @var array<string>
     */
    public $methods;
}