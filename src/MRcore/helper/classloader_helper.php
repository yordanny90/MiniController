<?php

namespace MiniRouter;
/**
 * Establece una carpeta desde la que se intenta cargar un class, trait o interface que aún no existe
 * @param string $rootdir
 * @param string $prefix
 * @param string $suffix
 * @param string $namespace
 * @return bool
 */
function classloader(string $rootdir, string $prefix='', string $suffix='.php', string $namespace='', bool $prepend=false){
	$rootdir=realpath($rootdir)?:$rootdir;
	if($rootdir && is_dir($rootdir)){
		$preg_namespace=false;
		if(!empty($namespace)){
			$preg_namespace='/^\\\\?'.preg_quote($namespace.'\\').'.+/';
		}
		$success=spl_autoload_register(function($class_name) use ($rootdir, $prefix, $suffix, $preg_namespace){
			if($preg_namespace && !preg_match($preg_namespace, $class_name)) return;
            if($file=class_search_file($class_name, $rootdir, $prefix, $suffix)){
                include $file;
            }
		}, true, $prepend);
		return $success;
	}
	else{
		return false;
	}
}

/**
 * Busca el archivo correspondiente a la clase en una carpeta
 * @see classloader()
 * @param string $class_name
 * @param string $rootdir
 * @param string $prefix
 * @param string $suffix
 * @return string|null
 */
function class_search_file(string $class_name, string $rootdir, string $prefix, string $suffix): ?string{
    $namespace=array_filter(explode('\\', $class_name), 'strlen');
    $class=array_pop($namespace);
    $cNS=count($namespace);
    if($cNS==0){
        $paths=[
            $rootdir.'/'.$prefix.$class.$suffix,
        ];
    }
    elseif($cNS==1){
        $namespace=implode('/', $namespace);
        $paths=[
            $rootdir.'/'.$namespace.'/'.$prefix.$class.$suffix,
        ];
    }
    elseif($cNS==2){
        $namespace=implode('/', $namespace);
        $paths=[
            $rootdir.'/'.$namespace.'/'.$prefix.$class.$suffix,
            $rootdir.'/'.str_replace('/','-',$namespace).'/'.$prefix.$class.$suffix,
            $rootdir.'/'.str_replace('/','.',$namespace).'/'.$prefix.$class.$suffix,
        ];
    }
    else{
        $mainNS=array_shift($namespace);
        $namespace=implode('/', $namespace);
        $paths=[
            $rootdir.'/'.$mainNS.'/'.$namespace.'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'/'.str_replace('/','-',$namespace).'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'/'.str_replace('/','.',$namespace).'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'-'.$namespace.'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'.'.$namespace.'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'-'.str_replace('/','-',$namespace).'/'.$prefix.$class.$suffix,
            $rootdir.'/'.$mainNS.'.'.str_replace('/','.',$namespace).'/'.$prefix.$class.$suffix,
        ];
    }
    foreach($paths as $path){
        if(is_file($path)) return $path;
    }
    return null;
}