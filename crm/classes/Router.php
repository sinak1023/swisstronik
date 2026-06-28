<?php

class Router
{
    public $uri = '';
    public $dir = '';
    public $query = [];
    public $baseurl = '';
    public $method = '';


    public function __construct()
    {
        $this->Set_baseurl();
        $this->Set_dir();
        $this->Set_method();
        $this->Set_query();
    }

    public function Set_baseurl()
    {
        $this->baseurl = implode('/', array_slice(explode('/', (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : "")), 0, -1)) . '/';

    }

    public function Set_dir()
    {

        //$dirs = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->baseurl));
        $dirs = substr(rawurldecode((isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "")), strlen($this->baseurl));
        if (empty($dirs)) {
            $dirs = '/';
        }
        if (!empty($dirs) and strpos($dirs, "?") !== false) {
            $dirs = substr($dirs, 0, strpos($dirs, '?'));
        }

        $this->dir = $dirs;
    }

    public function Set_method()
    {
        //$this->method = $_SERVER['REQUEST_METHOD'];
        $this->method = (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : "");
    }

    public function Set_query()
    {

        $arr = [];
        foreach ($_REQUEST as $key => $value) {

            $arr = array_merge($arr, [$key => $value]);
        }

        $this->query = $arr;
    }

    public function Check_dir($dir)
    {
        if (file_exists($dir)) {
            return 1;
        } else {
            return 0;
        }
    }

    public function explode_dir()
    {
        $ex = explode('/', rtrim($this->dir, '/'));
        if (empty($ex[0])) {
            $ex = ['/'];
        }

        return $ex;
    }

}