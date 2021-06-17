<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Phpml\Dataset\CsvDataset;
use Phpml\Metric\Accuracy;

/**
 * Naive-Bayes Classifier
 */
class Bayes
{
    protected static $stopWords;

    //this static variable we will use later to compute probabilities of category

    /**
     * array of our category names
     *
     * @var array
     */
    public static $categories;

    /**
     * array of objects {document,category}
     *
     * @var array
     */

    public static $documents;

    /**
     *  for each category, how often were documents mapped to it
     *  $docCount[$category] = count of documents in the category of type $category
     * @var array
     */
    public static $docCount;

    /**
     * number of documents we have learned from
     *
     * @var number
     */
    public static $totalDocuments;

    /**
     * Vocabulary list
     * all distinct words was learned from
     * @var array
     */
    public static $vocabulary;

    /**
     * Vocabulary size
     *
     * @var number
     */
    public static $vocabularySize;

    /**
     * for each category, how many words total were mapped to it
     * $wordCount[$category] = count of words in $category
     * @var array
     */
    public static $wordCount;

    /**
     * word frequency table for each category
     *  for each category, what frequent was a given word mapped to it
     * matrix of 2d : $wordFrequencyCount[$category][$word] = frequency
     * @var array
     */

    public static function clear()
    {
        self::$totalDocuments = 0;
        self::$wordFrequencyCount = null;
        self::$docCount = null;
        self::$wordCount = null;
        self::$vocabulary = null;
        self::$vocabularySize = 0;
        self::$documents = null;
        self::$categories = null;
    }

    public static $wordFrequencyCount;

    public static function tokenizer($text)
    {
        // convert everything to lowercase
        $text = mb_strtolower($text);

        // split $text to the words and put result in the $matches array
        preg_match_all('/[[:alpha:]]+/u', $text, $matches);

        //matches[0] is the first match list of words

        $res = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            if (strlen($matches[0][$i]) > 2) {
                //check if a word belongs to $stopWords array
                // we dont need a stop words in classify
                if (!(in_array($matches[0][$i], self::$stopWords))) {
                    $res[] = Porter::stem($matches[0][$i]);
                }
            }
        }

