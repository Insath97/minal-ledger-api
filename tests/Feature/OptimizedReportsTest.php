<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\User;
use App\Services\DatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptimizedReportsTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $customer;

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
            'outstanding_balance' => 0.00,
        ]);
    }

    public function test_dashboard_stats_and_dynamic_outstanding_calculation()
    {
        // Set up sales and payments to test stats
        // current month
        $currentMonthDate = now()->toDateString();
        
        Sale::create([
            'customer_id' => $this->customer->id,
            'business_type' => 'retail',
            'total_amount' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'payment_status' => 'unpaid',
            'sale_date' => $currentMonthDate,
            'created_by' => $this->user->id,
        ]);
        
        $this->customer->increment('outstanding_balance', 1000);

        // Make a payment in current month
        Payment::create([
            'customer_id' => $this->customer->id,
            'total_amount' => 400,
            'payment_method' => 'cash',
            'payment_date' => $currentMonthDate,
            'created_by' => $this->user->id,
        ]);
        
        $this->customer->decrement('outstanding_balance', 400);

        $response = $this->getJson('/api/v1/dashboard/stats');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1000, $data['total_sales']);
        $this->assertEquals(600, $data['total_outstanding']);
        
        // Outstanding prev month calculation check:
        // Outstanding (End of Prev Month) = Current (600) + Payments (400) - Sales Due (1000) = 0
        // (outstanding change: 0 -> 600)
        $this->assertEquals(0, $data['outstanding_change']);
    }

    public function test_dues_aging_report_grouping()
    {
        // Create an unpaid sale 15 days ago (0-30 days)
        Sale::create([
            'customer_id' => $this->customer->id,
            'business_type' => 'retail',
            'total_amount' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'payment_status' => 'unpaid',
            'sale_date' => now()->subDays(15)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Create an unpaid sale 45 days ago (31-60 days)
        Sale::create([
            'customer_id' => $this->customer->id,
            'business_type' => 'retail',
            'total_amount' => 800,
            'paid_amount' => 0,
            'due_amount' => 800,
            'payment_status' => 'unpaid',
            'sale_date' => now()->subDays(45)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/reports/dues-aging');
        $response->assertStatus(200);

        $summary = $response->json('data.summary');
        $this->assertEquals(1300, $summary['total_due']);
        $this->assertEquals(500, $summary['current_0_30']);
        $this->assertEquals(800, $summary['aging_31_60']);
        $this->assertEquals(0, $summary['aging_61_90']);
        $this->assertEquals(0, $summary['over_90']);
    }

    public function test_customer_statement_running_balance_chronological()
    {
        // Create first sale on Day 1
        Sale::create([
            'customer_id' => $this->customer->id,
            'business_type' => 'retail',
            'total_amount' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'payment_status' => 'unpaid',
            'sale_date' => '2026-07-01',
            'created_by' => $this->user->id,
        ]);

        // Create payment on Day 2
        Payment::create([
            'customer_id' => $this->customer->id,
            'total_amount' => 300,
            'payment_method' => 'cash',
            'payment_date' => '2026-07-02',
            'created_by' => $this->user->id,
        ]);

        // Create second sale on Day 3
        Sale::create([
            'customer_id' => $this->customer->id,
            'business_type' => 'retail',
            'total_amount' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'payment_status' => 'unpaid',
            'sale_date' => '2026-07-03',
            'created_by' => $this->user->id,
        ]);

        // Request full statement
        $response = $this->getJson("/api/v1/reports/customer-statement?customer_id={$this->customer->id}");
        $response->assertStatus(200);

        $transactions = $response->json('data.transactions');
        
        // Output is sorted descending (newest first)
        // Tx0: Day 3 Sale 500 -> Running balance should be 1200 (1000 - 300 + 500)
        // Tx1: Day 2 Payment 300 -> Running balance should be 700 (1000 - 300)
        // Tx2: Day 1 Sale 1000 -> Running balance should be 1000
        $this->assertCount(3, $transactions);
        $this->assertEquals(1200, $transactions[0]['balance']);
        $this->assertEquals(700, $transactions[1]['balance']);
        $this->assertEquals(1000, $transactions[2]['balance']);
        
        // Test statement filter with date_from = 2026-07-02
        // Prior sale (Day 1) total_amount = 1000. Prior payments = 0. Opening balance should be 1000.
        $responseFiltered = $this->getJson("/api/v1/reports/customer-statement?customer_id={$this->customer->id}&date_from=2026-07-02");
        $responseFiltered->assertStatus(200);

        $dataFiltered = $responseFiltered->json('data');
        $this->assertEquals(1000, $dataFiltered['summary']['opening_balance']);
        $this->assertEquals(1200, $dataFiltered['summary']['closing_balance']);
        
        // Transactions from 2026-07-02 onwards are:
        // Tx0: Day 3 Sale 500 (balance = 1200)
        // Tx1: Day 2 Payment 300 (balance = 700)
        $txsFiltered = $dataFiltered['transactions'];
        $this->assertCount(2, $txsFiltered);
        $this->assertEquals(1200, $txsFiltered[0]['balance']);
        $this->assertEquals(700, $txsFiltered[1]['balance']);
    }

    public function test_database_backup_streaming()
    {
        $backupService = new DatabaseService();
        $filePath = $backupService->export();
        
        $this->assertTrue(file_exists($filePath));
        
        // clean up backup file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
