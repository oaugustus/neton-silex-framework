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

use Doctrine\Common\Annotations\Annotation;
use Neton\Silex\Framework\Annotation\After;
use Neton\Silex\Framework\Annotation\Direct;
use Neton\Silex\Framework\Annotation\Route;
use Neton\Silex\Framework\Annotation\Service;
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
    /**
     * Aplicação Silex.
     *
     * @var \Silex\Application
     */
    protected  $app;

    /**
     * Leitor de anotações do framework.
     *
     * @var \Doctrine\Common\Annotations\AnnotationReader
     */
    protected $reader;

    /**
     * Objeto de auto requisição de arquivos de código fonte.
     *
     * @var AutoRequire
     */
    protected $autoRequire;

    /**
     * Objeto responsável por carregar as configurações da aplicação.
     *
     * @var ConfigLoader
     */
    protected $configLoader;

    /**
     * Cria uma nova instância do framework.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->checkParameters();

        $this->reader = new AnnotationReader();
        $this->autoRequire = new AutoRequire($app['neton.framework.requires'], $app);
        $this->configLoader = new ConfigLoader($app);
        AnnotationRegistry::registerAutoloadNamespace("Neton\Silex\Framework\Annotation", __DIR__."/../../../");

    }

    /**
     * Aciona a inicialização do framework.
     */
    public function initialize()
    {
        $app = $this->app;

        $this->configLoader->loadConfigs();
        $this->autoRequire->requires();

        foreach ($app['neton.framework.bundles'] as $bundle => $namespace) {

            $bundleDir = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);
            $dir = $app['neton.framework.src_dir'].DIRECTORY_SEPARATOR.$bundleDir;

            $finder = new Finder();

            foreach ($finder->in($dir)->files() as $file) {

                $className = str_replace(".".$file->getExtension(), '', $file->getRelativePathname());
                $fullClass = $namespace."\\".str_replace("/","\\",$className);

                if (class_exists($fullClass)) {

                    $reflClass = new \ReflectionClass($fullClass);
                    $classAnnotations = $this->reader->getClassAnnotations($reflClass);

                    foreach ($classAnnotations as $annot) {
                        $this->compile($annot, $reflClass, $bundle);
                    }
                }

            }
        }
    }

    /**
     * Realiza o mapeamento de serviços do framework.
     *
     * @param Annotation $annotation
     * @param \ReflectionClass $refClass
     * @param String $bundle
     */
    private function compile($annotation, $refClass, $bundle)
    {
        if ($annotation instanceof Controller) {
            $this->defineControllerService($refClass, $bundle, $annotation->filters);
        }

        if ($annotation instanceof Service) {
            $this->defineService($refClass, $annotation->definition);
        }
    }

    /**
     * Cria a definição de um serviço no Silex.
     *
     * @param \ReflectionClass $refClass
     * @param $bundleNs
     * @param $definition
     */
    private function defineService(\ReflectionClass $refClass, $definition)
    {
        $app = $this->app;
        $serviceKey = strtolower(implode('.',explode('\\',$refClass->getName())));
        $class = $refClass->getName();

        switch ($definition) {
            case 'clousure':
                $app[$serviceKey] = function() use ($class, $app){
                    return new $class($app);
                };
            break;
            case 'shared':
                $app[$serviceKey] = $app->share(function() use ($class, $app){
                    return new $class($app);
                });
            break;
            case 'protected':
                $app[$serviceKey] = $app->protect(function() use ($class, $app){
                    return new $class($app);
                });
        }

        $this->defineParameters($refClass, $serviceKey);
    }

    /**
     * Define os parâmetros registrados via anotação no silex.
     *
     * @param \ReflectionClass $refClass
     * @param String $serviceKey
     */
    private function defineParameters($refClass, $serviceKey)
    {
        $properties = $refClass->getProperties();
        $app = $this->app;
        $class = $refClass->getName();

        foreach ($properties as $property) {

            $annotation = $this->reader->getPropertyAnnotation($property, 'Neton\Silex\Framework\Annotation\Parameter');

            if (!empty($annotation)) {
                $parameterKey = $serviceKey.".".$property->getName();

                $app[$parameterKey] = function() use ($class, $property){

                    $prop = $property->getName();

                    return $class::$$prop;
                };
            }
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

        $controllerPattern = "/".strtolower($bundle)."_/".strtolower($reflection->getShortName())."/";
        $controller = strtolower($reflection->getShortName());
        $serviceName = strtolower($bundle.".".$controller);

        $app[$serviceName] = $this->app->share(function() use ($app, $reflection) {
            $controllerClass = $reflection->getName();

            return new $controllerClass($app);
        });

        $$controller = $this->app['controllers_factory'];

        $this->mapRoutes($$controller, $serviceName, $reflection);
        $this->setControllerFilters($$controller, $serviceName,  $filters);


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
                $this->mapBasicRoute($route, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters);
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

        $ctr = $controller->post($method, $callback)->direct($direct->form);

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

    }

    /**
     * Mapeia uma rota convencional em um controlador.
     *
     * @param Route $route
     * @param \Silex\ControllerCollection $controller
     * @param String $controllerService
     * @param \ReflectionClass $reflectionClass
     * @param \ReflectionMethod $reflectionMethod
     * @param Array $beforeFilters
     * @param Array $afterFilters
     *
     * @throws FrameworkException
     * @throws FrameworkException
     */
    private function mapBasicRoute($route, $controller, $controllerService, $reflectionClass, $reflectionMethod, $beforeFilters, $afterFilters)
    {
        $app = $this->app;

        if ($route->method != null || !empty($route->methods)) {

            $routeMethods = $route->method == null ? $route->methods : array($route->method);

            if ($route->pattern){
                if ($route->template){
                    $ctr = $app->match($route->pattern, function(Request $request) use ($app, $controllerService, $reflectionMethod, $route){
                        $method = $reflectionMethod->getName();
                        $params = (array)$app[$controllerService]->$method($request);

                        return $app['twig']->render($route->template, $params);
                    })->method(implode('|',$routeMethods));
                } else {
                    $ctr = $app->match($route->pattern, $controllerService.":".$reflectionMethod->getName())->method(implode('|',$routeMethods));
                }

            } else {
                $ctr = $controller->match(strtolower($reflectionMethod->getName()), $controllerService.":".$reflectionMethod->getName())->method(implode('|',$routeMethods));
            }

            if ($route->name != null){
                $ctr->bind($route->name);
            }

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

    }

    /**
     * Seta os filtros do controlador.
     *
     * @param \Silex\Controller $controller
     * @param string $controllerService
     * @param array $filters
     * @param \ReflectionClass $reflectionClass
     */
    private function setControllerFilters($controller, $controllerService, $filters)
    {

        foreach ($filters as $filter) {

            if ($filter instanceof Before) {
                foreach ($filter->methods as $method) {

                    $this->addFilter($method, 'before', $controller, $controllerService);
                }
            }

            if ($filter instanceof After) {
                foreach ($filter->methods as $method) {
                    $this->addFilter($method, 'after', $controller, $controllerService);
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
     *
     * @throws FrameworkException
     * @throws FrameworkException
     */
    private function addFilter($method, $type, $controller, $controllerService)
    {
        $app = $this->app;


        if ($type == 'before') {
            $callback = function(Request $request) use ($controllerService, $app, $method){
                $service = $this->locateService($controllerService, $method);

                return $app[$service['service_class']]->$service['service_method']($request);
            };

        } else {
            $callback = function(Request $request, Response $response) use ($controllerService, $app, $method){
                $service = $this->locateService($controllerService, $method);

                return $app[$service['service_class']]->$service['service_method']($request, $response);
            };
        }

        $controller->$type($callback);

    }

    /**
     * Localiza o serviço e o método chamado para execução de callback.
     *
     * @param String $controllerService
     * @param String $method
     *
     * @return Array
     */
    private function locateService($controllerService, $method)
    {
        $app = $this->app;

        if (strpos($method, ':') !== false){
            $methodParts = explode(':', $method);

            $controllerService = $methodParts[0];
            $method = $methodParts[1];
        }

        $refClass = new \ReflectionClass(get_class($app[$controllerService]));

        if (!$refClass->hasMethod($method)){
            throw FrameworkException::controllerMethodNotDefinedError(sprintf("O metodo '%s' nao esta definido no controlador '%s'",$method, $refClass->getName()));
        }

        $refMethod = new \ReflectionMethod($refClass->getName(),$method);
        $annotations = $this->reader->getMethodAnnotations($refMethod);

        if (!empty($annotations)){
            throw FrameworkException::filterHasAnnotationError(sprintf("O metodo '%s' do controlador '%s' esta definido como um filtro mas possui anotacoes.",$method, $refClass->getName()));
        }

        return array(
            'service_class' => $controllerService,
            'service_method' => $method
        );
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

        if (!$this->app['neton.framework.config_dir']){
            throw FrameworkException::configDirNotFoundError("O diretorio dos arquivos de configuracao nao foi definido!");
        }

        if (empty($this->app['neton.framework.bundles'])){
            throw FrameworkException::bundlesNotDefinedError("Nenhum bundle foi definido!");
        }
    }
}