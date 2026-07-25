<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CustomerImageUpdateTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $testFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and user
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        // Find the seeded user and login
        $this->user = User::where('email', 'dev@localhost.com')->first();
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function tearDown(): void
    {
        // Clean up test files from filesystem
        foreach ($this->testFiles as $path) {
            $fullPath = public_path($path);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }

        parent::tearDown();
    }

    private function trackFile(?string $path)
    {
        if ($path) {
            $this->testFiles[] = $path;
        }
    }

    public function test_can_upload_profile_and_nic_images_on_create()
    {
        $profile = UploadedFile::fake()->image('profile.jpg');
        $nic = UploadedFile::fake()->image('nic.jpg');

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => $profile,
            'nic_image' => $nic,
        ]);

        $response->assertStatus(201);
        $customer = Customer::first();
        $this->assertNotNull($customer->profile_image);
        $this->assertNotNull($customer->nic_image);

        $this->trackFile($customer->profile_image);
        $this->trackFile($customer->nic_image);

        $this->assertTrue(File::exists(public_path($customer->profile_image)));
        $this->assertTrue(File::exists(public_path($customer->nic_image)));
    }

    public function test_can_replace_profile_image_and_old_file_is_deleted()
    {
        // 1. Create customer with an image
        $profile1 = UploadedFile::fake()->image('profile1.jpg');
        $customer = Customer::create([
            'code' => 'CUST-99999',
            'name' => 'John Doe',
            'phone' => '0771234567',
        ]);

        // Upload first image
        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => $profile1,
        ]);

        $response->assertStatus(200);
        $customer->refresh();
        $oldPath = $customer->profile_image;
        $this->trackFile($oldPath);
        $this->assertTrue(File::exists(public_path($oldPath)));

        // 2. Upload second image to replace first
        $profile2 = UploadedFile::fake()->image('profile2.jpg');
        $response2 = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => $profile2,
        ]);

        $response2->assertStatus(200);
        $customer->refresh();
        $newPath = $customer->profile_image;
        $this->trackFile($newPath);

        // Verify new path is saved, new file exists, and old file is deleted
        $this->assertNotEquals($oldPath, $newPath);
        $this->assertTrue(File::exists(public_path($newPath)));
        $this->assertFalse(File::exists(public_path($oldPath)));
    }

    public function test_can_explicitly_delete_profile_image_by_sending_null()
    {
        // 1. Create customer with an image
        $profile = UploadedFile::fake()->image('profile.jpg');
        $customer = Customer::create([
            'code' => 'CUST-99999',
            'name' => 'John Doe',
            'phone' => '0771234567',
        ]);

        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => $profile,
        ]);

        $response->assertStatus(200);
        $customer->refresh();
        $oldPath = $customer->profile_image;
        $this->trackFile($oldPath);
        $this->assertTrue(File::exists(public_path($oldPath)));

        // 2. Send request to update with profile_image set to null
        $response2 = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => null,
        ]);

        $response2->assertStatus(200);
        $customer->refresh();

        // Verify path is null in DB and file is deleted from filesystem
        $this->assertNull($customer->profile_image);
        $this->assertFalse(File::exists(public_path($oldPath)));
    }

    public function test_omitting_profile_image_leaves_existing_intact()
    {
        // 1. Create customer with an image
        $profile = UploadedFile::fake()->image('profile.jpg');
        $customer = Customer::create([
            'code' => 'CUST-99999',
            'name' => 'John Doe',
            'phone' => '0771234567',
        ]);

        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe',
            'phone' => '0771234567',
            'profile_image' => $profile,
        ]);

        $response->assertStatus(200);
        $customer->refresh();
        $oldPath = $customer->profile_image;
        $this->trackFile($oldPath);
        $this->assertTrue(File::exists(public_path($oldPath)));

        // 2. Send request omitting profile_image
        $response2 = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'John Doe Updated',
            'phone' => '0771234567',
        ]);

        $response2->assertStatus(200);
        $customer->refresh();

        // Verify path is NOT null in DB and file STILL exists
        $this->assertEquals($oldPath, $customer->profile_image);
        $this->assertTrue(File::exists(public_path($oldPath)));
    }
}
