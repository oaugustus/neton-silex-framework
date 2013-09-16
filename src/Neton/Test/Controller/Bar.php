<?php

namespace Neton\Test\Controller;

use Neton\Silex\Framework\Annotation\Controller;
use Neton\Silex\Framework\Annotation\Direct;
use Neton\Silex\Framework\Annotation\Before;
use Neton\Silex\Framework\Annotation\After;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Foo
 *
 * @package Neton\Test
 *
 * @Controller
 */
class Bar
{
    /**
     * @Direct
     */
    public function listAll()
    {

    }

    /**
     * @Direct(form=true)
     */
    public function save()
    {

    }

    /**
     * @param Request $request
     */
    public function before(Request $request)
    {

    }
}