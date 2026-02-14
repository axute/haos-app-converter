<?php

namespace App\File\Traits;

trait DataTrait
{

    protected array $data = [];

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($this->data[$key]);
        } else {
            $this->data[$key] = $value;
        }
    }

    public function addData(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }
}