<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SaleImageUpdateTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $customer;
    private $testFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);

        $this->user = User::where('email', 'dev@localhost.com')->first();
        $token = auth('api')->login($this->user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $this->customer = Customer::create([
            'code' => 'CUST-00001',
            'name' => 'Jane Doe',
            'phone' => '0771112233',
        ]);
    }

    protected function tearDown(): void
    {
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

    public function test_can_upload_bill_image_on_sale_create()
    {
        $bill = UploadedFile::fake()->image('bill.jpg');

        $response = $this->postJson('/api/v1/sales', [
            'business_type' => 'retail',
            'total_amount' => 2000,
            'paid_amount' => 2000,
            'sale_date' => '2026-07-25',
            'bill_image' => $bill,
        ]);

        $response->assertStatus(201);
        $sale = Sale::first();
        $this->assertNotNull($sale->bill_image);
        $this->trackFile($sale->bill_image);

        $this->assertTrue(File::exists(public_path($sale->bill_image)));
    }

    public function test_can_replace_bill_image_and_old_file_is_deleted()
    {
        $bill1 = UploadedFile::fake()->image('bill1.jpg');

        $response = $this->postJson('/api/v1/sales', [
            'business_type' => 'retail',
            'total_amount' => 2000,
            'paid_amount' => 2000,
            'sale_date' => '2026-07-25',
            'bill_image' => $bill1,
        ]);

        $response->assertStatus(201);
        $sale = Sale::first();
        $oldPath = $sale->bill_image;
        $this->trackFile($oldPath);
        $this->assertTrue(File::exists(public_path($oldPath)));

        $bill2 = UploadedFile::fake()->image('bill2.jpg');
        $response2 = $this->postJson("/api/v1/sales/{$sale->id}", [
            '_method' => 'PUT',
            'invoice_number' => 'INV-UPDATED',
            'bill_image' => $bill2,
        ]);

        $response2->assertStatus(200);
        $sale->refresh();
        $newPath = $sale->bill_image;
        $this->trackFile($newPath);

        $this->assertNotEquals($oldPath, $newPath);
        $this->assertTrue(File::exists(public_path($newPath)));
        $this->assertFalse(File::exists(public_path($oldPath)));
    }

    public function test_can_explicitly_delete_bill_image_on_update()
    {
        $bill = UploadedFile::fake()->image('bill.jpg');

        $response = $this->postJson('/api/v1/sales', [
            'business_type' => 'retail',
            'total_amount' => 2000,
            'paid_amount' => 2000,
            'sale_date' => '2026-07-25',
            'bill_image' => $bill,
        ]);

        $response->assertStatus(201);
        $sale = Sale::first();
        $oldPath = $sale->bill_image;
        $this->trackFile($oldPath);
        $this->assertTrue(File::exists(public_path($oldPath)));

        $response2 = $this->putJson("/api/v1/sales/{$sale->id}", [
            'invoice_number' => 'INV-NO-IMAGE',
            'bill_image' => null,
        ]);

        $response2->assertStatus(200);
        $sale->refresh();

        $this->assertNull($sale->bill_image);
        $this->assertFalse(File::exists(public_path($oldPath)));
    }
}
