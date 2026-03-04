<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceFee;

class ServiceFeesSeeder extends Seeder
{
    public function run(): void
    {
        // NOTE:
        // rules is JSON (array cast). Keep it simple & extend later.
        // You can standardize rule schema like:
        // {
        //   "type": "fixed|percent|tiered",
        //   "percent": 2.5,
        //   "min": 1,
        //   "max": 50,
        //   "tiers": [{"min":0,"max":100,"amount":5}, ...],
        //   "applies_to": ["order","wallet_withdraw","escrow_release"]
        // }

        $items = [
            [
                'code'      => 'DELIVERY_BASE',
                'amount'    => 0,
                'rules'     => [
                    'type' => 'tiered',
                    'tiers' => [
                        ['min' => 0,   'max' => 50,  'amount' => 10],
                        ['min' => 50,  'max' => 100, 'amount' => 15],
                        ['min' => 100, 'max' => null,'amount' => 20],
                    ],
                    'currency' => 'EGP',
                    'applies_to' => ['order_delivery'],
                ],
                'is_active' => true,
            ],
            [
                'code'      => 'ESCROW_FEE',
                'amount'    => 0,
                'rules'     => [
                    'type'    => 'percent',
                    'percent' => 2.5,
                    'min'     => 1,
                    'max'     => 50,
                    'currency'=> 'EGP',
                    'applies_to' => ['escrow_create', 'escrow_release'],
                ],
                'is_active' => true,
            ],
            [
                'code'      => 'WALLET_WITHDRAW_FEE',
                'amount'    => 5,
                'rules'     => [
                    'type' => 'fixed',
                    'currency' => 'EGP',
                    'applies_to' => ['wallet_withdraw'],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($items as $row) {
            ServiceFee::updateOrCreate(
                ['code' => $row['code']],
                [
                    'amount'    => $row['amount'],
                    'rules'     => $row['rules'],
                    'is_active' => $row['is_active'],
                ]
            );
        }
    }
}
