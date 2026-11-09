<?php

namespace Tests\Feature;

use App\Http\Requests\StoreQdnValidationRequest;
use Tests\TestCase;

class PeValidationDispositionTest extends TestCase
{
    private function errors(array $input): array
    {
        return validator($input, (new StoreQdnValidationRequest)->rules())->errors()->keys();
    }

    public function test_disposition_is_required_and_old_is_valid_payload_fails(): void
    {
        $this->assertContains('disposition', $this->errors(['is_valid' => true, 'issued_to_employee_id' => '1705']));
    }

    public function test_invalid_needs_no_issued_to_but_others_do(): void
    {
        $this->assertSame([], $this->errors(['disposition' => 'invalid']));
        $this->assertSame(['issued_to_employee_id'], $this->errors(['disposition' => 'rework']));
        $this->assertSame([], $this->errors(['disposition' => 'use_as_is', 'issued_to_employee_id' => '1705']));
    }
}
