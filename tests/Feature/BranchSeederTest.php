<?php

namespace Tests\Feature;

use App\Models\Branch;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_seeder_creates_three_branches_with_prefixes_and_api_keys(): void
    {
        $this->seed(BranchSeeder::class);

        $this->assertDatabaseCount('branches', 3);

        $this->assertDatabaseHas('branches', [
            'code' => 'BTR',
            'name' => 'Bintaro',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
        ]);

        $this->assertDatabaseHas('branches', [
            'code' => 'BGK',
            'name' => 'Bangka',
            'va_fixed_prefix' => '85765020',
            'va_dynamic_prefix' => '85765992',
        ]);

        $this->assertDatabaseHas('branches', [
            'code' => 'SMG',
            'name' => 'Semarang',
            'va_fixed_prefix' => '85765030',
            'va_dynamic_prefix' => '85765993',
        ]);

        $this->assertSame(3, Branch::query()->whereNotNull('api_key')->count());

        foreach (Branch::query()->get(['api_key']) as $branch) {
            $this->assertNotNull($branch->api_key);
            $this->assertSame(64, strlen((string) $branch->api_key));
        }
    }
}
