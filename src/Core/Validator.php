<?php
declare(strict_types=1);

namespace Tendari\Core;

final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label = null): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v === '') {
            $this->errors[$field] = ($label ?? ucfirst($field)) . ' is required.';
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Enter a valid email address.';
        }
        return $this;
    }

    public function min(string $field, int $len): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) < $len) {
            $this->errors[$field] = 'Must be at least ' . $len . ' characters.';
        }
        return $this;
    }

    public function max(string $field, int $len): self
    {
        $v = (string)($this->data[$field] ?? '');
        if (mb_strlen($v) > $len) {
            $this->errors[$field] = 'Must be at most ' . $len . ' characters.';
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = 'Invalid value.';
        }
        return $this;
    }

    public function int(string $field): self
    {
        $v = $this->data[$field] ?? '';
        if ($v !== '' && $v !== null && filter_var($v, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = 'Must be a number.';
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
}
