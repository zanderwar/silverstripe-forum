<?php

namespace SilverStripe\Forum\Page;

use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Forum\Page\ForumHolderPage;
use SilverStripe\Forum\Search\ForumSearch;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;

/**
 * Class ForumHolderController
 *
 * @package SilverStripe\Forum\Pages
 */
class ForumHolderPageController extends \PageController
{
    /** @var array */
    private static $allowed_actions = array(
        'popularthreads',
        'login',
        'logout',
        'search',
        'rss',
    );

    /**
     * Add forum requirements
     *
     * @return void
     */
    protected function init()
    {
        parent::init();

        Requirements::javascript(ADMIN_THIRDPARTY_DIR . "/jquery/jquery.js");
        Requirements::javascript(FORUM_DIR . "/javascript/jquery.MultiFile.js");
        Requirements::javascript(FORUM_DIR . "/javascript/forum.js");

        Requirements::themedCSS('Forum.css');

        RSSFeed::linkToFeed($this->Link("rss"), _t('ForumHolder.POSTSTOALLFORUMS', "Posts to all forums"));

        // Set the back url
        if (isset($_SERVER['REQUEST_URI'])) {
            Session::set('BackURL', $_SERVER['REQUEST_URI']);
        }

        Session::set('BackURL', $this->Link());
    }

    /**
     * Generate a complete list of all the members data. Return a
     * set of all these members sorted by a GET variable
     *
     * @todo Sort via AJAX
     * @return DataList A DataObjectSet of all the members which are signed up
     */
    public function memberlist()
    {
        return $this->httpError(404); // todo ???

        $forumGroupID = (int)Group::get()->filter(['Code' => 'forum-members'])->first()->ID;

        // If sort has been defined then save it as in the session
        $order = (isset($_GET['order'])) ? $_GET['order'] : "";

        if (!isset($_GET['start']) || !is_numeric($_GET['start']) || (int)$_GET['start'] < 1) {
            $_GET['start'] = 0;
        }

        $SQL_start = (int)$_GET['start'];

        switch ($order) {
            case "joined":
                //$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Created\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
                $members = Member::get()
                    ->filter('Member.GroupID', $forumGroupID)
                    ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                    ->sort('"Member"."Created" ASC')
                    ->limit($SQL_start . ',100');
            break;
            case "name":
                //$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Nickname\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
                $members = Member::get()
                    ->filter('Member.GroupID', $forumGroupID)
                    ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                    ->sort('"Member"."Nickname" ASC')
                    ->limit($SQL_start . ',100');
            break;
            case "country":
                //$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID' AND \"Member\".\"CountryPublic\" = TRUE", "\"Member\".\"Country\" ASC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
                $members = Member::get()
                    ->filter(array('Member.GroupID' => $forumGroupID, 'Member.CountryPublic' => true))
                    ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                    ->sort('"Member"."Nickname" ASC')
                    ->limit($SQL_start . ',100');
            break;
            case "posts":
                $query           = singleton('SilverStripe\\Security\\Member')->extendedSQL('', "\"NumPosts\" DESC", "{$SQL_start},100");
                $query->select[] = "(SELECT COUNT(*) FROM \"Post\" WHERE \"Post\".\"AuthorID\" = \"Member\".\"ID\") AS \"NumPosts\"";
                $records         = $query->execute();
                $members         = singleton('SilverStripe\\Security\\Member')->buildDataObjectSet($records, 'DataObjectSet', $query, 'SilverStripe\\Security\\Member');
                $members->parseQueryLimit($query);
            break;
            default:
                //$members = DataObject::get("Member", "\"GroupID\" = '$forumGroupID'", "\"Member\".\"Created\" DESC", "LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"", "{$SQL_start},100");
                $members = Member::get()
                    ->filter('Member.GroupID', $forumGroupID)
                    ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                    ->sort('"Member"."Created" DESC')
                    ->limit($SQL_start . ',100');
            break;
        }

        return array(
            'Subtitle' => _t('ForumHolder.MEMBERLIST', 'Forum member List'),
            'Abstract' => $this->MemberListAbstract,
            'Members'  => $members,
            'Title'    => _t('ForumHolder.MEMBERLIST', 'Forum member List')
        );
    }

