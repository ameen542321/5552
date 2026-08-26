<?php

namespace App\Domain\EmployeeOperations\Data;

final readonly class EmployeeOperationActor
{
    public function __construct(
        public ?int $id,
        public string $type,
        public string $name,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
        ];
    }
}
