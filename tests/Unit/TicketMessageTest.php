<?php

namespace Tests\Unit;

use App\Models\TicketMessage;
use PHPUnit\Framework\TestCase;

class TicketMessageTest extends TestCase
{
    public function test_single_line_breaks_and_paragraphs_are_preserved(): void
    {
        $message = new TicketMessage(['body' => "First line\nSecond line\n\nNew paragraph"]);

        $this->assertSame("<p>First line<br />Second line</p>\n<p>New paragraph</p>\n", $message->renderedBody());
    }

    public function test_formatting_and_code_are_preserved_without_allowing_html_or_unsafe_links(): void
    {
        $message = new TicketMessage(['body' => "**Bold**\n\n- One\n- Two\n\n```\nfirst\nsecond\n```\n\n<script>alert(1)</script>\n\n[unsafe](javascript:alert%281%29)"]);
        $html = $message->renderedBody();

        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<li>One</li>', $html);
        $this->assertStringContainsString("<pre><code>first\nsecond\n</code></pre>", $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
