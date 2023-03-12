<?php

namespace MiniRouter;

class Router{
	const TYPE_CLI=0;
	const TYPE_HTTP=1;
	/**
	 * @var string Caracter separador para las rutas
	 */
	protected $splitter='.';
	/**
	 * @var string Dirección del enpoint utilizada si {@see Router::$path} esta vacío.
	 *
	 * Solo aplica para endpoints HTTP (@see Router::prepareForHTTP())
	 */
	private $default_path='index';
	/**
	 * @var string|null Indica la ruta recibida para la ejecución del endpoint. Si es null, el sistema la detectará auntomáticamente
	 */
	private $path;

	/**
	 * @var string El namespace al que deben pertenecer las clases de los endpoint
	 */
	private $mainNS;
	/**
	 * @var string Nombre de la clase RouteException o una que extienda de esta
	 */
	private $classException=RouteException::class;
	/**
	 * @var ReRouter|null Reencaminador de rutas
	 */
	private $reRouter;
	/**
	 * @var array|null Lista de métodos permitidos en el request
	 */
	private $allows;
	/**
	 * @var string Clase de la que deben extender los endpoint
	 */
	private $parentClass;
	/**
	 * @var int Cantidad maxima de subdirectorio al buscar el endpoint. Solo aplica cuando se usa "/" en {@see Router::setSplitter()}
	 */
	private $maxSubDir=2;
	/**
	 * @var string|null Método del request
	 */
	private $_method;
	/**
	 * @var \ReflectionClass|null
	 */
	private $_class;
	/**
	 * @var string|null Nombre parcial del método de la clase del endpoint
	 */
	private $_name;
	/**
	 * @var array|null Lista de parámetros después de la ruta de la clase
	 */
	private $_params;
	/**
	 * @var \ReflectionMethod|null
	 */
	private $_fn;

	protected static function fixPath($path){
		$path=trim($path, '/');

		return $path;
	}

	/**
	 * RouterP constructor.
	 * @param string $main_namespace
	 * @param string $parentClass Nombre de la clase o interface del que debe extender o implementar los endpoints
	 * @throws RouteException
	 */
	protected function __construct($type){
		$this->type=$type;
	}

	public static function &startHttp(string $main_namespace, string $parentClass=''){
		$new=new static(static::TYPE_HTTP);
		$new->mainNS=$main_namespace;
		$new->parentClass=$parentClass;
		return $new;
	}

	public static function &startCli(string $main_namespace, string $parentClass=''){
		$new=new static(static::TYPE_CLI);
		$new->mainNS=$main_namespace;
		$new->parentClass=$parentClass;
		return $new;
	}

	/**
	 * @return string
	 */
	public function getSplitter(): string{
		return $this->splitter;
	}

	/**
	 * Establece el caracter que separa las partes de una ruta en la URL
	 *
	 * Solo si utiliza el caracater "/", debe contemplar la posibilidad de aumentar o disminuir el valor en {@see Router::setMaxSubDir()}
	 * @param string $splitter Default: "." Posibles valores: ".- /"
	 * @return void
	 * @throws \Exception Error al intentar utilizar un string que no es de longitud 1, o no está en la lista de caracateres permitidos
	 */
	public function setSplitter(string $splitter): void{
		if(strlen($splitter)!==1 || strpos(".- /", $splitter)===false) throw new \Exception($splitter." invalid splitter");
		$this->splitter=$splitter;
	}

	/**
	 * @return string
	 */
	public function getDefaultPath(): string{
		return $this->default_path;
	}

	/**
	 * @param string $default_path
	 */
	public function setDefaultPath(string $default_path): void{
		$this->default_path=$default_path;
	}

	/**
	 * @return string|null
	 */
	public function getPath(): ?string{
		return $this->path;
	}

	/**
	 * @param string $path
	 */
	public function setPath(string $path): void{
		$this->path=$path;
	}

	/**
	 * Devuelve el método valido después de {@see Router::prepare()}
	 * @return string|null
	 */
	public function getMethod(): ?string{
		return $this->_method;
	}

	/**
	 * Devuelve el nombre de la funcion válida después de {@see Router::prepare()}
	 * @return string|null
	 */
	public function getName(): ?string{
		return $this->_name;
	}

	/**
	 * @return string
	 */
	public function getMainNamespace(): string{
		return $this->mainNS;
	}

	/**
	 * @return string
	 */
	public function getClassException(): string{
		return $this->classException;
	}

	/**
	 * @param string $classException
	 * @return void
	 * @throws \Exception
	 */
	public function setClassException($classException){
		if(!class_exists($classException)){
			throw new \Exception($classException." is not a class");
		}
		if(!is_subclass_of($classException, RouteException::class)){
			throw new \Exception($classException." class does not extend from ".RouteException::class);
		}
		$this->classException=$classException;
	}

	/**
	 * @return array|null
	 */
	public function getAllows(): ?array{
		return $this->allows;
	}

	/**
	 * @param array|null $allows
	 */
	public function setAllows(?array $allows): void{
		$this->allows=$allows;
	}

