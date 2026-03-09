<?php

declare(strict_types=1);

namespace Tests\Unit\Response;

/**
 * Stub DTO for ResponseWrapperTest::test_toDto_returns_dto_from_json.
 */
final class ResponseWrapperTestDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {
    }

    public static function from(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['name'] ?? '')
        );
    }
}
