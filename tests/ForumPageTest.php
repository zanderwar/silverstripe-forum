<?php

namespace SilverStripe\Forum\Tests;

use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Forum\Controller\ForumPageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataModel;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Forum\Page\ForumPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\FunctionalTest;

/**
 * @todo Write Tests for doPostMessageForm()
 */
class ForumPageTest extends FunctionalTest
{
    protected static $fixture_file = "forum/tests/ForumTest.yml";
    protected static $use_draft_site = true;

    public function testCanView()
    {
        // test viewing not logged in
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        $public = $this->objFromFixture(ForumPage::class, 'general');
        $private = $this->objFromFixture(ForumPage::class, 'loggedInOnly');
        $limited = $this->objFromFixture(ForumPage::class, 'limitedToGroup');
        $noposting = $this->objFromFixture(ForumPage::class, 'noPostingForum');
        $inherited = $this->objFromFixture(ForumPage::class, 'inheritedForum');

        $this->assertTrue($public->canView());
        $this->assertFalse($private->canView());
        $this->assertFalse($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // try logging in a member
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'test1');
        $member->logIn();

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertFalse($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $member->logIn();

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertTrue($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertFalse($inherited->canView());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $member->logIn();

        $this->assertTrue($public->canView());
        $this->assertTrue($private->canView());
        $this->assertTrue($limited->canView());
        $this->assertTrue($noposting->canView());
        $this->assertTrue($inherited->canView());
    }

    public function testCanPost()
    {
        // test viewing not logged in
        /** @var Member $member */
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        /** @var ForumPage $public */
        $public = $this->objFromFixture(ForumPage::class, 'general');

        /** @var ForumPage $private */
        $private = $this->objFromFixture(ForumPage::class, 'loggedInOnly');

        /** @var ForumPage $limited */
        $limited = $this->objFromFixture(ForumPage::class, 'limitedToGroup');

        /** @var ForumPage $noposting */
        $noposting = $this->objFromFixture(ForumPage::class, 'noPostingForum');

        /** @var ForumPage $inherited */
        $inherited = $this->objFromFixture(ForumPage::class, 'inheritedForum');

        $this->assertTrue($public->canPost());
        $this->assertFalse($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // try logging in a member
        $member = $this->objFromFixture(Member::class, 'test1');
        $member->logIn();

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $member->logIn();

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertTrue($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $member->logIn();

        $this->assertTrue($public->canPost());
        $this->assertTrue($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($noposting->canPost());
        $this->assertFalse($inherited->canPost());
    }

    public function testSuspended()
    {
        /** @var ForumPage $private */
        $private = $this->objFromFixture(ForumPage::class, 'loggedInOnly');

        /** @var ForumPage $limited */
        $limited = $this->objFromFixture(ForumPage::class, 'limitedToGroup');

        /** @var ForumPage $inheritedForum_loggedInOnly */
        $inheritedForum_loggedInOnly = $this->objFromFixture(ForumPage::class, 'inheritedForum_loggedInOnly');
        DBDatetime::set_mock_now('2011-10-10 12:00:00');

        // try logging in a member suspendedexpired
        /** @var Member $suspendedexpired */
        $suspendedexpired = $this->objFromFixture(Member::class, 'suspendedexpired');
        $this->assertFalse($suspendedexpired->IsSuspended());
        $suspendedexpired->logIn();
        $this->assertTrue($private->canPost());
        $this->assertTrue($limited->canPost());
        $this->assertTrue($inheritedForum_loggedInOnly->canPost());

        // try logging in a member suspended
        $suspended = $this->objFromFixture(Member::class, 'suspended');
        $this->assertTrue($suspended->IsSuspended());
        $suspended->logIn();
        $this->assertFalse($private->canPost());
        $this->assertFalse($limited->canPost());
        $this->assertFalse($inheritedForum_loggedInOnly->canPost());
    }

    public function testCanModerate()
    {
        // test viewing not logged in
        /** @var Member $member */
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        /** @var ForumPage $public */
        $public = $this->objFromFixture(ForumPage::class, 'general');

        /** @var ForumPage $private */
        $private = $this->objFromFixture(ForumPage::class, 'loggedInOnly');

        /** @var ForumPage $limited */
        $limited = $this->objFromFixture(ForumPage::class, 'limitedToGroup');

        /** @var ForumPage $noposting */
        $noposting = $this->objFromFixture(ForumPage::class, 'noPostingForum');

        /** @var ForumPage $inherited */
        $inherited = $this->objFromFixture(ForumPage::class, 'inheritedForum');

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // try logging in a member
        $member = $this->objFromFixture(Member::class, 'test1');
        $member->logIn();

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // login as a person with access to restricted forum
        $member = $this->objFromFixture(Member::class, 'test2');
        $member->logIn();

        $this->assertFalse($public->canModerate());
        $this->assertFalse($private->canModerate());
        $this->assertFalse($limited->canModerate());
        $this->assertFalse($noposting->canModerate());
        $this->assertFalse($inherited->canModerate());

        // Moderator should be able to view his own forums
        $member = $this->objFromFixture(Member::class, 'moderator');
        $member->logIn();

        $this->assertTrue($public->canModerate());
        $this->assertTrue($private->canModerate());
        $this->assertTrue($limited->canModerate());
        $this->assertTrue($noposting->canModerate());
        $this->assertTrue($inherited->canModerate());
    }

    public function testCanAttach()
    {
        /** @var ForumPage $canAttach */
        $canAttach = $this->objFromFixture(ForumPage::class, 'general');
        $this->assertTrue($canAttach->canAttach());

        /** @var ForumPage $noAttach */
        $noAttach = $this->objFromFixture(ForumPage::class, 'forum1cat2');
        $this->assertFalse($noAttach->canAttach());
    }

    public function testgetForbiddenWords()
    {
        $forum = $this->objFromFixture(ForumPage::class, "general");
        $f_controller = new ForumPageController($forum);
        $this->assertEquals($f_controller->getForbiddenWords(), "shit,fuck");
    }

    public function testfilterLanguage()
    {
        $forum =  $this->objFromFixture(ForumPage::class, "general");
        $f_controller = new ForumPageController($forum);
        $this->assertEquals($f_controller->filterLanguage('shit'), "*");

        $this->assertEquals($f_controller->filterLanguage('shit and fuck'), "* and *");

        $this->assertEquals($f_controller->filterLanguage('hello'), "hello");
    }

    public function testGetStickyTopics()
    {
        /** @var ForumPage $forumWithSticky */
        $forumWithSticky = $this->objFromFixture(ForumPage::class, "general");
        $stickies = $forumWithSticky->getStickyTopics();
        $this->assertEquals($stickies->Count(), '2');

        // TODO: Sorts by Created, which is all equal on all Posts in test, and can't be overridden, so can't rely on order
        //$this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');

        $stickies = $forumWithSticky->getStickyTopics($include_global = false);
        $this->assertEquals($stickies->Count(), '1');
        $this->assertEquals($stickies->First()->Title, 'Sticky Thread');

        /** @var ForumPage $forumWithGlobalOnly */
        $forumWithGlobalOnly = $this->objFromFixture(ForumPage::class, "forum1cat2");
        $stickies = $forumWithGlobalOnly->getStickyTopics();
        $this->assertEquals($stickies->Count(), '1');
        $this->assertEquals($stickies->First()->Title, 'Global Sticky Thread');
        $stickies = $forumWithGlobalOnly->getStickyTopics($include_global = false);
        $this->assertEquals($stickies->Count(), '0');
    }

    public function testTopics()
    {
        /** @var ForumPage $forumWithPosts */
        $forumWithPosts = $this->objFromFixture(ForumPage::class, "general");
        $this->assertEquals($forumWithPosts->getTopics()->Count(), '4');

        /** @var ForumPage $forumWithoutPosts */
        $forumWithoutPosts = $this->objFromFixture(ForumPage::class, "forum1cat2");
        $this->assertNull($forumWithoutPosts->getTopics());
    }

    public function testGetLatestPost()
    {
        /** @var ForumPage $forumWithPosts */
        $forumWithPosts = $this->objFromFixture(ForumPage::class, "general");
        $this->assertEquals($forumWithPosts->getLatestPost()->Content, 'This is the last post to a long thread');

        /** @var ForumPage $forumWithoutPosts */
        $forumWithoutPosts = $this->objFromFixture(ForumPage::class, "forum1cat2");
        $this->assertNull($forumWithoutPosts->getLatestPost());
    }

    public function testGetNumPosts()
    {
        /** @var ForumPage $forumWithPosts */
        $forumWithPosts = $this->objFromFixture(ForumPage::class, "general");
        $this->assertEquals(24, $forumWithPosts->getNumPosts());

        //Mark spammer accounts and retest the posts count
        $this->markGhosts();
        $this->assertEquals(22, $forumWithPosts->getNumPosts());
    }

    public function testGetNumTopics()
    {
        /** @var ForumPage $forumWithPosts */
        $forumWithPosts = $this->objFromFixture(ForumPage::class, "general");
        $this->assertEquals(6, $forumWithPosts->getNumTopics());

        /** @var ForumPage $forumWithoutPosts */
        $forumWithoutPosts = $this->objFromFixture(ForumPage::class, "forum1cat2");
        $this->assertEquals(0, $forumWithoutPosts->getNumTopics());

        //Mark spammer accounts and retest the threads count
        $this->markGhosts();
        $this->assertEquals(5, $forumWithPosts->getNumTopics());
    }

    public function testGetTotalAuthors()
    {
        /** @var ForumPage $forumWithPosts */
        $forumWithPosts = $this->objFromFixture(ForumPage::class, "general");
        $this->assertEquals(4, $forumWithPosts->getNumAuthors());

        /** @var ForumPage $forumWithoutPosts */
        $forumWithoutPosts = $this->objFromFixture(ForumPage::class, "forum1cat2");
        $this->assertEquals(0, $forumWithoutPosts->getNumAuthors());

        //Mark spammer accounts and retest the authors count
        $this->markGhosts();
        $this->assertEquals(2, $forumWithPosts->getNumAuthors());
    }

    protected function markGhosts()
    {
        //Mark a members as a spammers
        $spammer = $this->objFromFixture(Member::class, "spammer");
        $spammer->ForumStatus = 'Ghost';
        $spammer->write();

        $spammer2 = $this->objFromFixture(Member::class, "spammer2");
        $spammer2->ForumStatus = 'Ghost';
        $spammer2->write();
    }

    /**
     * Note: See {@link testCanModerate()} for detailed permission tests.
     */
    public function testMarkAsSpamLink()
    {
        /** @var Post $spampost */
        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();
        $author = $spampost->Author();

        /** @var Member $moderator */
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->MarkAsSpamLink(), 'Link not present by default');

        $c = new ForumPageController($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'markasspam/'. $spampost->ID), DataModel::inst());
        $this->assertEquals(403, $response->getStatusCode());

        // with logged-in moderator
        $moderator->logIn();
        $this->assertNotEquals(false, $spampost->MarkAsSpamLink(), 'Link present for moderators on this forum');

        $this->assertNull($author->SuspendedUntil);

        $c = new ForumPageController($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'markasspam/'. $spampost->ID), DataModel::inst());
        $this->assertFalse($response->isError());

        // removes the post
        $this->assertNull(Post::get()->byID($spampost->ID));

        // suspends the member
        $author = Member::get()->byID($author->ID);
        $this->assertNotNull($author->SuspendedUntil);

        // does not effect the thread
        /** @var ForumThread $thread */
        $thread = ForumThread::get()->byID($spampost->Thread()->ID);
        $this->assertEquals('1', $thread->getNumPosts());

        // mark the first post in that now as spam
        $spamfirst = $this->objFromFixture(Post::class, 'SpamFirstPost');

        $response = $c->handleRequest(new HTTPRequest('GET', 'markasspam/'. $spamfirst->ID), DataModel::inst());

        // removes the thread
        $this->assertNull(ForumThread::get()->byID($spamfirst->Thread()->ID));
    }

    public function testBanLink()
    {
        /** @var Post $spampost */
        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();
        $author = $spampost->Author();

        /** @var Member $moderator */
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->BanLink(), 'Link not present by default');

        $c = ForumPageController::create($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'ban/'. $spampost->AuthorID), DataModel::inst());
        $this->assertEquals(403, $response->getStatusCode());

        // with logged-in moderator
        $moderator->logIn();
        $this->assertNotEquals(false, $spampost->BanLink(), 'Link present for moderators on this forum');

        $c = new ForumPageController($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'ban/'. $spampost->AuthorID), DataModel::inst());
        $this->assertFalse($response->isError());