	/**
	 * @return int
	 */
	public function getMaxSubDir(): int{
		return $this->maxSubDir;
	}

	/**
	 * Solo aplica si se utiliza "/" en {@see Router::setSplitter()}
	 *
	 * Determina la cantidad máxima de subdirectorios (o namespace) que se utilizan durante la búsqueda de la ruta.
	 *
	 * Si este valor se establece en 0 (cero), nunca se tomarán en cuenta las subcarpetas (namespace)
	 *
	 * ## IMPORTANTE: Cuanto mayor se a este número, menor será la eficiencia del enrutador
	 * @param int $maxSubDir Default: 2. El valor minimo es 0 (cero)
	 */
	public function setMaxSubDir(int $maxSubDir): void{
		$this->maxSubDir=max(0, $maxSubDir);
	}

	/**
	 * @return ReRouter|null
	 */
	public function getReRouter(): ?ReRouter{
		return $this->reRouter;
	}

	/**
	 * @param ReRouter|null $reRouter
	 */
	public function setReRouter(?ReRouter $reRouter): void{
		$this->reRouter=$reRouter;
	}

	/**
	 * @param string|null $method
	 * @return void
	 * @throws RouteException
	 */
	public function prepare(?string $method=null, ?string $path=null): void{
		if(!is_null($this->_class)){
			throw new $this->classException('RouterP prepare twice', RouteException::CODE_EXECUTION);
		}
		if($this->type==static::TYPE_CLI){
			$this->prepareForCLI($method, $path);
		}
		elseif($this->type==static::TYPE_HTTP){
			$this->prepareForHTTP($method, $path);
		}
		else{
			throw new $this->classException('RouterP Type invalid', RouteException::CODE_EXECUTION);
		}
	}

	/**
	 * @throws RouteException
	 */
	private function prepareForHTTP(?string $method, ?string $path): void{
		if(Request::isCLI())
			throw new $this->classException('Execution by CLI is not allowed', RouteException::CODE_EXECUTION);
		if(Response::headers_sent())
			throw new $this->classException('Headers has been sent', RouteException::CODE_EXECUTION);
		$path=self::fixPath($path??Request::getPath());
		if($path==='') $path=self::fixPath($this->default_path);
		$this->loadEndPoint($method??Request::getMethod(), $path);
	}

	/**
	 * @throws RouteException
	 */
	private function prepareForCLI(?string $method, ?string $path): void{
		if(!Request::isCLI()){
			throw new $this->classException('Only execution by CLI is allowed', RouteException::CODE_EXECUTION);
		}
		$path=self::fixPath($path??ArgCLI::getText(0));
		$this->loadEndPoint($method??'CLI', $path);
	}

	private function loadEndPoint(string $method, string $path){
		if($this->reRouter && $this->reRouter->change($method, $path)){
			$path=self::fixPath($this->reRouter->getPath()??$path);
			$method=$this->reRouter->getMethod()??$method;
		}
		$parts=explode('/', $path);
		if(!is_null($this->_class)) return;
		if(!count($parts)){
			throw new $this->classException('Class', RouteException::CODE_NOTFOUND);
		}
		$preg_class="/^(\w+\\\)*\w+$/";
		if($this->getSplitter()!=='/'){
			$name='';
			$rclass=str_replace($this->getSplitter(), '\\', $parts[0]);
			if(!preg_match($preg_class, $rclass)){
				throw new $this->classException('Class', RouteException::CODE_NOTFOUND);
			}
			$class=$this->mainNS.'\\'.$rclass;
			try{
				$ref_class=new \ReflectionClass($class);
			}catch(\ReflectionException $e){
				$cparts=explode('\\', $rclass);
				$name=array_pop($cparts);
				if(count($cparts)==0){
					throw new $this->classException('Class', RouteException::CODE_NOTFOUND);
				}
				$class=$this->mainNS.'\\'.implode('\\', $cparts);
				try{
					$ref_class=new \ReflectionClass($class);
				}catch(\ReflectionException $e){
					throw new $this->classException('Class', RouteException::CODE_NOTFOUND, $e);
				}
			}
			if($this->parentClass && !$ref_class->isSubclassOf($this->parentClass)){
				throw new $this->classException('Class', RouteException::CODE_FORBIDDEN);
			}
			try{
				$ref_fn=$ref_class->getMethod($method.'_'.$name);
			}catch(\ReflectionException $e){
			}
			$this->_method=$method;
			$this->_class=$ref_class;
			$this->_name=$name;
			$this->_params=array_slice($parts, 1);
			$this->_fn=$ref_fn??null;
		}
		else{
			$len=0;
			do{
				if(++$len>($this->getMaxSubDir()+1)){
					throw new $this->classException('Class', RouteException::CODE_NOTFOUND);
				}
				$cparts=array_slice($parts, 0, $len);
				if(!preg_match($preg_class, implode('\\', $cparts))){
					throw new $this->classException('Class', RouteException::CODE_NOTFOUND);
				}
				$class=$this->mainNS.'\\'.implode('\\', $cparts);
			}while(!class_exists($class));
			try{
				$ref_class=new \ReflectionClass($class);
			}catch(\ReflectionException $e){
				throw new $this->classException('Class', RouteException::CODE_NOTFOUND, $e);
			}
			if($this->parentClass && !$ref_class->isSubclassOf($this->parentClass)) throw new $this->classException('Class', RouteException::CODE_FORBIDDEN);
			$name=$parts[$len]??'';
			$ref_fn=null;
			try{
				$ref_fn=$ref_class->getMethod($method.'_'.$name);
				if($name!=='') ++$len;
			}catch(\ReflectionException $e){
				if($name!==''){
					$name='';
					try{
						$ref_fn=$ref_class->getMethod($method.'_'.$name);
					}catch(\ReflectionException $e){
					}
				}
			}
			$this->_method=$method;
			$this->_class=$ref_class;
			$this->_name=$name;
			$this->_params=array_slice($parts, $len);
			$this->_fn=$ref_fn;
		}
	}

