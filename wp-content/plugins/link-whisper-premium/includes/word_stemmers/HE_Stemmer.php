<?php

/**
 * Class Wpil_Stemmer for Hebrew language
 */
class Wpil_Stemmer {

    static $stemmer = null;

    //Hebrew words can not be stemmed
    public static function Stem($word){
        return $word;
    }
}