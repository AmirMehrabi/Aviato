<?php

namespace Tests\Unit;

use App\Services\Sms\KavenegarLookupClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KavenegarLookupClientTest extends TestCase
{
    #[DataProvider('names')]
    public function test_name_token_only_contains_text_before_the_first_whitespace(string $name, string $expected): void
    {
        $this->assertSame($expected, KavenegarLookupClient::nameToken($name));
    }

    public static function names(): array
    {
        return [
            'regular full name' => ['علی رضایی', 'علی'],
            'extra spaces' => ['  علی   رضایی  ', 'علی'],
            'line break' => ["علی\nرضایی", 'علی'],
            'single name' => ['پشتیبانی', 'پشتیبانی'],
            'empty name' => ['   ', ''],
        ];
    }
}
