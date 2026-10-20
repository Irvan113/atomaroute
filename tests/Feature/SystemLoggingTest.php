<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use App\Services\SystemLogService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SystemLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_log_service_records_all_five_levels()
    {
        $debug = SystemLogService::debug('Debugging memory usage', ['mem' => '10MB']);
        $info = SystemLogService::info('Background job processed', ['count' => 12]);
        $warn = SystemLogService::warn('API rate limit reaching threshold', ['usage' => '85%']);
        $error = SystemLogService::error('Database deadlock detected', [], 'db.deadlock');
        $critical = SystemLogService::critical('Server memory exhausted', [], 'server.oom');

        $this->assertDatabaseHas('system_logs', [
            'id' => $debug->id,
            'level' => 'DEBUG',
            'category' => 'troubleshooting',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'id' => $info->id,
            'level' => 'INFO',
            'category' => 'system',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'id' => $warn->id,
            'level' => 'WARN',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'id' => $error->id,
            'level' => 'ERROR',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'id' => $critical->id,
            'level' => 'CRITICAL',
        ]);
    }

    public function test_sensitive_data_is_masked_in_logs()
    {
        $log = SystemLogService::security('auth.test', 'Testing security payload', [
            'username' => 'testuser',
            'password' => 'supersecret123',
            'token' => 'bearer-abc-xyz',
            'api_key' => 'secret-gemini-key',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'id' => $log->id,
        ]);

        $retrieved = SystemLog::find($log->id);
        $this->assertStringContainsString('******** (masked)', $retrieved->context['password']);
        $this->assertStringContainsString('******** (masked)', $retrieved->context['token']);
        $this->assertStringContainsString('******** (masked)', $retrieved->context['api_key']);
        $this->assertEquals('testuser', $retrieved->context['username']);
    }

    public function test_troubleshoot_helper_captures_exception_stack_trace()
    {
        try {
            throw new Exception('Simulated test exception failure');
        } catch (Exception $e) {
            $log = SystemLogService::troubleshoot($e, 'test.exception');
        }

        $this->assertNotNull($log);
        $this->assertEquals('ERROR', $log->level);
        $this->assertEquals('troubleshooting', $log->category);
        $this->assertStringContainsString('Simulated test exception failure', $log->message);
        $this->assertIsArray($log->context['trace']);
        $this->assertNotEmpty($log->context['trace']);
    }

    public function test_performance_monitoring_helper_flags_slow_queries()
    {
        $fastLog = SystemLogService::performance('query.fast', 150, 'Fast database query');
        $slowLog = SystemLogService::performance('query.slow', 1400, 'Heavy analytic aggregation', [], 1000);

        $this->assertEquals('INFO', $fastLog->level);
        $this->assertEquals('WARN', $slowLog->level);
        $this->assertEquals(1400, $slowLog->execution_time_ms);
        $this->assertEquals('performance', $slowLog->category);
    }

    public function test_auth_events_trigger_security_and_audit_logs()
    {
        $user = User::factory()->create([
            'email' => 'auditor@example.com',
        ]);

        // 1. Successful Login Event
        event(new \Illuminate\Auth\Events\Login('web', $user, false));
        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'auth.login_success',
        ]);

        // 2. Failed Login Event
        event(new \Illuminate\Auth\Events\Failed('web', null, ['email' => 'hacker@example.com', 'password' => 'wrong']));
        $this->assertDatabaseHas('system_logs', [
            'category' => 'security',
            'action' => 'auth.login_failed',
            'level' => 'WARN',
        ]);

        // 3. Logout Event (Compliance Audit)
        event(new \Illuminate\Auth\Events\Logout('web', $user));
        $this->assertDatabaseHas('system_logs', [
            'category' => 'audit',
            'action' => 'auth.logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_non_admin_is_forbidden_from_admin_system_logs()
    {
        $regularUser = User::factory()->create([
            'id' => 888,
            'is_admin' => false,
        ]);

        $this->actingAs($regularUser);
        $response = $this->get(route('admin.system-logs'));
        $response->assertForbidden();
    }

    public function test_admin_can_access_and_filter_system_logs_dashboard()
    {
        $admin = User::factory()->create([
            'id' => 1,
            'is_admin' => true,
        ]);
        $this->actingAs($admin);

        SystemLogService::security('auth.brute_force', 'Percobaan serang brute force', [], 'CRITICAL');
        SystemLogService::performance('http.slow', 1500, 'Endpoint kalkulasi lambat');

        $response = $this->get(route('admin.system-logs'));
        $response->assertOk();
        $response->assertSee('Pusat Pemantauan Log & Audit');
        $response->assertSee('Percobaan serang brute force');

        // Test Livewire component filtering
        Volt::test('pages.admin-system-logs')
            ->set('filterLevel', 'CRITICAL')
            ->assertSee('Percobaan serang brute force')
            ->assertDontSee('Endpoint kalkulasi lambat');
    }

    public function test_admin_can_generate_test_logs_and_export_csv()
    {
        $admin = User::factory()->create([
            'id' => 1,
            'is_admin' => true,
        ]);
        $this->actingAs($admin);

        Volt::test('pages.admin-system-logs')
            ->call('generateTestLogs')
            ->assertSee('Berhasil membuat 7 sampel log');

        $this->assertDatabaseHas('system_logs', [
            'level' => 'DEBUG',
            'action' => 'debug',
        ]);

        $this->assertDatabaseHas('system_logs', [
            'level' => 'CRITICAL',
            'action' => 'critical',
        ]);

        $test = Volt::test('pages.admin-system-logs');
        $response = $test->call('exportCsv');
        $this->assertNotNull($response);
    }
}
