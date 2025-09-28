<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Training;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(VoyagerDatabaseSeeder::class);

        $trainings = [
            "Les bases de l'appareillage de série",
            "Examen clinique express",
            "Orthèses sur-mesure - Rappels et astuces",
            "La rééducation fonctionnelle",
            "Les Clés de la Délivrance Orthopédique",
            "Béquilles"
        ];
        foreach ($trainings as $tr) {
            Training::create(['name' => $tr]);
        }

        Lesson::create([
            'training_id' => 1,
            'title' => 'Orthopédia'
        ]);
    }
}
