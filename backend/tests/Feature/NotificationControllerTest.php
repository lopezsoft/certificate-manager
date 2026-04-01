<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Jobs\SendAdminExpiringCertificatesReportJob;
use App\Jobs\SendExpiringCertificatesNotificationsJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests de característica para NotificationController.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class NotificationControllerTest extends TestCase
{
    // ── Protección de autenticación ──────────────────────────────────────────

    public function test_get_notifications_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    public function test_triggerNow_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/v1/admin/certificates/notify-now');

        $response->assertStatus(401);
    }

    public function test_mark_all_read_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(401);
    }

    public function test_expiring_devuelve_401_sin_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/certificates/expiring');

        $response->assertStatus(401);
    }

    // ── triggerNow — requiere is_admin ───────────────────────────────────────

    public function test_triggerNow_devuelve_403_si_usuario_no_es_admin(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::make(['id' => 1, 'email' => 'user@test.com']);
        $user->is_admin = false;

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/admin/certificates/notify-now');

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    }

    public function test_triggerNow_despacha_jobs_cuando_es_admin(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::make(['id' => 99, 'email' => 'admin@test.com']);
        $user->is_admin = true;

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/admin/certificates/notify-now', [
                'include_admin_report' => true,
            ]);

        $response->assertOk();
        Queue::assertPushed(SendExpiringCertificatesNotificationsJob::class);
        Queue::assertPushed(SendAdminExpiringCertificatesReportJob::class);
    }

    public function test_triggerNow_no_despacha_admin_report_cuando_se_deshabilita(): void
    {
        Queue::fake();

        /** @var User $user */
        $user = User::make(['id' => 99, 'email' => 'admin@test.com']);
        $user->is_admin = true;

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/admin/certificates/notify-now', [
                'include_admin_report' => false,
            ]);

        $response->assertOk();
        Queue::assertPushed(SendExpiringCertificatesNotificationsJob::class);
        Queue::assertNotPushed(SendAdminExpiringCertificatesReportJob::class);
    }
}
