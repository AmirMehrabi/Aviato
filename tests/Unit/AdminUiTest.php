<?php

namespace Tests\Unit;

use App\Support\AdminUi;
use PHPUnit\Framework\TestCase;

class AdminUiTest extends TestCase
{
    public function test_vm_power_states_have_semantic_labels_and_tones(): void
    {
        $this->assertSame([
            'label' => 'روشن',
            'tone' => 'success',
            'description' => 'ماشین مجازی روشن و در حال سرویس‌دهی است.',
        ], AdminUi::statusMeta('running'));

        $this->assertSame('خاموش', AdminUi::statusMeta('stopped')['label']);
        $this->assertSame('danger', AdminUi::statusMeta('stopped')['tone']);
    }

    public function test_unknown_statuses_remain_readable_and_neutral(): void
    {
        $this->assertSame([
            'label' => 'custom-state',
            'tone' => 'neutral',
            'description' => null,
        ], AdminUi::statusMeta('custom-state'));
    }
}
