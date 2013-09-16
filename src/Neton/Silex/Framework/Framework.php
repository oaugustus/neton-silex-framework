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

use Direct\DirectServiceProvider;
use Neton\Silex\Framework\Annotation\After;
use Neton\Silex\Framework\Annotation\Direct;
use Neton\Silex\Framework\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Neton\Silex\Framework\Annotation\Before;
use Neton\Silex\Framework\Annotation\Controller;
use Silex\Application;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;

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
        $app['route_class'] = 'Direct\\Silex\\Route';
        $this->reader = new AnnotationReader();
    }

    /**
     * Aciona a inicialização do framework.
     */
    public function initialize()
    {
        $app = $this->app;

        $this->checkParameters();

        AnnotationRegistry::registerAutoloadNamespace("Neton\Silex\Framework\Annotation", __DIR__."/../../../");

        foreach ($app['neton.framework.bundles'] as $bundle => $namespace){
            $bundleDir = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
            $dir = $app['neton.framework.src_dir'].DIRECTORY_SEPARATOR.$bundleDir;

            $finder = new Finder();

            foreach ($finder->in($dir)->files() as $file){
                $className = str_replace(".".$file->getExtension(), '', $file->getRelativePathname());
                $fullClass = $namespace."\\".str_replace("/","\\",$className);

                if (class_exists($fullClass)){
                    $reflClass = new \ReflectionClass($fullClass);
                    $classAnnotations = $this->reader->getClassAnnotations($reflClass);

                    foreach ($classAnnotations as $annot){
                        $this->compile($annot, $reflClass, $bundle);
                    }
                }

            }
        }
    }

    private function compile($annotation, $refClass, $bundle)
    {
        if ($annotation instanceof Controller){
            $this->defineControllerService($refClass, $bundle, $annotation->filters);
        }
    }

    /**
     * Define as rotas do controlador.
     *
     * @param \ReflectionClass $reflection
     * @param string $bundle
     * @param array $filters
     */
    private function defineControllerService(\ReflectionClass $reflection, $bundle, $filters)
    {
        $app = $this->app;
        $controllerPattern = "/".strtolower($bundle)."_".$reflection->getShortName()."/";
        $controller = strtolower($reflection->getShortName());
        $serviceName = strtolower($bundle.".".$controller);

        $app[$serviceName] = $this->app->share(function() use ($app, $reflection){
            $controllerClass = $reflection->getName();

            return new $controllerClass($app);
        });

        $$controller = $this->app['controllers_factory'];

        $this->mapRoutes($$controller, $serviceName, $reflection);
        $this->setControllerFilters($$controller, $serviceName,  $filters, $reflection);



        /*echo "<pre>";
        print_r($$controller);
        echo "</pre>";*/
        //echo $controllerPattern;
        $this->app->mount($controllerPattern, $$controller);
    }

    /**
     * Cria as rotas definidas para o controlador.
     *
     * @param \Silex\ControllerCollection $controller
     * @param String $controllerService
     * @param \ReflectionClass $reflectionClass
     */
    private function mapRoutes($controller, $controllerService, $reflectionClass)
    {
        $methods = $reflectionClass->getMethods();

        foreach ($methods as $reflectionMethod) {

            $route = $this->reader->getMethodAnnotation($reflectionMethod, 'Neton\Silex\Framework\Annotation\Route');
            $direct = $this->reader->getMethodAnnotation($reflectionMethod, 'Neton\Silex\Framework\Annotation\Direct');

            $beforeFilters = $this->reader->getMethodAnnotation($reflectionMethod, 'Neton\Silex\Framework\Annotation\Before');
            $afterFilters = $this->reader->getMethodAnnotation($reflectionMethod, 'Neton\Silex\Framework\Annotation\After');

            if ($route) {
                $this->mapBasicRoute($route, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters);
            }

            if ($direct) {
                $this->mapDirectRoute($direct, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters);
            }
        }
    }

    /**
     * Mapeia uma rota de ExtDirect em um controlador.
     *
     * @param Direct $direct
     * @param \Silex\ControllerCollection $controller
     * @param String $controllerService
     * @param \ReflectionClass $reflectionClass
     * @param \ReflectionMethod $reflectionMethod
     * @param Array $beforeFilters
     * @param Array $afterFilters
     */
    private function mapDirectRoute($direct, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters)
    {
        $app = $this->app;

        $method = $reflectionMethod->getName();
        $callback = function(Request $request) use ($controllerService, $app, $method){
            return $app[$controllerService]->$method($request);
        };


        $controller->post($method, $callback)->direct($direct->form);
    }

    /**
     * Mapeia uma rota convencional em um controlador.
     *
     * @param Route $route
     * @param String $controllerService
     * @param \ReflectionClass $reflectionClass
     * @param \ReflectionMethod $reflectionMethod
     * @param Array $beforeFilters
     * @param Array $afterFilters
     *
     * @throws FrameworkException
     * @throws FrameworkException
     */
    private function mapBasicRoute($route, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters)
    {
        $app = $this->app;

        if ($route->pattern != null) {

            if ($route->method != null || !empty($route->methods)) {

                $routeMethods = $route->method == null ? $route->methods : array($route->method);
                $ctr = $app->match($route->pattern, $controllerService.":".$reflectionMethod->getName())->method(implode('|',$routeMethods));

                if (!empty($beforeFilters)) {

                    foreach ($beforeFilters->methods as $method) {
                        $this->addFilter($method, 'before', $ctr, $controllerService, $reflectionClass);
                    }
                }

                if (!empty($afterFilters)) {

                    foreach ($afterFilters->methods as $method) {
                        $this->addFilter($method, 'after', $ctr, $controllerService, $reflectionClass);
                    }
                }

            } else {

                throw FrameworkException::routeMethodNotDefinedError(sprintf(
                    "O metodo '%s' do controlador '%s' foi anotado como rota mas nao possui um metodo (post,get,etc) definido",
                    $reflectionMethod->getName(), $reflectionClass->getName()
                ));
            }

        } else {

            throw FrameworkException::routePatternNotDefinedError(sprintf(
                "O metodo '%s' do controlador '%s' foi anotado como rota mas nao tem um pattern definido",
                $reflectionMethod->getName(), $reflectionClass->getName()
            ));
        }

    }

    /**
     * Seta os filtros do controlador.
     *
     * @param \Silex\Controller $controller
     * @param string $controllerService
     * @param array $filters
     * @param \ReflectionClass $reflectionClass
     */
    private function setControllerFilters($controller, $controllerService, $filters, $reflectionClass)
    {

        foreach ($filters as $filter){
            if ($filter instanceof Before){
                foreach ($filter->methods as $method){
                    $this->addFilter($method, 'before', $controller, $controllerService, $reflectionClass);
                }
            }

            if ($filter instanceof After){
                foreach ($filter->methods as $method){
                    $this->addFilter($method, 'after', $controller, $controllerService, $reflectionClass);
                }
            }
        }
    }

    /**
     * Adiciona um filtro a um controlador.
     *
     * @param String $method
     * @param String $type
     * @param \Silex\Controller $controller
     * @param String $controllerService
     * @param \ReflectionClass $reflectionClass
     *
     * @throws FrameworkException
     * @throws FrameworkException
     */
    private function addFilter($method, $type, $controller, $controllerService, $reflectionClass)
    {
        $app = $this->app;

        if ($reflectionClass->hasMethod($method)){
            $reflectionMethod = new \ReflectionMethod($reflectionClass->getName(),$method);
            $annotations = $this->reader->getMethodAnnotations($reflectionMethod);

            if (empty($annotations)){

                if ($type == 'before'){

                    $callback = function(Request $request) use ($controllerService, $app, $method){
                        return $app[$controllerService]->$method($request);
                    };
                } else {
                    $callback = function(Request $request, Response $response) use ($controllerService, $app, $method){
                        return $app[$controllerService]->$method($request, $response);
                    };
                }

                $controller->$type($callback);
            } else {
                throw FrameworkException::filterHasAnnotationError(sprintf("O metodo '%s' do controlador '%s' esta definido como um filtro '%s' mas possui anotacoes.",$method, $reflectionClass->getName(), $type));
            }
        } else {
            throw FrameworkException::controllerMethodNotDefinedError(sprintf("O metodo '%s' nao esta definido no controlador '%s'",$method, $reflectionClass->getName()));
        }
    }

    /**
     * Verifica se os parâmetros obrigatórios ao funcionamento do framework estão preenchidos.
     *
     * @todo Veriricar e remover as marcações <pre>
     */
    private function checkParameters()
    {
        if (!$this->app['neton.framework.src_dir']){
            throw FrameworkException::sourceDirNotFoundError("O diretorio dos arquivos fonte nao foi definido!");
        }

        if (empty($this->app['neton.framework.bundles'])){
            throw FrameworkException::bundlesNotDefinedError("Nenhum bundle foi definido!");
        }
    }
}