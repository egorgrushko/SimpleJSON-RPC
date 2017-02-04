# SimpleJSONRPC

Implementation of JSON-RPC for PHP 5.4+.
Can be used to create API service for your project.
Specification: http://www.jsonrpc.org/specification

## Install

Via Composer

``` bash
$ composer require egorgrushko/simplejsonrpc
```

## Usage

Example implementation of http://www.jsonrpc.org/specification#examples

``` php
class Foo
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function __destruct()
    {
        
    }

    public function sum($param1, $param2, $param3)
    {
        return $param1 + $param2 + $param3;
    }

    public function subtract($minuend, $subtrahend)
    {
        return $minuend - $subtrahend;
    }

    public function notify_hello()
    {
        return 2 + 2;
    }

    public function get_data()
    {
        return $this->data;
    }
}
$request = isset($_REQUEST['request']) ? $_REQUEST['request'] : null; // Read JSON string

$fooObject = new Foo(array("hello", 0)); // Create the class object with the required methods and properties

$server = new SimpleJSONRPCServer($fooObject); // Create server object

$fooObject->data[1] = 5; // You can change properties before or between processing RPC

echo $server->process($request); // Execute the JSONRPC string processing. Returns result of processing
```

## License

The MIT License (MIT). Please see License File (LICENSE.md) for more information.