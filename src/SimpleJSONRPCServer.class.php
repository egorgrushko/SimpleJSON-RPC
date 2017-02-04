<?php

class SimpleJSONRPCServer
{
    private static $PARSE_ERROR      = [
        'code' => -32700,
        'message' => 'Parse error'
    ];
    private static $INVALID_REQUEST  = [
        'code' => -32600,
        'message' => 'Invalid Request'
    ];
    private static $METHOD_NOT_FOUND = [
        'code' => -32601,
        'message' => 'Method not found'
    ];
    private static $INVALID_PARAMS   = [
        'code' => -32602,
        'message' => 'Invalid params'
    ];
    private static $INTERNAL_ERROR   = [
        'code' => -32603,
        'message' => 'Internal error'
    ];

    const CONTENT_TYPE_HEADER = 'Content-Type: application/json';
    const JSON_RPC_VERSION    = '2.0';

    private $RPCObject;
    private $reflectionRPCObject;

    private function getErrorResponseObject($error, $id = null)
    {
        return array(
            'jsonrpc' => self::JSON_RPC_VERSION,
            'error' => $error,
            'id' => $id
        );
    }

    private function getSuccessResponseObject($result, $id)
    {
        return array(
            'jsonrpc' => self::JSON_RPC_VERSION,
            'result' => $result,
            'id' => $id
        );
    }

    private function isVersionValid($request)
    {
        return isset($request->jsonrpc) &&
            $request->jsonrpc === self::JSON_RPC_VERSION;
    }

    private function isMethodValid($request)
    {
        return isset($request->method);
    }

    private function areParamsValid($request)
    {
        if (!isset($request->params)) {
            return true;
        }

        return isset($request->params) &&
            (is_object($request->params) || is_array($request->params));
    }

    private function isIdValid($request)
    {
        if (!isset($request->id)) {
            return true;
        }

        return is_int($request->id) || is_string($request->id);
    }

    private function hasValidProps($request)
    {
        $requestArray = array_keys((array) $request);
        $validProps   = array('jsonrpc', 'method', 'params', 'id');
        return !count(array_diff($requestArray, $validProps));
    }

    private function checkRequestObject($request)
    {
        return $this->hasValidProps($request) &&
            $this->isVersionValid($request) &&
            $this->isMethodValid($request) &&
            $this->areParamsValid($request) &&
            $this->isIdValid($request);
    }

    private function checkParams($method, $requestParams)
    {
        $requestParamsArray = (array) $requestParams;
        $requestParamsCount = count($requestParamsArray);

        if (is_array($requestParams)) {
            if ($method->getNumberOfParameters() < $requestParamsCount ||
                $method->getNumberOfRequiredParameters() > $requestParamsCount) {
                return false;
            }

            return $requestParams;
        } else if (is_object($requestParams)) {
            $params       = array();
            $methodParams = $method->getParameters();

            foreach ($methodParams as $parameter) {
                $parameterName = $parameter->getName();

                if (key_exists($parameterName, $requestParamsArray)) {
                    $params[] = $requestParamsArray[$parameterName];
                } else if (!$parameter->isOptional()) {
                    return false;
                }
            }

            if (count(array_diff(array_values($requestParamsArray), $params)) == 0) {
                return $params;
            }
        }

        return false;
    }

    private function processRequestObject($request)
    {
        if (!$this->checkRequestObject($request)) {
            return $this->getErrorResponseObject(self::$INVALID_REQUEST);
        }

        $notification = !isset($request->id);

        if (!$this->reflectionRPCObject->hasMethod($request->method)) {
            return $notification ? null : $this->getErrorResponseObject(self::$METHOD_NOT_FOUND,
                    $request->id);
        }

        $method = $this->reflectionRPCObject->getMethod($request->method);

        if (!$method->isPublic() || $method->isConstructor() || $method->isDestructor()
            || $method->isAbstract()) {
            return $notification ? null : $this->getErrorResponseObject(self::$METHOD_NOT_FOUND,
                    $request->id);
        }

        if (!isset($request->params)) {
            $request->params = array();
        }

        $params = $this->checkParams($method, $request->params);

        if ($params === false) {
            return $notification ? null : $this->getErrorResponseObject(self::$INVALID_PARAMS,
                    $request->id);
        }

        try {
            $result = $method->invokeArgs($this->RPCObject, $params);
        } catch (ReflectionException $e) {
            return $notification ? null : $this->getErrorResponseObject(self::$INTERNAL_ERROR,
                    $request->id);
        }

        if (isset($request->id)) {
            return $notification ? null : $this->getSuccessResponseObject($result,
                    $request->id);
        }

        return null;
    }

    private function processBatch($batch)
    {
        if (count($batch) == 0) {
            return $this->getErrorResponseObject(self::$INVALID_REQUEST);
        }

        $batchResult = array();

        foreach ($batch as $request) {
            $requestResult = $this->processRequestObject($request);

            if (!is_null($requestResult)) {
                $batchResult[] = $requestResult;
            }
        }

        return (count($batchResult) == 0) ? null : $batchResult;
    }

    public function __construct($object)
    {
        $this->RPCObject           = $object;
        $this->reflectionRPCObject = new ReflectionObject($object);
    }

    public function process($requestJSON)
    {
        $request = json_decode($requestJSON);

        if (json_last_error() != JSON_ERROR_NONE) {
            return json_encode($this->getErrorResponseObject(self::$PARSE_ERROR));
        }

        if (is_object($request)) {
            $result = $this->processRequestObject($request);

            if (!is_null($result)) {
                return json_encode($result);
            }

            return '';
        } else if (is_array($request)) {
            $batchResult = $this->processBatch($request);

            return (count($batchResult) > 0) ? json_encode($batchResult) : '';
        }

        return json_encode($this->getErrorResponseObject(self::$INVALID_REQUEST));
    }
}