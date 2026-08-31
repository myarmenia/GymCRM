<?php

namespace Tests\Unit;

use App\Support\StableUuid;
use PHPUnit\Framework\TestCase;

class StableUuidTest extends TestCase
{
    public function test_seed_identities_are_deterministic(): void
    {
        $this->assertSame(
            'e60cc079-7242-5254-9a6f-a9df152192bb',
            StableUuid::from('seed:langs:hy'),
        );
        $this->assertSame(
            StableUuid::seedIdentity('langs', 'hy'),
            StableUuid::seedIdentity('langs', 'hy'),
        );
        $this->assertNotSame(
            StableUuid::seedIdentity('langs', 'hy')['uuid'],
            StableUuid::seedIdentity('langs', 'en')['uuid'],
        );
    }
}
