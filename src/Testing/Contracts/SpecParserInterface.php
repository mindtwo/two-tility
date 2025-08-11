<?php

namespace mindtwo\TwoTility\Testing\Contracts;

/**
 * Interface for parsing API specifications.
 *
 * This interface defines the methods required for parsing API specification files
 * and retrieving the definitions and responses for API endpoints.
 *
 * @phpstan-type ApiOperation array{
 *   method: string,
 *   authRequired: bool,
 *   responses: array<string, mixed>
 * }
 * @phpstan-type ApiSpecPath array{
 *   faker: array<string, mixed>,
 *   list?: ApiOperation,
 *   show?: ApiOperation,
 *   create?: ApiOperation,
 *   update?: ApiOperation,
 *   delete?: ApiOperation
 * }
 * @phpstan-type ApiStructuredSpec array<string, ApiSpecPath>
 */
interface SpecParserInterface
{
    public function parse(string $file): void;

    /** @return array<string, ApiSpecPath> */
    public function getPaths(): array;

    /** @return array<string, array<string, mixed>> */
    public function getFakerDefinitions(): array;

    /** @return array<string, array<string>> */
    public function getSupportedMethods(): array;

    /** @return array<string, array<string, bool>> */
    public function getAuthRequirements(): array;
}
