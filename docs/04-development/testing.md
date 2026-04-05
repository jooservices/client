# Testing

## Test Types

- Unit tests
- Integration tests
- Architecture tests
- Live network tests (opt-in)
- Performance benchmarks (PHPBench)

## Commands

- `composer test`
- `composer test:coverage`
- `composer test:unit`
- `composer test:integration`
- `composer test:arch`

## Coverage Gate

`composer test:coverage` generates `coverage/` plus `coverage/clover.xml` and enforces a 98% minimum line-coverage threshold.

## Coverage Source

Coverage is intentionally measured against the package's exercised client runtime surface rather than the entire `src/` tree. This remains a deliberate divergence from DTO.
