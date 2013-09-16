<?php

namespace Neton\Test\Controller;

use Neton\Silex\Framework\Annotation\Controller;
use Neton\Silex\Framework\Annotation\Direct;
use Neton\Silex\Framework\Annotation\Before;
use Neton\Silex\Framework\Annotation\After;
use Neton\Silex\Framework\Annotation\Route;
use Neton\Silex\Framework\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Foo
 *
 * @package Neton\Test
 *
 * @Controller({
 *      @Before({"before","teste"}),
 *      @After({"after1"})
 * })
 */
class Foo extends BaseController
{
    /**
     * @Route(pattern="/teste", method="get")
     * @Before({"beforeRoute"})
     */
    public function route(Request $request)
    {
        $response = new Response("Olá ".$request->get('world'));
        return $response;
    }

    public function beforeRoute(Request $request)
    {
        $request->request->set('world','World');
    }

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
        $request->request->set('name', 'Otavio');
    }

    public function teste(Request $request)
    {
        $request->request->set('name', $request->get('name')." Fernandes");
    }

    public function after1(Request $request, Response $response)
    {
        return new Response($response->getContent()." passou por aqui");
    }
}