<?php

declare(strict_types=1);

namespace GraphQL\Server;

use function array_change_key_case;
use function is_string;
use function json_decode;
use function json_last_error;
use function strlen;

use const CASE_LOWER;
use const JSON_ERROR_NONE;

/**
 * Structure representing parsed HTTP parameters for GraphQL operation
 */
class OperationParams
{
    /**
     * Id of the query (when using persistent queries).
     *
     * Valid aliases (case-insensitive):
     * - id
     * - queryId
     * - documentId
     *
     * @api
     * @var string|null
     */
    public $queryId;

    /**
     * @api
     * @var string|null
     */
    public $query;

    /**
     * @api
     * @var string
     */
    public $operation;

    /**
     * @api
     * @var array<string, mixed>|null
     */
    public $variables;

    /**
     * @api
     * @var array<string, mixed>|null
     */
    public $extensions;

    /** @var array<string, mixed> */
    private $originalInput;

    private bool $readOnly;

    /**
     * Creates an instance from given array
     *
     * @param array<string, mixed> $params
     *
     * @api
     */
    public static function create(array $params, bool $readonly = false): OperationParams
    {
        $instance = new static();

        $params                  = array_change_key_case($params, CASE_LOWER);
        $instance->originalInput = $params;

        $params += [
            'query' => null,
            'queryid' => null,
            'documentid' => null, // alias to queryid
            'id' => null, // alias to queryid
            'operationname' => null,
            'variables' => null,
            'extensions' => null,
        ];

        if ($params['variables'] === '') {
            $params['variables'] = null;
        }

        // Some parameters could be provided as serialized JSON.
        foreach (['extensions', 'variables'] as $param) {
            if (! is_string($params[$param])) {
                continue;
            }

            $tmp = json_decode($params[$param], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $params[$param] = $tmp;
        }

        $instance->query      = $params['query'];
        $instance->queryId    = $params['queryid'] ?? $params['documentid'] ?? $params['id'];
        $instance->operation  = $params['operationname'];
        $instance->variables  = $params['variables'];
        $instance->extensions = $params['extensions'];
        $instance->readOnly   = $readonly;

        // Apollo server/client compatibility: look for the queryid in extensions
        if (isset($instance->extensions['persistedQuery']['sha256Hash']) && strlen($instance->query ?? '') === 0 && strlen($instance->queryId ?? '') === 0) {
            $instance->queryId = $instance->extensions['persistedQuery']['sha256Hash'];
        }

        return $instance;
    }

    /**
     * @return mixed
     *
     * @api
     */
    public function getOriginalInput(string $key)
    {
        return $this->originalInput[$key] ?? null;
    }

    /**
     * Indicates that operation is executed in read-only context
     * (e.g. via HTTP GET request)
     *
     * @api
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }
}
