<?php

namespace Neton\Silex\Framework\Annotation;

/**
 * Class Direct
 *
 * @package Neton\Silex\Framework\Annotation
 *
 * @Annotation
 * @Target("METHOD")
 */
class Direct
{
    /**
     * @var bool
     */
    public $form = false;
}