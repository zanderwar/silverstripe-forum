<?php

namespace SilverStripe\Forum\Search;

use SilverStripe\Core\Object;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Page\ForumPage;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SphinxSearch;

/**
 * An extension to the default Forum search to use the {@link Sphinx} class instead
 * of the standard database search.
 *
 * To use Sphinx instead of the built in Search is use:
 *
 * ``ForumHolder::setSearchEngine('Sphinx');``
 *
 * @todo Currently this does not index or search forum Titles...
 *
 * @package forum
 */

class ForumSphinxSearch implements ForumSearchProvider
{
    
    private $searchCache = array();
    
    // These are classes that *may* be indexed by Sphinx. If they are,
    // we can search for them, and we may need to add extra sphinx
    // properties to them.
    protected static $extraSearchClasses = array(ForumPage::class, Member::class);

    /**
     * Get the results
     *
     * @return DataList
     */
    public function getResults($forumHolderID, $query, $order, $offset = 0, $limit = 10)
    {
        $query = $this->cleanQuery($query);

        // Default weights put title ahead of content, which effectively
        // puts threads ahead of posts.
        $fieldWeights = array("Title" => 5, "Content" => 1);

        // Work out what sorting method
        switch ($order) {
            case 'date':
                $mode = 'fields';
                
                $sortArg = array('Created' => 'DESC');
                break;
            case 'title':
                $mode = 'fields';
                
                $sortArg = array('Title' => 'ASC');
                break;
            default:
                // Sort by relevancy, but add the calculated age band,
                // which will push up more recent content.
                $mode = 'eval';
                $sortArg = "@relevance + _ageband";

                // Downgrade the title weighting, which will give more
                // emphasis to age.
                $fieldWeights = array("Title" => 1, "Content" => 1);

                break;
        }
        
        $cachekey = $query.':'.$offset;
        if (!isset($this->searchCache[$cachekey])) {
            // Determine the classes to search. This always include
            // ForumThread and Post, since we decorated them. It also
            // includes Forum and Member if they are decorated, as
            // appropriate.
            $classes = array('ForumThread', 'Post');
            foreach (self::$extraSearchClasses as $c) {
                if (Object::has_extension($c, 'SphinxSearchable')) {
                    $classes[] = $c;
                }
            }

            $this->searchCache[$cachekey] = \SphinxSearch::search($classes, $query, array(
                'start'             => $offset,
                'pagesize'      => $limit,
                'sortmode'      => $mode,
                'sortArg'       => $sortArg,
                'field_weights'     => $fieldWeights
            ));
        }
        
        return $this->searchCache[$cachekey]->Matches;
    }

    // Clean up the query text with some combinatiosn that are known to
    // cause problems for sphinx, including:
    // - term starts with $
    // - presence of /, ^, @, !, (, )
    // we just remove the chars when we see these
    public function cleanQuery($query)
    {
        $query = trim($query);
        if (!$query) {
            return $query;
        }
        if ($query[0] == "$") {
            $query = substr($query, 1);
        }
        $query = str_replace(
            array("/", "^", "@", "!", "(", ")", "~"),
            array("",  "",  "",  "",  "",  "",  ""),
            $query
        );
        return $query;
    }

    public function load()
    {
        // Add the SphinxSearchable extension to ForumThread and Post,
        // with an extra computed column that gives an age band. The
        // age bands are based on Created, as follows:
        // _ageband = 10		where object is <30 days old
        // _ageband = 9			where object is 30-90 days old
        // _ageband = 8			where object is 90-180 days old
        // _ageband = 7			where object is 180 days to 1 year old
        // _ageband = 6			older than one year.
        // The age band is calculated so that when added to @relevancy,
        // it can be sorted. This calculation is valid for data that
        // ages like Post and ForumThread, but not for other classes
        // we can search, like Member and Forum. In those cases,
        // we still have to add the extra field _ageband, but we set it
        // to 10 so it's sorted like it's recent.
        ForumThread::add_extension('SphinxSearchable');
        
        // todo I don't know what set_static has been replaced with
        Object::set_static("ForumThread", "sphinx", array(
            "extra_fields" => array("_ageband" => "if(datediff(now(),LastEdited)<30,10,if(datediff(now(),LastEdited)<90,9,if(datediff(now(),LastEdited)<180,8,if(datediff(now(),LastEdited)<365,7,6))))")
        ));
        DataObject::add_extension('Post', 'SphinxSearchable');
        Object::set_static("Post", "sphinx", array(
            "extra_fields" => array("_ageband" => "if(datediff(now(),Created)<30,10,if(datediff(now(),Created)<90,9,if(datediff(now(),Created)<180,8,if(datediff(now(),Created)<365,7,6))))")
        ));

        // For classes that might be indexed, add the extra field if they
        // are decorated with SphinxSearchable.
        foreach (self::$extraSearchClasses as $class) {
            if (Object::has_extension($class, 'SphinxSearchable')) {
                // todo I don't know what uninherited_static has been replaced with
                $conf = Object::uninherited_static($class, "sphinx");
                if (!$conf) {
                    $conf = array();
                }
                if (!isset($conf['extra_fields'])) {
                    $conf['extra_fields'] = array();
                }
                $conf['extra_fields']['_ageband'] = "10";
                Object::set_static($class, "sphinx", $conf);
            }
        }
    }
}
