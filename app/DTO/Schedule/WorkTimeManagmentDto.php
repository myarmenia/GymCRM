<?php

namespace App\DTO\Schedule;
use Illuminate\Http\Request;

class WorkTimeManagmentDto
{
    public function __construct(
        public string $name,
        public ?int $status = null,
        public array $weekDays
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->name,
            status: $request->has('status') ? 1 : 0,
            weekDays: $request->week_days
        );
    }
}
