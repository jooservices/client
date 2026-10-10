<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Logging;

use Faker\Factory;
use JOOservices\Client\Logging\LogSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    #[Test]
    public function testRedactsSensitiveKeysAfterPercentDecodingAndRetainsSafeValues(): void
    {
        $faker = Factory::create();
        $accessToken = $faker->sha256();
        $apiKey = $faker->sha256();
        $token = $faker->sha256();
        $safeValue = $faker->word();
        $safeKeyValue = $faker->sha256();
        $uri = "https://api.example.test/?access%5Ftoken={$accessToken}&api%2Dkey={$apiKey}&%74oken={$token}&page={$safeValue}&page.token={$safeKeyValue}";

        $sanitized = (new LogSanitizer())->sanitize(['uri' => $uri]);

        self::assertSame(
            "https://api.example.test/?access%5Ftoken=[redacted]&api%2Dkey=[redacted]&%74oken=[redacted]&page={$safeValue}&page.token={$safeKeyValue}",
            $sanitized['uri'],
        );
    }

    #[Test]
    public function testRedactsSemicolonDelimitedSensitiveParametersAndRetainsSafeValues(): void
    {
        $faker = Factory::create();
        $token = $faker->sha256();
        $safeValue = $faker->word();
        $uri = "https://api.example.test/?page={$safeValue};token={$token};theme=light";

        $sanitized = (new LogSanitizer())->sanitize(['uri' => $uri]);

        self::assertSame(
            "https://api.example.test/?page={$safeValue};token=[redacted];theme=light",
            $sanitized['uri'],
        );
    }

    #[Test]
    public function testRedactsTheCompleteSensitiveValueWhenItContainsAQuestionMark(): void
    {
        $faker = Factory::create();
        $token = $faker->sha256() . '?tail=' . $faker->sha256();
        $safeValue = $faker->word();
        $uri = "https://api.example.test/?access_token={$token}&state={$safeValue}";

        $sanitized = (new LogSanitizer())->sanitize(['uri' => $uri]);

        self::assertSame(
            "https://api.example.test/?access_token=[redacted]&state={$safeValue}",
            $sanitized['uri'],
        );
    }

    #[Test]
    public function testRedactsSensitiveFragmentParametersAndRetainsSafeValues(): void
    {
        $faker = Factory::create();
        $fragmentToken = $faker->sha256();
        $safeValue = $faker->word();
        $uri = "https://api.example.test/path#access_token={$fragmentToken}&state={$safeValue}&panel=details";

        $sanitized = (new LogSanitizer())->sanitize(['uri' => $uri]);

        self::assertSame(
            'https://api.example.test/path#access_token=[redacted]&state=' . $safeValue . '&panel=details',
            $sanitized['uri'],
        );
    }
}
