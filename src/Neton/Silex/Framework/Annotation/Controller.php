<?php

namespace Neton\Silex\Framework\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Controller
{
    /**
     * @var array
     */
    public $filters = array();
}