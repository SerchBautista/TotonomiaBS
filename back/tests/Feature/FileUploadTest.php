<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_file_to_s3_disk(): void
    {
        Storage::fake('s3');

        $this->seed();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        config()->set('filesystems.cloud', 's3');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/user/files/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertCreated();
        $path = $response->json('data.path');
        Storage::disk('s3')->assertExists($path);
    }

    public function test_admin_can_upload_file_to_s3_disk(): void
    {
        Storage::fake('s3');

        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        config()->set('filesystems.cloud', 's3');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/files/upload', [
                'file' => UploadedFile::fake()->image('admin-avatar.jpg'),
            ]);

        $response->assertCreated();
        $path = $response->json('data.path');
        Storage::disk('s3')->assertExists($path);
    }

    public function test_regular_user_cannot_upload_using_admin_upload_route(): void
    {
        Storage::fake('s3');

        $this->seed();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/files/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertForbidden();
    }
}
