<?php
/**
 * ReiRouter
 * Version 1.0.0
 *
 * Copyright (c) 2017 Reinir Puradinata
 * All rights reserved
 */
class ReiRouter{
    static $base;
    static $path;
    public $routes;
    
    public function __construct(array $routes=[[],[]]){
        $this->routes=$routes;
    }
    public function add($path,$handler=null,$methods=null,array $params=[]){
        if(preg_match_all('-/([:*]?)([^/]*)-',$path,$x,PREG_SET_ORDER)){
            $routes =&$this->routes[0];
            $labels = [];
            foreach($x as $x){
                switch($x[1]){
                    case':':
                        $labels[] = $x[2];
                        $routes =&$routes['/1'];
                        break;
                    case'*':
                        $labels[] = $x[2];
                        $routes['/2'] = [$handler,$methods,$params,$labels];
                        return $this;
                    default:
                        $routes =&$routes[$x[2]];
                }
            }
            $routes['/'] = [$handler,$methods,$params,$labels];
        }else{
            $this->routes[1][$path] = [$handler,$methods,$params];
        }
        return $this;
    }
    public function find($path){
        $v=[];
        if($r=$this->findRoute(1,explode('/',$path),$this->routes[0],$v)){
            return[$r[0],$r[1],array_combine($r[3],$v)+$r[2]];
        }
    }
    private function findRoute($i,$p,$routes,&$values){
        if(isset($p[$i])){
            if(isset($routes[$p[$i]])&&($result=$this->findRoute($i+1,$p,$routes[$p[$i]],$values))) return $result;
            if(isset($routes['/1'])){ $values[]=$p[$i]; if($result=$this->findRoute($i+1,$p,$routes['/1'],$values)) return $result; array_pop($values); }
            if(isset($routes['/2'])){ $values[]=array_slice($p,$i); return $routes['/2']; }
        }elseif(isset($routes['/'])) return $routes['/'];
    }
    public function execute(){
        if($_SERVER['SERVER_PROTOCOL']=='HTTP/1.0'&&empty($_SERVER['HTTP_CONNECTION']))header('Connection: close');
        if($route=$this->find(self::$path)){
            if($handler=$route[0])
            if(class_exists($handler)){
                $method=$_SERVER['REQUEST_METHOD'];
                $methods=$route[1]?(array)$route[1]:get_class_methods($handler);
                $params=$route[2];
                try{
                    if(!in_array($method,$methods)) $this->respond(405,'Method not allowed',$h='Allow: '.join(',',$methods),[$h]);
                    elseif(method_exists($handler,$method)) (new $handler)->$method($params,self::$base,self::$path);
                    elseif(method_exists($handler,'__toString')) echo new $handler($method,$params,self::$base,self::$path);
                    else $this->respond(501,'Method not implemented','Request handler: '.$handler);
                    die();
                }catch(\Exception $e){
                    if(isset($e->description)) $this->respond($e->getCode(),$e->getMessage(),$e->description);
                    else $this->respond($e->getCode(),strstr($m=$e->getMessage()."\n","\n",true),strstr($m,"\n"));
                }
            }else $this->respond(500,'Request handler not found','Request handler: '.$handler);
        }else $this->respond(404,'Not found','Request path: '.self::$path);
    }
    private function respond($code,$message,$description,array $headers=[]){
        if(isset($this->routes[1][$code])){
            $route=$this->routes[1][$code];
            if($handler=$route[0])
            if(class_exists($handler)){
                $params=['code'=>$code,'message'=>$message,'description'=>$description,'headers'=>$headers]+$route[2];
                $method=$route[1];
                if(method_exists($handler,$method)) (new $handler)->$method($params,self::$base,self::$path);
                elseif(method_exists($handler,'__toString')) echo new $handler($method,$params,self::$base,self::$path);
                die();
            }
        }
        http_response_code($code=($code>=400)&&($code<600)?$code:500);
        foreach($headers as $header) header($header);
        echo "<!DOCTYPE html>\n";
        echo "<html>\n";
        echo "<head><meta name='viewport' content='width=device-width' charset='utf-8'><meta name='author' content='Reinir Puradinata'><title>{$code} {$message}</title></head>\n";
        echo "<body style='word-wrap:break-word'><h1>{$code} {$message}</h1><p>{$description}</p></body>\n";
        echo "</html>";
        die();
    }
}
if(preg_match('.([^\0]*)/[^\0]*\0\1(/[^?#]*).',"{$_SERVER['SCRIPT_NAME']}\0{$_SERVER['REQUEST_URI']}",$_)){
    ReiRouter::$base=$_[1];
    ReiRouter::$path=$_[2];
}