<?php

/**
 * Model for keywords
 *
 * Class Wpil_Model_Keyword
 */
class Wpil_Model_Keyword
{
    public $post_id;
    public $post_type;
    public $keyword_type;
    public $keywords;
    public $stemmed;
    public $checked;
    public $impressions;
    public $clicks;
    public $word_count;

    public function __construct($params = [])
    {
        //fill model properties from initial array
        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                switch($key){
                    case 'keywords':
                        // if the current item is the keywords, save the keywords
                        $this->{$key} = $value;
                        // save the stemmed version of the keywords
                        $stemmed = trim(Wpil_Word::getStemmedSentence($value));
                        $this->stemmed = (!empty($stemmed)) ? $stemmed: $value;
                        // and save the word count
                        $words = explode(' ', $value);
                        $this->word_count = count($words);
                    break;
                    default:
                    // for everything else, there's saving
                    $this->{$key} = $value;
                }
            }
        }
    }
}