<?php

namespace App\Runs\Execution;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Adapter over opis/json-schema.
 */
final class JsonSchemaValidator
{
    public function __construct(
        private readonly Validator $validator,
        private readonly ErrorFormatter $formatter,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string> errors, empty when valid
     */
    public function validate(string $json, array $schema): array
    {
        $result = $this->validator->validate(json_decode($json), (string) json_encode($schema));

        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ($this->formatter->format($result->error(), true) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = "{$path}: {$message}";
            }
        }

        return $errors;
    }
}
