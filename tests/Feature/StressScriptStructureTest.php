<?php

namespace Tests\Feature;

use Tests\TestCase;

class StressScriptStructureTest extends TestCase
{
    public function test_k6_script_classifies_expected_stress_responses_without_accepting_auth_or_server_errors(): void
    {
        $script = file_get_contents(base_path('stress-test-100-all-operations.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("server_errors_500: ['count==0']", $script);
        $this->assertStringContainsString("auth_errors: ['count==0']", $script);
        $this->assertStringContainsString("unacceptable_response_rate: ['rate==0']", $script);
        $this->assertStringContainsString('const isProtectedConflict = res.status === 409;', $script);
        $this->assertStringContainsString('const isExpectedValidation = res.status === 422 && expectedValidationNames.has(name);', $script);
        $this->assertStringContainsString('php artisan stress:validate-integrity', $script);
    }
}
