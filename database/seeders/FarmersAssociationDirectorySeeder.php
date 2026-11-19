<?php

namespace Database\Seeders;

use App\Models\FarmerOrganization;
use Illuminate\Database\Seeder;

class FarmersAssociationDirectorySeeder extends Seeder
{
    /** Provisional directory transcribed from the MAO photograph; no contacts or accounts are created. */
    public function run(): void
    {
        $rows = [
            ['Balibago', 'Balibago Farmers Association Inc.', 'Danilo Saraza Cahigan'],
            ['Bungahan', "Bungahan-Baldeo Irrigator's Association Inc.", 'Eriberto Masipag Estacion'],
            ['Prenza', "Calaca Irrigators's Association Inc.", 'Ronald Lubugan Villanueva'],
            ['Binubusan', 'Binubusan Irrigators and Farmers Association Inc.', 'Damaceno Panganiban Delos Reyes'],
            ['Sandiego', 'Buhay ng Magsasaka Farmers Association Inc.', 'Miguela Vergara Noche'],
            ['Prenza', 'Altura Spring Farmers Irrigators Association Inc.', 'Rodel Magana Malinay'],
            ['Kapito', 'Lumang Tubigan Farmers Association Inc.', 'Sonny Lainez Nioko'],
            ['Luyahan', 'Luyahan Rice Farmers Association Inc.', 'Alejandro Carandang Condicion'],
            ['Malaruhatan', 'Malaruhatan Rice Farmers Association Inc.', 'Apolinario Rosales Lama'],
            ['Matabungkay', 'Matabungkay Farmers Association Inc.', 'Dominador Bascuguin Javier'],
            ['Kapito', 'Molino-Resagwa Farmers Irrigators Inc.', 'Hemeriano Hernandez Tanarte'],
            ['Prenza', "Prenza, Ladron Balanoy Sampalucan Calumpit Irrigator's Farmers Association", 'Elmer Marquez Montealegre'],
            ['Prenza', 'Prenza, Bagbag, Bungahan Farmers Association Inc.', 'Fernando Montealegre Laparan'],
            ['Kapito', 'Saluysoy Rice Farmers Association Inc.', 'Efren Mercado Abellera'],
            ['Sandiego', 'Tan-Ag Farmers Association', 'Conrado Castillo Villar'],
        ];

        foreach ($rows as [$barangay, $name, $chairman]) {
            // Official barangay spelling ("Sandiego" -> "San Diego"), and the name matched the way the unique index
            // compares it (MariaDB utf8mb4_unicode_ci ignores case): "Tan-Ag" is the existing "Tan-ag" association.
            $barangay = \App\Support\LianBarangays::canonicalName($barangay) ?? $barangay;
            $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            $association = FarmerOrganization::query()->where(function ($query) use ($name, $key): void {
                $query->where('directory_key', $key)->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            })->first();

            if (! $association) {
                $association = new FarmerOrganization(['name' => $name, 'barangay' => $barangay, 'farm_type' => null]);
            }

            $association->directory_key ??= $key;
            $association->directory_status ??= 'provisional';
            $association->chairman_name ??= $chairman;
            $association->directory_source ??= 'MAO photograph (provisional)';
            $association->save();
        }
    }
}