	/**
	 * @param bool $strict_parrams Default: true. Valida que los parámetros recibidos no excedan los esperados.
	 *
	 * Aunque se deshabilite seguira validando que cumpla con los parámetros mínimos
	 * @return Route
	 * @throws RouteException
	 */
	public function getRoute(bool $strict_parrams=true){
		if($this->getAllows() && !in_array($this->getMethod(), $this->getAllows())){
			throw new $this->classException($this->getMethod(), RouteException::CODE_METHODNOTALLOWED);
		}
		if(!$this->_class) throw new $this->classException('Class ', RouteException::CODE_NOTFOUND);
		if(!$this->_fn) throw new $this->classException('Funtion ', RouteException::CODE_NOTFOUND);
		$ref_fn=$this->_fn;
		if(!$ref_fn->isPublic()) throw new $this->classException('Function', RouteException::CODE_FORBIDDEN);
		if(!$this->_class->isInstantiable() && !$ref_fn->isStatic()) throw new $this->classException('Function', RouteException::CODE_FORBIDDEN);
		$path_class=static::class_to_path($this->mainNS, $this->_class->getName(), $this->getSplitter());
		$route=Route::create($path_class, $ref_fn, $this->getSplitter());
		if(!$route || $route->getMethod()!==$this->getMethod() || $route->getName()!==$this->getName()){
			throw new $this->classException('Route', RouteException::CODE_NOTFOUND);
		}
		$param_missing=$route->getReqParams()-count($this->_params);
		if($param_missing>0){
			throw new $this->classException('Params missing: '.$param_missing, RouteException::CODE_NOTFOUND);
		}
		elseif($strict_parrams && !$route->isParamsInfinite() && $route->getParams()<count($this->_params)){
			throw new $this->classException('Too many params', RouteException::CODE_NOTFOUND);
		}
		$route->exec_params=$this->_params;
		return $route;
	}

	private function allows(string $name){
	}

	/**
	 * @param string $name {@see Router::getName()}
	 * @return array
	 */
	public function getRouteAllow(string $name){
		if(!$this->_class) return null;
		$allows=[];
		$instClass=$this->_class->isInstantiable();
		foreach($this->_class->getMethods(\ReflectionMethod::IS_PUBLIC) AS $ref_fn){
			if(!$instClass && !$ref_fn->isStatic()) continue;
			if(($m_parts=Route::getMethodParts($ref_fn->getName())) && $m_parts['name']===$name){
				$allows[]=$m_parts['method'];
			}
		}
		if($this->getAllows()) $allows=array_values(array_intersect($allows, $this->getAllows()));
		return $allows;
	}

	public function getClassPath(){
		if(!$this->_class) return null;
		return static::class_to_path($this->mainNS, $this->_class->getName(), $this->getSplitter());
	}

	public function getClassFile(){
		if(!$this->_class) return null;
		return $this->_class->getFileName();
	}

	/**
	 * @return array|null
	 */
	public function getRouteList(){
		if(!$this->_class) return null;
		$routes=[];
		$instClass=$this->_class->isInstantiable();
		$path_class=static::class_to_path($this->mainNS, $this->_class->getName(), $this->getSplitter());
		foreach($this->_class->getMethods(\ReflectionMethod::IS_PUBLIC) AS $ref_fn){
			if(!$instClass && !$ref_fn->isStatic()) continue;
			if($route=Route::create($path_class, $ref_fn, $this->getSplitter())){
				$routes[$route->getMethod()][]=[
					$route->getMethod(),
					$route->getPath(),
					$route->getUrlParams()
				];
			}
		}
		if($this->getAllows()){
			$routes=array_merge(...array_values(array_intersect_key($routes,array_fill_keys($this->getAllows(),null))));
		}
		else{
			$routes=array_merge(...array_values($routes));
		}
		return $routes;
	}

	public static function class_to_path($main_namespace, $class, $path_splitter){
		if(substr($class,0,strlen($main_namespace))==$main_namespace){
			$path_class=substr($class, strlen($main_namespace));
		}
		else{
			$path_class=$class;
		}
		return ltrim(str_replace('\\', $path_splitter, $path_class), $path_splitter);
	}

}
