<?php

use Illuminate\Database\Seeder;

class QuestionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $question = new \App\Question;
        $question->word = "people";
        $question->enabled = true;
        $question->count = 1;
        $question->save();
    }
}
