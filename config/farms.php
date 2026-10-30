<?php

// A farmer's farms (App\Models\Farm, FarmController). Farm size is always an exact number: in hectares, or in square
// meters turned into hectares. The same largest size applies to every place a farm size is entered (Add / Edit Farm,
// the dashboard's Farm Information pop-up, the profile form and the planting plan).
return [
    'max_hectares' => (float) env('FARM_MAX_HECTARES', 10000),
];
