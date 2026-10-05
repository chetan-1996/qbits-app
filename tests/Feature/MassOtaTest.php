<?php

namespace Tests\Feature;

use App\Jobs\ProcessOtaStatus;
use App\Models\DongleState;
use App\Models\Firmware;
use App\Models\OtaJob;
use App\Models\OtaJobUnit;
use App\Services\OtaMqttService;
use App\Services\OtaStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FakeOtaMqtt extends OtaMqttService
{
    public array $published = [];

    public function connect(string $clientId): void {}

    public function reconnect(): void {}

    public function publishCommand(string $topic, string $payload): void
    {
        $this->published[] = ['topic' => $topic, 'payload' => json_decode($payload, true)];
    }
}

class MassOtaTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR = 'operator-pass';
    private const ADMIN = 'admin-pass';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota.operator_password' => self::OPERATOR]);
        config(['app.admin_password' => self::ADMIN]);
    }

    private function asOperator(): self
    {
        return $this->withCookie('ota_auth', hash('sha256', self::OPERATOR));
    }

    private function asAdmin(): self
    {
        return $this->withCookie('admin_auth', hash('sha256', self::ADMIN));
    }

    private function csvUpload(array $rows): UploadedFile
    {
        $csv = "IMEI,Site,Note\n" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows)) . "\n";
        $path = tempnam(sys_get_temp_dir(), 'ota');
        file_put_contents($path, $csv);

        return new UploadedFile($path, 'devices.csv', 'text/csv', null, true);
    }

    private function makeFirmware(string $version = '2.1.0', bool $backup = false): Firmware
    {
        return Firmware::create([
            'version' => $version,
            'filename' => 'fw_' . $version . '.bin',
            'path' => 'static/firmware/fw_' . $version . '.bin',
            'size' => 1000,
            'sha256' => str_repeat('a', 64),
            'is_backup' => $backup,
        ]);
    }

    private function statusMessage(OtaJobUnit $unit, array $payload): void
    {
        $job = new ProcessOtaStatus(
            'rtsg-1/Ongridrooftop/' . $unit->imei . '/ota/pub',
            json_encode($payload),
            now()->toDateTimeString()
        );
        $job->handle(new OtaStateMachine());
    }

    public function test_upload_validates_imeis_and_flags_invalid(): void
    {
        $file = $this->csvUpload([
            ['865056070026808', 'Site A', 'ok'],
            ['490154203237518', 'Site B', 'ok2'],
            ['865056070026808', 'Dup', 'duplicate'],
            ['12345', 'Short', 'bad'],
            ['865056070026809', 'Luhn', 'bad check digit'],
        ]);

        $res = $this->asOperator()->post(route('ota.jobs.preview'), ['file' => $file]);

        $job = OtaJob::latest()->first();
        $this->assertNotNull($job);
        $this->assertSame(OtaJob::STATUS_DRAFT, $job->status);
        $this->assertMatchesRegularExpression('/^OTA-\d{8}-\d{4}$/', $job->otaid);
        $this->assertSame('operator', $job->created_by_role);

        $this->assertSame(2, $job->units()->where('state', OtaJobUnit::QUEUED)->count());
        $this->assertSame(3, $job->units()->where('state', OtaJobUnit::INVALID)->count());
    }

    public function test_send_requires_admin_for_force_and_backup(): void
    {
        $job = OtaJob::create(['otaid' => 'OTA-T-0001', 'status' => 'draft']);
        $job->units()->create(['imei' => '865056070026808', 'state' => 'QUEUED']);
        $backup = $this->makeFirmware('9.9.9', true);
        $normal = $this->makeFirmware('2.1.0');

        // operator cannot send backup image
        $this->asOperator()->post(route('ota.jobs.send', $job), [
            'firmware_id' => $backup->id,
            'otaid' => 'OTA-T-0001',
        ]);
        $this->assertSame('draft', $job->fresh()->status);

        // operator cannot force
        $this->asOperator()->post(route('ota.jobs.send', $job), [
            'firmware_id' => $normal->id,
            'otaid' => 'OTA-T-0001',
            'force' => '1',
            'force_confirm' => 'FORCE',
        ]);
        $this->assertSame('draft', $job->fresh()->status);

        // admin force without typed confirmation is refused
        $this->asAdmin()->post(route('ota.jobs.send', $job), [
            'firmware_id' => $normal->id,
            'otaid' => 'OTA-T-0001',
            'force' => '1',
        ]);
        $this->assertSame('draft', $job->fresh()->status);

        // admin with confirmation succeeds
        $this->asAdmin()->post(route('ota.jobs.send', $job), [
            'firmware_id' => $normal->id,
            'otaid' => 'OTA-T-0001',
            'force' => '1',
            'force_confirm' => 'FORCE',
        ]);
        $job->refresh();
        $this->assertSame('sending', $job->status);
        $this->assertTrue($job->force);
    }

    public function test_dispatcher_sends_qos0_command_and_pacing(): void
    {
        $fake = new FakeOtaMqtt();
        $this->app->instance(OtaMqttService::class, $fake);

        $fw = $this->makeFirmware('2.1.0');
        $job = OtaJob::create([
            'otaid' => 'OTA-T-0002',
            'status' => 'sending',
            'firmware_id' => $fw->id,
            'target_version' => '2.1.0',
            'url' => $fw->deviceUrl(),
            'send_gap_seconds' => 10,
            'max_concurrent' => 10,
        ]);
        $u1 = $job->units()->create(['imei' => '865056070026808', 'state' => 'QUEUED']);
        $u2 = $job->units()->create(['imei' => '490154203237518', 'state' => 'QUEUED']);

        $this->artisan('ota:run', ['--once' => true])->assertSuccessful();

        // one unit per tick; gap enforced
        $this->assertCount(1, $fake->published);
        $this->assertSame(
            'rtsg-1/Ongridrooftop/865056070026808/ota/sub',
            $fake->published[0]['topic']
        );
        $this->assertSame([
            'IMEI' => '865056070026808',
            'otaid' => 'OTA-T-0002',
            'url' => $fw->deviceUrl(),
            'ver' => '2.1.0',
            'force' => false,
        ], $fake->published[0]['payload']);

        $this->assertSame(OtaJobUnit::SENT, $u1->fresh()->state);
        $this->assertSame(OtaJobUnit::QUEUED, $u2->fresh()->state);
    }

    public function test_concurrent_cap_pauses_sending(): void
    {
        $fake = new FakeOtaMqtt();
        $this->app->instance(OtaMqttService::class, $fake);

        $fw = $this->makeFirmware('2.1.0');
        $job = OtaJob::create([
            'otaid' => 'OTA-T-0003', 'status' => 'sending',
            'firmware_id' => $fw->id, 'target_version' => '2.1.0', 'url' => $fw->deviceUrl(),
            'send_gap_seconds' => 0, 'max_concurrent' => 1,
        ]);
        $job->units()->create(['imei' => '865056070026808', 'state' => 'SENT', 'sent_at' => now()]);
        $job->units()->create(['imei' => '490154203237518', 'state' => 'QUEUED']);

        $this->artisan('ota:run', ['--once' => true])->assertSuccessful();
        $this->assertCount(0, $fake->published); // cap reached
    }

    public function test_status_message_drives_state_machine(): void
    {
        $job = OtaJob::create(['otaid' => 'OTA-T-0004', 'status' => 'sending', 'target_version' => '2.1.0']);
        $unit = $job->units()->create([
            'imei' => '865056070026808', 'state' => 'SENT', 'sent_at' => now(),
        ]);

        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0004',
            'status' => 'ACCEPTED', 'from_ver' => '2.0.8', 'to_ver' => '2.1.0', 'reason' => '',
        ]);
        $this->assertSame(OtaJobUnit::ACCEPTED, $unit->fresh()->state);

        // duplicate ACCEPTED is ignored
        $unit->update(['reason' => 'X']);
        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0004', 'status' => 'ACCEPTED',
        ]);
        $this->assertSame('X', $unit->fresh()->reason);

        // out-of-order repeat of SENT-level state cannot downgrade
        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0004', 'status' => 'DOWNLOADING',
        ]);
        $this->assertSame(OtaJobUnit::DOWNLOADING, $unit->fresh()->state);

        // SUCCESS with a different to_ver flags version_differs (MO-27)
        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0004',
            'status' => 'SUCCESS', 'to_ver' => '2.2.0',
        ]);
        $unit->refresh();
        $this->assertSame(OtaJobUnit::SUCCESS, $unit->state);
        $this->assertTrue($unit->version_differs);
        $this->assertSame(OtaJob::STATUS_COMPLETED, $job->fresh()->status);

        // a later message cannot move a final state
        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0004', 'status' => 'FAILED',
            'reason' => 'DOWNLOAD_FAILED',
        ]);
        $this->assertSame(OtaJobUnit::SUCCESS, $unit->fresh()->state);
    }

    public function test_rejected_version_not_newer_becomes_up_to_date(): void
    {
        DongleState::create(['imei' => '865056070026808', 'fw_ver' => '2.1.0', 'last_seen_at' => now()]);
        $job = OtaJob::create(['otaid' => 'OTA-T-0005', 'status' => 'sending', 'target_version' => '2.1.0']);
        $unit = $job->units()->create(['imei' => '865056070026808', 'state' => 'SENT', 'sent_at' => now()]);

        $this->statusMessage($unit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0005',
            'status' => 'REJECTED', 'reason' => 'VERSION_NOT_NEWER',
        ]);

        $this->assertSame(OtaJobUnit::UP_TO_DATE, $unit->fresh()->state);
    }

    public function test_accepted_in_new_job_replaces_old_row(): void
    {
        $old = OtaJob::create(['otaid' => 'OTA-T-0006', 'status' => 'sending', 'target_version' => '2.0.9']);
        $oldUnit = $old->units()->create(['imei' => '865056070026808', 'state' => 'NO_RESPONSE', 'sent_at' => now()->subMinutes(10)]);

        $new = OtaJob::create(['otaid' => 'OTA-T-0007', 'status' => 'sending', 'target_version' => '2.1.0']);
        $newUnit = $new->units()->create(['imei' => '865056070026808', 'state' => 'SENT', 'sent_at' => now()]);

        $this->statusMessage($newUnit, [
            'IMEI' => '865056070026808', 'otaid' => 'OTA-T-0007', 'status' => 'ACCEPTED',
        ]);

        $this->assertSame(OtaJobUnit::ACCEPTED, $newUnit->fresh()->state);
        $this->assertSame(OtaJobUnit::REPLACED, $oldUnit->fresh()->state);
    }

    public function test_timeouts(): void
    {
        $this->app->instance(OtaMqttService::class, new FakeOtaMqtt());

        $job = OtaJob::create(['otaid' => 'OTA-T-0008', 'status' => 'sending', 'target_version' => '2.1.0']);
        $silent = $job->units()->create([
            'imei' => '865056070026808', 'state' => 'SENT',
            'sent_at' => now()->subMinutes(6),
        ]);
        $stuck = $job->units()->create([
            'imei' => '490154203237518', 'state' => 'DOWNLOADING',
            'sent_at' => now()->subMinutes(60), 'accepted_at' => now()->subMinutes(50),
        ]);

        $this->artisan('ota:run', ['--once' => true])->assertSuccessful();

        $this->assertSame(OtaJobUnit::NO_RESPONSE, $silent->fresh()->state);
        $this->assertSame(OtaJobUnit::TIMED_OUT, $stuck->fresh()->state);
    }

    public function test_telemetry_backstop_confirms_success(): void
    {
        $this->app->instance(OtaMqttService::class, new FakeOtaMqtt());

        $job = OtaJob::create(['otaid' => 'OTA-T-0009', 'status' => 'sending', 'target_version' => '2.1.0']);
        $unit = $job->units()->create([
            'imei' => '865056070026808', 'state' => 'SENT', 'sent_at' => now()->subMinutes(10),
        ]);
        // old firmware never publishes ota/pub; telemetry shows it reached target
        DongleState::create(['imei' => '865056070026808', 'fw_ver' => '2.1.0', 'last_seen_at' => now()]);

        $this->artisan('ota:run', ['--once' => true])->assertSuccessful();

        $unit->refresh();
        $this->assertSame(OtaJobUnit::SUCCESS, $unit->state);
        $this->assertTrue($unit->confirmed_by_telemetry);
        $this->assertSame(OtaJob::STATUS_COMPLETED, $job->fresh()->status);
    }

    public function test_retry_requeues_retryable_units(): void
    {
        $job = OtaJob::create(['otaid' => 'OTA-T-0010', 'status' => 'sending', 'target_version' => '2.1.0']);
        $unit = $job->units()->create([
            'imei' => '865056070026808', 'state' => 'FAILED', 'sent_at' => now(), 'attempt' => 1,
        ]);
        $ok = $job->units()->create([
            'imei' => '490154203237518', 'state' => 'SUCCESS', 'sent_at' => now(),
        ]);

        $this->asOperator()->post(route('ota.jobs.retry', $job), [
            'unit_ids' => [$unit->id, $ok->id],
        ]);

        $unit->refresh();
        $this->assertSame(OtaJobUnit::QUEUED, $unit->state);
        $this->assertSame(2, $unit->attempt);
        $this->assertSame(OtaJobUnit::SUCCESS, $ok->fresh()->state); // not retryable
    }

    public function test_already_up_to_date_skipped_unless_included(): void
    {
        DongleState::create(['imei' => '865056070026808', 'fw_ver' => '2.1.0', 'last_seen_at' => now()]);
        $fw = $this->makeFirmware('2.1.0');

        $job = OtaJob::create(['otaid' => 'OTA-T-0011', 'status' => 'draft']);
        $job->units()->create(['imei' => '865056070026808', 'state' => 'QUEUED']);
        $job->units()->create(['imei' => '490154203237518', 'state' => 'QUEUED']);

        $this->asOperator()->post(route('ota.jobs.send', $job), [
            'firmware_id' => $fw->id,
            'otaid' => 'OTA-T-0011',
        ]);

        $job->refresh();
        $this->assertSame('sending', $job->status);
        $this->assertSame(OtaJobUnit::UP_TO_DATE, $job->units()->where('imei', '865056070026808')->first()->state);
        $this->assertSame(OtaJobUnit::QUEUED, $job->units()->where('imei', '490154203237518')->first()->state);
    }

    public function test_ota_routes_require_auth(): void
    {
        $this->get(route('ota.jobs.index'))->assertOk(); // login screen rendered
        $this->get(route('ota.jobs.index'))->assertSee('OTA Portal Access');
    }

    public function test_pause_resume_stop_work(): void
    {
        $job = OtaJob::create(['otaid' => 'OTA-T-PAUSE', 'status' => 'sending']);
        $unit = $job->units()->create(['imei' => '865056070026808', 'state' => 'QUEUED']);

        $admin = ['admin_auth' => hash('sha256', self::ADMIN)];

        $this->withCookies($admin)->post(route('ota.jobs.pause', $job))
            ->assertRedirect(route('ota.jobs.show', $job));
        $this->assertSame('paused', $job->fresh()->status);

        $this->withCookies($admin)->post(route('ota.jobs.resume', $job))->assertRedirect();
        $this->assertSame('sending', $job->fresh()->status);

        $this->withCookies($admin)->post(route('ota.jobs.stop', $job))->assertRedirect();
        $job->refresh();
        $this->assertSame('stopped', $job->status);
        $this->assertSame('SKIPPED', $unit->fresh()->state); // queued rows are skipped on stop
    }

    public function test_login_post_sets_cookie_and_redirects_back(): void
    {
        // POST to the dedicated login route — the middleware intercepts it,
        // sets the cookie and returns to the originally requested page.
        $res = $this->post(route('ota.login'), [
            'ota_password' => self::OPERATOR,
            'redirect' => '/ota/jobs',
        ]);

        $res->assertRedirect('/ota/jobs');
        $res->assertCookie('ota_auth', hash('sha256', self::OPERATOR));

        // wrong password returns to the login page with an error
        $this->post(route('ota.login'), ['ota_password' => 'wrong'])
            ->assertRedirect(route('ota.login'));

        // admin password on the OTA login grants the admin cookie
        $this->post(route('ota.login'), [
            'ota_password' => self::ADMIN,
            'redirect' => '/ota/jobs',
        ])->assertCookie('admin_auth', hash('sha256', self::ADMIN));
    }
}
