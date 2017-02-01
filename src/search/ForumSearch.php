<?php

namespace SilverStripe\Forum\Search;

/**
 * Forum Search.
 *
 * Wrapper for providing search functionality
 *
 * @package forum
 */

class ForumSearch
{
    /**
     * The search class engine to use for the forum. By default use the standard
     * Database Search but optionally allow other search engines. Must implement
     * the {@link ForumSearch} interface.
     *
     * @var String
     */
    private static $searchEngine = 'ForumDatabaseSearch';

    /**
     * Set the search class to use for the Forum search. Must implement the
     * {@link ForumSearch} interface
     *
     * @param String
     *
     * @return mixed The result of load() on the engine
     */
    public static function setSearchEngine($engine)
    {
        if (!$engine) {
            $engine = 'ForumDatabaseSearch';
        }

        $search = new $engine();

        if (!$search instanceof ForumSearchProvider) {
            user_error("$engine must implement the ForumSearchProvider interface", E_USER_ERROR);
        }
        
        self::$searchEngine = $engine;

        return $search->load();
    }

    /**
     * Return the search class for the forum search
     *
     * @return String
     */
    public static function getSearchEngine()
    {
        return self::$searchEngine;
    }
}