    /**
     * Show the 20 most popular threads across all {@link Forum} children.
     *
     * Two configuration options are available:
     * 1. "posts" - most popular threads by posts
     * 2. "views" - most popular threads by views
     *
     * e.g. mysite.com/forums/popularthreads?by=posts
     *
     * @return array
     */
    public function popularthreads()
    {
        $threads = null;
        $start   = isset($_GET['start']) ? (int)$_GET['start'] : 0;
        $limit   = 20;
        $method  = isset($_GET['by']) ? $_GET['by'] : null;
        if (!$method) {
            $method = 'posts';
        }

        if ($method == 'posts') {
            $threadsQuery            = singleton(ForumThread::class)->buildSQL(
                "\"SiteTree\".\"ParentID\" = '" . $this->ID . "'",
                "\"PostCount\" DESC",
                "$start,$limit",
                "LEFT JOIN \"Post\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\" LEFT JOIN \"SiteTree\" ON \"SiteTree\".\"ID\" = \"ForumThread\".\"ForumID\""
            );
            $threadsQuery->select[]  = "COUNT(\"Post\".\"ID\") AS 'PostCount'";
            $threadsQuery->groupby[] = "\"ForumThread\".\"ID\"";
            $threads                 = singleton(ForumThread::class)->buildDataObjectSet($threadsQuery->execute());
            if ($threads) {
                $threads->setPageLimits($start, $limit, $threadsQuery->unlimitedRowCount());
            }
        } elseif ($method == 'views') {
            $threads = DataObject::get(ForumThread::class, '', "\"NumViews\" DESC", '', "$start,$limit");
        }

        return array(
            'Title'    => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Subtitle' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Method'   => $method,
            'Threads'  => $threads
        );
    }

    /**
     * The login action
     *
     * It simply sets the return URL and forwards to the standard login form.
     *
     * @return HTTPResponse
     */
    public function login()
    {
        Session::set('Security.Message.message', _t('Forum.CREDENTIALS'));
        Session::set('Security.Message.type', 'status');
        Session::set("BackURL", $this->Link());

        return $this->redirect('Security/login');
    }

    /**
     * @return HTTPResponse
     */
    public function logout()
    {
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        return $this->redirect($this->Link());
    }

    /**
     * The search action
     *
     * @param  HTTPRequest $request
     * @return array Returns an array to render the search results.
     */
    public function search(HTTPRequest $request)
    {
        $keywords = $request->param('Search') ? Convert::raw2xml($request->param('Search')) : null;
        $order    = $request->param('order') ? Convert::raw2xml($request->param('order')) : null;
        $start    = $request->param('start') ? (int)$request->param('start') : 0;

        $abstract = ($keywords)
            ? "<p>" . _t('ForumHolder.SEARCHEDFOR', "You searched for '{keywords}'.", ['keywords' => $keywords]) . "</p>"
            : null;

        // get the results of the query from the current search engine
        $search = ForumSearch::getSearchEngine();

        if ($search) {
            $engine = new $search();

            $results = $engine->getResults($this->ID, $keywords, $order, $start);
        } else {
            $results = false;
        }

        //Paginate the results
        $results = PaginatedList::create(
            $results,
            $this->request->getVars()
        );

        // if the user has requested this search as an RSS feed then output the contents as xml
        // rather than passing it to the template
        if (isset($_REQUEST['rss'])) {
            $rss = new RSSFeed($results, $this->Link(), _t('ForumHolder.SEARCHRESULTS', 'Search results'), "", "Title", "RSSContent", "RSSAuthor");

            return $rss->outputToBrowser();
        }

        // attach a link to a RSS feed version of the search results
        $rssLink = $this->Link() . "search/?Search=" . urlencode($keywords) . "&amp;order=" . urlencode($order) . "&amp;rss";
        RSSFeed::linkToFeed($rssLink, _t('ForumHolder.SEARCHRESULTS', 'Search results'));

        return array(
            "Subtitle"      => DBField::create_field('Text', _t('ForumHolder.SEARCHRESULTS', 'Search results')),
            "Abstract"      => DBField::create_field('HTMLText', $abstract),
            "Query"         => DBField::create_field('Text', $request->param('Search')),
            "Order"         => DBField::create_field('Text', ($order) ? $order : "relevance"),
            "RSSLink"       => DBField::create_field('HTMLText', $rssLink),
            "SearchResults" => $results
        );
    }

