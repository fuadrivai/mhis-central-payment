<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Bintaro',
                'code' => 'BTR',
                'va_fixed_prefix' => '85765010',
                'va_dynamic_prefix' => '85765991',
            ],
            [
                'name' => 'Bangka',
                'code' => 'BGK',
                'va_fixed_prefix' => '85765020',
                'va_dynamic_prefix' => '85765992',
            ],
            [
                'name' => 'Semarang',
                'code' => 'SMG',
                'va_fixed_prefix' => '85765030',
                'va_dynamic_prefix' => '85765993',
            ],
        ];

        foreach ($branches as $branchData) {
            /** @var Branch $branch */
            $branch = Branch::query()->updateOrCreate(
                ['code' => $branchData['code']],
                [
                    'name' => $branchData['name'],
                    'va_fixed_prefix' => $branchData['va_fixed_prefix'],
                    'va_dynamic_prefix' => $branchData['va_dynamic_prefix'],
                    'is_active' => true,
                ],
            );

            if ($branch->api_key === null) {
                $branch->api_key = bin2hex(random_bytes(32));
                $branch->save();
            }
        }
    }
}
