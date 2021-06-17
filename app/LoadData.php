<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Phpml\Dataset\CsvDataset;

class LoadData
{
    public function modChunk($array, $size)
    {
        $i = 0;
        $arraySize = count($array);
        while ($i < $arraySize) {
            $len = min($arraySize - $i, $size);
            $chunk = array_slice($array, $i, $len);
            DB::table('documents')->insert($chunk);
            $i += $len;
        }
    }

    public function modChunk2($array, $size)
    {
        $i = 0;
        $data = [];
        $arraySize = count($array);
        foreach ($array as $category => $words) {
            foreach ($words as $word => $frequency) {
                $i++;
                $data[] = ['category' => $category, 'word' => $word, 'frequency' => $frequency];
                if ($i == $size) {
                    DB::table('word_frequencies')->insert($data);
                    $data = [];
                    $i = 0;
                }
            }
        }
        if ($i > 0) {
            DB::table('word_frequencies')->insert($data);
            $data = [];
        }
    }

    public function startLearn($lBound, $uBound)
    {
//        Bayes::clear();
        Bayes::initStopWords();
        $dataset = new CsvDataset('E:\bbc-reverse.csv', 1);
        $samples = [];
        $i = 0;
        foreach ($dataset->getSamples() as $sample) {
            if ($i >= $lBound && $i < $uBound) {
                $samples[] = $sample[0];
            }
            $i++;
        }
        $targets = [];
        $i = 0;
        foreach ($dataset->getTargets() as $target) {
            if ($i >= $lBound && $i < $uBound) {
                $targets[] = $target;
            }
            $i++;
        }

        for ($i = 0; $i < count($samples); $i++) {
            Bayes::learn($samples[$i], $targets[$i]);
        }

        $documents = Bayes::$documents;

        $wordCountFrequency = Bayes::$wordFrequencyCount;

//        Log::info($documents[0]);
$iii=0;
        foreach ($documents as $document){
            $document['document']=utf8_encode($document['document']);
            DB::table('documents')->insert($document);
//            Log::info($document['category']);
        }
//        $this->modChunk($documents, 10000);
        $this->modChunk2($wordCountFrequency, 10000);


    }

    public function testClassification($lBound, $uBound)
    {
        $classifier = new Bayes();
        return $classifier->validate($lBound, $uBound);

    }
}