    /**
     * Get the RSS feed
     *
     * This method will output the RSS feed with the last 50 posts to the
     * browser.
     */
    public function rss()
    {
        HTTP::set_cache_age(3600); // cache for one hour

        $threadID = null;
        $forumID  = null;

        // optionally allow filtering of the forum posts by the url in the format
        // rss/thread/$ID or rss/forum/$ID
        if (isset($this->urlParams['ID']) && ($action = $this->urlParams['ID'])) {
            if (isset($this->urlParams['OtherID']) && ($id = $this->urlParams['OtherID'])) {
                switch ($action) {
                    case 'forum':
                        $forumID = (int)$id;
                    break;
                    case 'thread':
                        $threadID = (int)$id;
                }
            } else {
                // fallback is that it is the ID of a forum like it was in
                // previous versions
                $forumID = (int)$action;
            }
        }

        $data = array('last_created' => null, 'last_id' => null);

        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            // just to get the version data..
            $available = ForumHolderPage::newPostsAvailable($this->ID, $data, null, null, $forumID, $threadID);

            // No information provided by the client, just return the last posts
            $rss = new RSSFeed(
                $this->getRecentPosts(50, $forumID, $threadID),
                $this->Link() . 'rss',
                _t('Forum.RSSFORUMPOSTSTO', ['title' => $this->Title]),
                "",
                "Title",
                "RSSContent",
                "RSSAuthor",
                $data['last_created'],
                $data['last_id']
            );

            return $rss->outputToBrowser();
        } else {
            // Return only new posts, check the request headers!
            $since = null;
            $etag  = null;

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                // Split the If-Modified-Since (Netscape < v6 gets this wrong)
                $since = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
                // Turn the client request If-Modified-Since into a timestamp
                $since = @strtotime($since[0]);
                if (!$since) {
                    $since = null;
                }
            }

            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && is_numeric($_SERVER['HTTP_IF_NONE_MATCH'])) {
                $etag = (int)$_SERVER['HTTP_IF_NONE_MATCH'];
            }
            if ($available = ForumHolderPage::newPostsAvailable($this->ID, $data, $since, $etag, $forumID, $threadID)) {
                HTTP::register_modification_timestamp($data['last_created']);
                $rss = new RSSFeed(
                    $this->getRecentPosts(50, $forumID, $threadID, $etag),
                    $this->Link() . 'rss',
                    _t('Forum.RSSFORUMPOSTSTO', ['title' => $this->Title]),
                    "",
                    "Title",
                    "RSSContent",
                    "RSSAuthor",
                    $data['last_created'],
                    $data['last_id']
                );

                return $rss->outputToBrowser();
            } else {
                if ($data['last_created']) {
                    HTTP::register_modification_timestamp($data['last_created']);
                }

                if ($data['last_id']) {
                    HTTP::register_etag($data['last_id']);
                }

                // There are no new posts, just output an "304 Not Modified" message
                HTTP::add_cache_headers();
                header('HTTP/1.1 304 Not Modified');
            }
        }
        exit;
    }

    /**
     * Return the GlobalAnnouncements from the individual forums
     *
     * @return DataList
     */
    public function GlobalAnnouncements()
    {
        //dump(ForumHolderPage::baseForumTable());

        // Get all the forums with global sticky threads
        return ForumThread::get()
            ->filter('IsGlobalSticky', 1)
            ->innerJoin(ForumHolderPage::baseForumTable(), '"ForumThread"."ForumID"="ForumPage"."ID"', "ForumPage")
            ->where('"ForumPage"."ParentID" = ' . $this->ID)
            ->filterByCallback(
                function ($thread) {
                    /** @var ForumThread $thread */
                    if ($thread->canView()) {
                        $post         = Post::get()->filter('ThreadID', $thread->ID)->sort('Post.Created DESC');
                        $thread->Post = $post;

                        return true;
                    }

                    return false;
                }
            );
    }
}
