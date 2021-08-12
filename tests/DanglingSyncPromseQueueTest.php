<?php

namespace GraphQL\Tests;

use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class DanglingSyncPromseQueueTest extends TestCase
{
    static $invocations = 0;

    public function testThatSyncPromiseQueueContainsReferenceAfterTest(): void
    {
        $returnTypeForTestQuery = new ObjectType([
            'name' => 'ReturnTypeForTestQuery',
            'fields' => [
                'fieldOne' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static function (): string {
                        static::$invocations++;
                        if (2 === static::$invocations) {
                            throw new \RuntimeException('error resolving value on second iteration');
                        }

                        return 'ValueForFieldOne';

                    },
                ],
                'fieldTwo' => [
                    'type' => Type::string(),
                    'resolve' => static function (): Deferred {
                        return new Deferred(fn() => 'ValueForFieldTwo');
                    },
                ],
            ],
        ]);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'testQuery' => [
                        'type' => Type::nonNull(Type::listOf($returnTypeForTestQuery)),
                        'resolve' => static function (): array {
                            return [
                                [],
                                [],
                            ];
                        },
                    ],
                ],
            ]),
            'types' => [
                $returnTypeForTestQuery,
            ],
        ]);

        $query = <<<'GRAPHQL'
{
  testQuery {
    fieldOne
    fieldTwo
  }
}
GRAPHQL;


        $result = GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE);


//        echo var_export($result, true). "\n";

    }

    protected function tearDown(): void
    {
        if (null !== \GraphQL\Executor\Promise\Adapter\SyncPromise::$queue && !\GraphQL\Executor\Promise\Adapter\SyncPromise::$queue->isEmpty()) {
            var_dump(\GraphQL\Executor\Promise\Adapter\SyncPromise::$queue);
            throw new \RuntimeException('\GraphQL\Executor\Promise\Adapter\SyncPromise::$queue not empty');
        }
    }
}
