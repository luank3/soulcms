<?php

declare(strict_types=1);

namespace SoulCMS\Support;

use SoulCMS\Http\Exception\ValidationException;

/**
 * A small, dependency-free validator.
 *
 * Rules are declared as `field => 'rule|rule:parameter'`. The validator
 * returns the *cleaned* values, so a controller never has to re-parse input.
 *
 * ```php
 * $data = (new Validator($request->getParsedBody()))->validate([
 *     'nick'     => 'required|string:3,32|regex:/^[A-Za-z0-9\-_]+$/',
 *     'email'    => 'required|email|max:255',
 *     'password' => 'required|string:min,6|confirmed:password',
 * ]);
 * ```
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $valid = [];

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * Validate the data and return the cleaned values.
     *
     * @param array<string,string> $rules
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public function validate(array $rules): array
    {
        $this->errors = [];
        $this->valid = [];

        foreach ($rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;

            foreach (explode('|', $ruleSet) as $rule) {
                $this->apply($field, $rule, $value);
            }

            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $this->valid[$field] = $value;
            }
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors, 'The given data was invalid.');
        }

        return $this->valid;
    }

    /**
     * Validate without throwing.
     *
     * @param array<string,string> $rules
     * @return array{valid:bool,errors:array<string,list<string>>,data:array<string,mixed>}
     */
    public function check(array $rules): array
    {
        try {
            $data = $this->validate($rules);
        } catch (ValidationException $exception) {
            return ['valid' => false, 'errors' => $exception->getErrors(), 'data' => []];
        }

        return ['valid' => true, 'errors' => [], 'data' => $data];
    }

    /**
     * @return array<string,list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function apply(string $field, string $rule, mixed $value): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->fail($field, $this->message('validation.required', ['field' => $this->label($field)]));
                }
                break;

            case 'string':
                if ($value !== null && !is_scalar($value)) {
                    $this->fail($field, $this->message('validation.string', ['field' => $this->label($field)]));
                    break;
                }

                // "string:min,max" bounds the length of the value.
                if ($parameter !== null && $this->hasValue($value)) {
                    [$min, $max] = array_pad(explode(',', $parameter), 2, null);
                    $length = $this->length($value);

                    if ($min !== null && $length < (int) $min) {
                        $this->fail($field, $this->message('validation.min', ['field' => $this->label($field), 'min' => (int) $min]));
                    } elseif ($max !== null && $max !== '' && $length > (int) $max) {
                        $this->fail($field, $this->message('validation.max', ['field' => $this->label($field), 'max' => (int) $max]));
                    }
                }
                break;

            case 'int':
            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->fail($field, $this->message('validation.int', ['field' => $this->label($field)]));
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->fail($field, $this->message('validation.numeric', ['field' => $this->label($field)]));
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, ['0', '1', 0, 1, 'true', 'false'], true)) {
                    $this->fail($field, $this->message('validation.boolean', ['field' => $this->label($field)]));
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->fail($field, $this->message('validation.email', ['field' => $this->label($field)]));
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->fail($field, $this->message('validation.url', ['field' => $this->label($field)]));
                }
                break;

            case 'min':
                if ($this->hasValue($value) && $this->length($value) < (int) $parameter) {
                    $this->fail($field, 'The ' . $this->label($field) . ' field must be at least ' . $parameter . ' characters.');
                }
                break;

            case 'max':
                if ($this->hasValue($value) && $this->length($value) > (int) $parameter) {
                    $this->fail($field, 'The ' . $this->label($field) . ' field may not be longer than ' . $parameter . ' characters.');
                }
                break;

            case 'regex':
                if ($this->hasValue($value) && !is_string($value)) {
                    $this->fail($field, $this->message('validation.string', ['field' => $this->label($field)]));
                    break;
                }

                if ($this->hasValue($value) && @preg_match((string) $parameter, (string) $value) !== 1) {
                    $this->fail($field, $this->message('validation.regex', ['field' => $this->label($field)]));
                }
                break;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $parameter));
                if ($this->hasValue($value) && !in_array((string) $value, $allowed, true)) {
                    $this->fail($field, $this->message('validation.in', ['field' => $this->label($field), 'allowed' => implode(', ', $allowed)]));
                }
                break;

            case 'confirmed':
                $other = $this->data[(string) $parameter] ?? null;
                if ($value !== $other) {
                    $this->fail($field, $this->message('validation.confirmed', ['field' => $this->label($field)]));
                }
                break;

            case 'same':
                $other = $this->data[(string) $parameter] ?? null;
                if ($value !== $other) {
                    $this->fail($field, $this->message('validation.confirmed', ['field' => $this->label($field)]));
                }
                break;

            case 'different':
                $other = $this->data[(string) $parameter] ?? null;
                if ($value === $other) {
                    $this->fail($field, $this->message('validation.different', ['field' => $this->label($field), 'other' => (string) $parameter]));
                }
                break;

            case 'slug':
                if ($this->hasValue($value) && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                    $this->fail($field, $this->message('validation.slug', ['field' => $this->label($field)]));
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->fail($field, $this->message('validation.array', ['field' => $this->label($field)]));
                }
                break;
        }
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function length(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        return strlen((string) $value);
    }

    private function label(string $field): string
    {
        return str_replace(['_', '-'], ' ', $field);
    }

    /**
     * A message from the catalogue, with the placeholders filled in.
     *
     * @param array<string,scalar> $replace
     */
    private function message(string $key, array $replace = []): string
    {
        return Lang::get($key, $replace);
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
