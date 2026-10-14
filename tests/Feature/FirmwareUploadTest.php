<?php

namespace Tests\Feature;

use App\Models\Firmware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmwareUploadTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'admin-pass';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_password' => self::ADMIN]);
        Storage::fake('public');
    }

    private function asAdmin(): self
    {
        return $this->withCookie('admin_auth', hash('sha256', self::ADMIN));
    }

    /**
     * Build a minimal valid AL0228 image: byte 0 = 0xE9, LE uint32
     * at offset 32 = 0xABCD5432, version string at bytes 48-79 (MO-45).
     */
    private function fakeImage(string $version = '2.1.0', bool $validHeader = true): UploadedFile
    {
        $bin = "\xE9" . str_repeat("\x00", 31)
            . pack('V', $validHeader ? 0xABCD5432 : 0xDEADBEEF)
            . str_repeat("\x00", 12)
            . substr(str_pad($version . "\0", 32, "\0"), 0, 32)
            . str_repeat("\x00", 200);

        $path = tempnam(sys_get_temp_dir(), 'fw');
        file_put_contents($path, $bin);

        return new UploadedFile($path, 'image.bin', 'application/octet-stream', null, true);
    }

    public function test_valid_upload_creates_registry_entry(): void
    {
        $res = $this->asAdmin()->post(route('firmware.store'), [
            'firmware' => $this->fakeImage('2.1.0'),
            'version' => '2.1.0',
            'notes' => 'release notes',
        ]);

        $res->assertRedirect(route('firmware.index'));

        $fw = Firmware::where('version', '2.1.0')->first();
        $this->assertNotNull($fw);
        $this->assertSame('release notes', $fw->notes);
        $this->assertFalse($fw->is_backup);
        $this->assertSame(64, strlen($fw->sha256));
        Storage::disk('public')->assertExists('static/firmware/' . $fw->filename);
    }

    public function test_version_mismatch_is_refused(): void
    {
        $res = $this->asAdmin()->post(route('firmware.store'), [
            'firmware' => $this->fakeImage('2.1.0'),
            'version' => '2.2.0',
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(0, Firmware::count());
    }

    public function test_bad_header_is_refused(): void
    {
        $res = $this->asAdmin()->post(route('firmware.store'), [
            'firmware' => $this->fakeImage('2.1.0', validHeader: false),
            'version' => '2.1.0',
        ]);

        $res->assertSessionHas('error');
        $this->assertSame(0, Firmware::count());
    }

    public function test_backup_version_is_flagged(): void
    {
        $this->asAdmin()->post(route('firmware.store'), [
            'firmware' => $this->fakeImage('9.9.9'),
            'version' => '9.9.9',
        ]);

        $fw = Firmware::where('version', '9.9.9')->first();
        $this->assertNotNull($fw);
        $this->assertTrue($fw->is_backup);
    }

    public function test_duplicate_version_is_refused(): void
    {
        $this->makeExisting('2.1.0');

        $res = $this->asAdmin()->post(route('firmware.store'), [
            'firmware' => $this->fakeImage('2.1.0'),
            'version' => '2.1.0',
        ]);

        $res->assertSessionHasErrors('version');
        $this->assertSame(1, Firmware::count());
    }

    private function makeExisting(string $version): void
    {
        Firmware::create([
            'version' => $version,
            'filename' => 'old_' . $version . '.bin',
            'path' => 'static/firmware/old_' . $version . '.bin',
            'size' => 10,
            'sha256' => str_repeat('b', 64),
        ]);
    }
}