        return $res;
    }

    /**
     * Initialize an instance of a Naive-Bayes Classifier
     */

    public function __construct()
    {
        //push data to static variables from database
        $this->initFromDatabase();
        //push data from a file to a static variable $stopWords
        self::initStopWords();
    }

    public function initFromDatabase()
    {
        //here we will use db facade instead of eloquent
        if (!cache()->has('totalDocuments')) {
            Log::info('init from first');
            $documents = DB::table('documents')->select('*')->get();
            foreach ($documents as $document) {
                $category = $document->category;
                self::$categories[$category] = true;
                self::$totalDocuments++;
                if (isset($this->docCount[$category])) {
                    self::$docCount[$category]++;
                } else {
                    self::$docCount[$category] = 1;
                }
            }

            $categoriesWords = DB::table('word_frequencies')
                ->select('category', DB::raw('sum(frequency) as count'))
                ->groupBy(['category'])->get();

            foreach ($categoriesWords as $categoryWords) {
                self::$wordCount[$categoryWords->category] = $categoryWords->count;
            }

            self::$vocabulary = DB::table('word_frequencies')
                ->select('word')->distinct()->get();

            self::$vocabularySize = count(self::$vocabulary);

            $wordFrequencies = DB::table('word_frequencies')->select('*')->get();

            foreach ($wordFrequencies as $wordFrequency) {
                self::$wordFrequencyCount[$wordFrequency->category][$wordFrequency->word] = $wordFrequency->frequency;
            }

            cache()->put('categories', self::$categories, now()->addMonth());
            cache()->put('vocabularySize', self::$vocabularySize, now()->addMonth());
            cache()->put('vocabulary', self::$vocabulary, now()->addMonth());
            cache()->put('docCount', self::$docCount, now()->addMonth());
            cache()->put('wordCount', self::$wordCount, now()->addMonth());
            cache()->put('wordFrequencyCount', self::$wordFrequencyCount, now()->addMonth());
            cache()->put('totalDocuments', self::$totalDocuments, now()->addMonth());
        } else {
            self::$categories = cache()->get('categories');
            self::$vocabularySize = cache()->get('vocabularySize');
            self::$vocabulary = cache()->get('vocabulary');
            self::$docCount = cache()->get('docCount');
            self::$wordCount = cache()->get('wordCount');
            self::$wordFrequencyCount = cache()->get('wordFrequencyCount');
            self::$totalDocuments = cache()->get('totalDocuments');
        }
    }

    //this function for test classifier on multi documents
    public function validate($lBound, $uBound)
    {
        $dataset = new CsvDataset('E:\bbc-reverse.csv', 1);
        $samples = [];
        $i = 0;
        foreach ($dataset->getSamples() as $sample) {
            if ($i >= $lBound && $i < $uBound) {
                $samples[] = $sample[0];
            }
            $i++;
        }
        $i = 0;
        $actualTargets = [];
        foreach ($dataset->getTargets() as $target) {
            if ($i >= $lBound && $i < $uBound) {
                $actualTargets[] = strtolower($target);
            }
            $i++;
        }

        $classifier = $this;
        $targets = [];

        for ($i = 0; $i < count($samples); $i++) {
            $targets[$i] = $classifier->categorize($samples[$i]);
        }
		for ($i = 0; $i < count($samples); $i++) {
            Log::info($targets[$i].'----'.$actualTargets[$i]);
        }

        return Accuracy::score($actualTargets, $targets);
    }


    /**
     * Identify the category of the provided text parameter.
     *
     * @param string $text
     * @return array
     */

    public function categorize($text)
    {
        $probabilities = $this->probabilities($text);   
         $that = $this;
        $maxProbability = -INF;
        $chosenCategory = null;
 
            // iterate thru our categories to find the one with max probability
            // for this text
            foreach ($probabilities as $category => $logProbability) {
                if ($logProbability > $maxProbability) {
                    $maxProbability = $logProbability;
                    $chosenCategory = $category;
                }
            }
            return $chosenCategory;
    }

    /**
     * Build a frequency hashmap where
     *  - the keys are the entries in `tokens`
     *  - the values are the frequency of each entry in `tokens`
     *
     * @param array $tokens array of string
     * @return array hashmap of token frequency
     */
    public static function frequencyTable($tokens)
    {
        $frequencyTable = [];
        foreach ($tokens as $token) {
            if (!isset($frequencyTable[$token])) {
                $frequencyTable[$token] = 1;
            } else {
                $frequencyTable[$token]++;
            }
        }
        return $frequencyTable;
    }

    /**
     * Teach your classifier
     * @param string $text
     * @param string $category
     * @return void
     */
    public static function learn($text, $category)
    {
        $text = strtolower($text);
        $category = strtolower($category);

        $document = ['document' => $text, 'category' => $category];
        self::$docCount++;
        self::$documents[] = $document;
        // normalize the text into a word array
        $tokens = self::tokenizer($text);

        // get a frequency count for each token in the text
        $frequencyTable = self::frequencyTable($tokens);

        // Update vocabulary and word frequency count for this category
        foreach ($frequencyTable as $token => $frequencyInText) {
            if (!isset($that->wordFrequencyCount[$category][$token])) {
                self::$wordFrequencyCount[$category][$token] = $frequencyInText;
            } else {
                self::$wordFrequencyCount[$category][$token] += $frequencyInText;
            }
            // update the count of all words we have seen mapped to this category
//            self::$wordCount[$category] += $frequencyInText;
        }

    }

    /**
     * Extract the probabilities for each known category
     * @param string $text
     * @return array  probabilities by category or null
     */
    public function probabilities($text)
    {
        $probabilities = [];
        if (self::$totalDocuments > 0) {
            $tokens = self::tokenizer($text);
            $frequencyTable = self::frequencyTable($tokens);
            // for this text iterate thru our categories to find the one with max probability
            foreach (self::$categories as $category => $value) {
                $category = strtolower($category);
                $categoryProbability = self::$docCount[$category] / self::$totalDocuments;
                $logProbability = log($categoryProbability);
                foreach ($frequencyTable as $token => $frequencyInText) {
                    $token = strtolower($token);
                    $tokenProbability = $this->tokenProbability($token, $category);
                    // determine the log of the P( w | c ) for this word
                    $logProbability += $frequencyInText * log($tokenProbability);
                }
                $probabilities[$category] = $logProbability;
            }
        }
        return $probabilities;
    }

    public static function initStopWords()
    {
        self::$stopWords = [];
        $fileContents = explode("\n", file_get_contents("C:\proo\proo\atire_puurula.txt"));
        self::$stopWords = $fileContents;
    }

    /**
     * Calculate the probability that a `token` belongs to a `category`
     *
     * @param string $token
     * @param string $category
     * @return number the probability
     */
    public function tokenProbability($token, $category)
    {
        // how many times this word has occurred in documents mapped to this category
        $wordFrequencyCount = 0;
        if (isset(self::$wordFrequencyCount[$category][$token])) {
            $wordFrequencyCount = self::$wordFrequencyCount[$category][$token];
        }
        // what is the count of all words that have ever been mapped to this category
        $wordCount = self::$wordCount[$category];
        // use laplace Add-1 Smoothing equation
        return ($wordFrequencyCount + 1) / ($wordCount + self::$vocabularySize);
    }
}
