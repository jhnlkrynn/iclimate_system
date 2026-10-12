<?php

/*
|--------------------------------------------------------------------------
| Yield unit (panel revision 1.2)
|--------------------------------------------------------------------------
|
| Every yield in iClimate is stored and shown in tons per hectare (t/ha), rounded to two decimals: recorded harvests,
| MAO production records, model predictions and every chart, table, tooltip and KPI.
|
| A harvest entered in another unit is converted on save (App\Support\YieldUnit):
|
|     yield (t/ha) = total harvest in tons ÷ area harvested in hectares
|
| where sacks are first turned into kilograms with `sack_kg`, and kilograms into tons (÷ 1,000).
| Change `sack_kg` here (or with YIELD_SACK_KG in .env) if the MAO uses a different sack weight.
*/

return [
    'unit' => 't/ha',

    'unit_label' => 'tons per hectare (t/ha)',

    'decimals' => 2,

    // Weight of one sack of palay (fresh, unmilled rice) in kilograms.
    'sack_kg' => (float) env('YIELD_SACK_KG', 50),

    // Units a farmer or MAO may enter a harvest in. The stored value is always t/ha.
    'input_units' => [
        't/ha' => 'Tons per hectare (t/ha)',
        'tons' => 'Total tons harvested',
        'kg' => 'Total kilograms harvested',
        'sacks' => 'Total sacks harvested',
    ],

    // The highest believable rice yield; anything above is rejected as a typing mistake.
    'max_t_per_ha' => 15,
];
