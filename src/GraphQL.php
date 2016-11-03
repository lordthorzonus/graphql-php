<?php
namespace GraphQL;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Language\AST\Document;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Definition\Directive;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use React\Promise\Promise;

class GraphQL
{
    /**
     * @param Schema $schema
     * @param $requestString
     * @param mixed $rootValue
     * @param array <string, string>|null $variableValues
     * @param string|null $operationName
     *
     * @return Promise
     */
    public static function execute(
        Schema $schema,
        $requestString,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null
    ) {
        return self::executeAndReturnResult($schema, $requestString, $rootValue, $contextValue, $variableValues, $operationName);
    }

    /**
     * @param Schema $schema
     * @param $requestString
     * @param null $rootValue
     * @param null $contextValue
     * @param null $variableValues
     * @param null $operationName
     *
     * @return Promise
     */
    public static function executeAndReturnResult(
        Schema $schema,
        $requestString,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        $operationName = null
    ) {

        $promise = new Promise(function ($resolve) use ($schema, $requestString, $rootValue, $contextValue, $variableValues, $operationName) {
            try {
                if ($requestString instanceof Document) {
                    $documentAST = $requestString;
                } else {
                    $source = new Source($requestString ?: '', 'GraphQL request');
                    $documentAST = Parser::parse($source);
                }

                /** @var QueryComplexity $queryComplexity */
                $queryComplexity = DocumentValidator::getRule('QueryComplexity');
                $queryComplexity->setRawVariableValues($variableValues);

                $validationErrors = DocumentValidator::validate($schema, $documentAST);

                if (!empty($validationErrors)) {
                    return $resolve(new ExecutionResult(null, $validationErrors));
                } else {
                    return $resolve(Executor::execute($schema, $documentAST, $rootValue, $contextValue, $variableValues, $operationName));
                }
            } catch (Error $e) {
                return $resolve(new ExecutionResult(null, [$e]));
            }
        });

        $promise->then(null, function ($error) {
            return new ExecutionResult(null, $error);
        });

        return $promise;
    }

    /**
     * @return array
     */
    public static function getInternalDirectives()
    {
        return array_values(Directive::getInternalDirectives());
    }
}