        // user is banned
        /** @var Member $author */
        $author = Member::get()->byId($author->ID);
        $this->assertTrue($author->IsBanned());
    }

    public function testGhostLink()
    {
        /** @var Post $spampost */
        $spampost = $this->objFromFixture(Post::class, 'SpamSecondPost');
        $forum = $spampost->Forum();
        $author = $spampost->Author();

        /** @var Member $moderator */
        $moderator = $this->objFromFixture(Member::class, 'moderator'); // moderator for "general" forum

        // without a logged-in moderator
        $this->assertFalse($spampost->GhostLink(), 'Link not present by default');

        $c = ForumPageController::create($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'ghost/'. $spampost->AuthorID), DataModel::inst());
        $this->assertEquals(403, $response->getStatusCode());

        // with logged-in moderator
        $moderator->logIn();
        $this->assertNotEquals(false, $spampost->GhostLink(), 'Link present for moderators on this forum');

        $c = ForumPageController::create($forum);
        $response = $c->handleRequest(new HTTPRequest('GET', 'ghost/'. $spampost->AuthorID), DataModel::inst());
        $this->assertFalse($response->isError());

        // post isn't available anymore in normal queries. {@link ForumSpamPostExtension}
        $post = Post::get()->byId($spampost->ID);
        $this->assertNull($post);

        // user is banned
        /** @var Member $author */
        $author = Member::get()->byId($author->ID);
        $this->assertTrue($author->IsGhost());
    }

    public function testNotifyModerators()
    {
        SecurityToken::disable();
        $notifyModerators = ForumPage::$notifyModerators;
        ForumPage::$notifyModerators = true;

        /** @var ForumPage $forum */
        $forum = $this->objFromFixture(ForumPage::class, 'general');
        $controller = ForumPageController::create($forum);

        /** @var Member $user */
        $user = $this->objFromFixture(Member::class, 'test1');
        $this->session()->inst_set('loggedInAs', $user->ID);

        // New thread
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            array(
                'Title' => 'New thread',
                'Content' => 'Meticulously crafted content',
                'action_doPostMessageForm' => 1
            )
        );

        $adminEmail = Config::inst()->get(Email::class, 'admin_email');

        $this->assertEmailSent('test3@example.com', $adminEmail, 'New thread "New thread" in forum [General Discussion]');
        $this->clearEmails();

        // New response
        $thread = DataObject::get_one(ForumThread::class, "\"ForumThread\".\"Title\"='New thread'");
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            array(
                'Title' => 'Re: New thread',
                'Content' => 'Rough response',
                'ThreadID' => $thread->ID,
                'action_doPostMessageForm' => 1
            )
        );
        $this->assertEmailSent('test3@example.com', $adminEmail, 'New post "Re: New thread" in forum [General Discussion]');
        $this->clearEmails();

        // Edit
        $post = $thread->Posts()->Last();
        $this->post(
            $forum->RelativeLink('PostMessageForm'),
            array(
                'Title' => 'Re: New thread',
                'Content' => 'Pleasant response',
                'ThreadID' => $thread->ID,
                'ID' => $post->ID,
                'action_doPostMessageForm' => 1
            )
        );
        $this->assertEmailSent('test3@example.com', $adminEmail, "New post \"Re: New thread\" in forum [General Discussion]");
        $this->clearEmails();

        ForumPage::$notifyModerators = $notifyModerators;
    }

    /**
     * Confirm that when a post is deleted, Member with corresponding ID still exists
     *
     * @throws ValidationException
     * @throws null
     */
    public function testPostDeletionMemberIntegrity()
    {
        $checkID = 100012;

        $post = new Post();
        $post->ID = $checkID;
        $post->write();

        $user = new Member();
        $user->ID = $checkID;
        $user->FirstName = 'TestUser100012';
        $user->write();

        $post->delete();

        $member = DataObject::get_by_id(Member::class, $checkID);
        $this->assertTrue($member->ID == $checkID);
    }
}
