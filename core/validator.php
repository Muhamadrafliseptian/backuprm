<?php
/**
 * file   : validator.php
 * path   : C:\xampp\htdocs\backuprm\core\validator.php
 * fungsi : Validasi input (required, min, max, in) dengan error terkumpul
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/internal_guard.php';

class Validator
{
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = "{$label} wajib diisi.";
        }
        return $this;
    }

    public function min(string $field, int $len, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) < $len) {
            $this->errors[$field] = "{$label} minimal {$len} karakter.";
        }
        return $this;
    }

    public function max(string $field, int $len, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) > $len) {
            $this->errors[$field] = "{$label} maksimal {$len} karakter.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $label = null): self
    {
        $label = $label ?? $field;
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "{$label} tidak valid.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors ? (string) reset($this->errors) : '';
    }
}