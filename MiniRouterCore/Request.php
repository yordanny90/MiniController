<?php

namespace MiniRouter;
/**
 * Provee las funciones básicas para la lectura de datos del Request
 * Class Request
 * @package MiniRouter
 */
class Request{
	const CONTENTYPE_NONE='';
	const CONTENTYPE_PLAIN='text/plain';
	const CONTENTYPE_HTML='text/html';
	const CONTENTYPE_JSON='application/json';
	const CONTENTYPE_JSONP='application/javascript';
	const CONTENTYPE_XML='application/xml';
	const CONTENTYPE_FORM_URLENCODED='application/x-www-form-urlencoded';
	const CONTENTYPE_FORM_DATA='multipart/form-data';

	private function __construct(){ }

	/**
	 * Determina si la ejecución actual es una ejecución por linea de comandos (CLI)
	 * @return bool
	 */
	public static function isCLI(){
		return (isset($_SERVER['argv']) && !isset($_SERVER['REQUEST_METHOD']));
	}

	/**
	 * Determina si el request fué realizado por Ajax (XMLHttpRequest)
	 * @return bool
	 */
	public static function isAjax(){
		return (self::getHeader('X-Requested-With')=='XMLHttpRequest');
	}

	public static function &getAcceptList(){
		$accepts=[];
		foreach(explode(',', self::getHeader('Accept')) AS $v){
			$v=explode(';', $v, 2);
			if(!isset($v[1])){
				$v[1]='';
			}
			else{
				$v[1]=str_replace(';', ";\n", $v[1]);
			}
			$accepts[$v[0]]=parse_ini_string($v[1]);
		}
		return $accepts;
	}

	public static function getMethod(){
		return (isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'');
	}

	public static function getScheme(){
		return (isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'');
	}

	/**
	 * <code><base href="<?php echo Request::get_base_href(); ?>" target="_blank"></code>
	 * @return string
	 */
	public static function getBaseHref($withHost=false){
		$base=($withHost?self::getScheme().'://'.self::getHeader('host'):'').$_SERVER['SCRIPT_NAME'];
		return $base;
	}

	public static function getPath(){
		return isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'';
	}

	static function getContentType(){
		$ct=(isset($_SERVER['CONTENT_TYPE'])?$_SERVER['CONTENT_TYPE']:'');
		return trim(explode(';', $ct, 2)[0]);
	}

	static function getInput(){
		return file_get_contents('php://input');
	}

	static function getInputResource(){
		return fopen('php://input', 'r');
	}

	static function getInput_JSON($assoc=false){
		return json_decode(self::getInput(), $assoc);
	}

	static function getInput_XML(){
		return simplexml_load_string(self::getInput());
	}

	static function getInput_UrlEncoded(){
		$result=null;
		parse_str(self::getInput(), $result);
		return $result;
	}

	/**
	 * @return array
	 */
	static function getAllHeaders(){
		$headers=[];
		foreach($_SERVER AS $k=>$v){
			if(substr($k, 0, 5)=='HTTP_'){
				$k=mb_convert_case(str_replace('_', '-', substr($k, 5)), MB_CASE_TITLE);
				$headers[$k]=$v;
			}
		}
		return $headers;
	}

	/**
	 * @param string $name
	 * @return mixed|null
	 */
	static function getHeader($name){
		$index='HTTP_'.strtoupper(str_replace('-', '_', $name));
		if(isset($_SERVER[$index])){
			return $_SERVER[$index];
		}
		return null;
	}

}