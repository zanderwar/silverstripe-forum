<?php

namespace SilverStripe\Forum\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Forum\Model\ForumThreadSubscription;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;

/**
 * @todo Write some more complex tests for testing the can*() functionality
 */
class ForumThreadTest extends FunctionalTest
{
    protected static $fixture_file = "ForumTest.yml";

    // fixes permission issues with these tests, we don't need to test versioning anyway.
    // without this, SiteTree::canView() would always return false even though CanViewType == Anyone.
    protected static $use_draft_site = true;

    public function testGetNumPosts()
    {
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals(17, $thread->getNumPosts());
    }

    public function testIncViews()
    {
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        // clear session
        Session::clear('ForumViewed-'.$thread->ID);

        $this->assertEquals($thread->NumViews, '10');

        $thread->incNumViews();

        $this->assertEquals($thread->NumViews, '11');
    }

    public function testGetLatestPost()
    {
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals($thread->getLatestPost()->Content, "This is the last post to a long thread");
    }

    public function testGetFirstPost()
    {
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        $this->assertEquals($thread->getFirstPost()->Content, "This is my first post");
    }

    public function testSubscription()
    {
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, "Thread1");

        /** @var ForumThread $thread2 */
        $thread2 = $this->objFromFixture(ForumThread::class, "Thread2");

        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, "test1");

        /** @var Member $member2 */
        $member2 = $this->objFromFixture(Member::class, "test2");

        $this->assertTrue(ForumThreadSubscription::alreadySubscribed($thread->ID, $member->ID));
        $this->assertTrue(ForumThreadSubscription::alreadySubscribed($thread->ID, $member2->ID));

        $this->assertFalse(ForumThreadSubscription::alreadySubscribed($thread2->ID, $member->ID));
        $this->assertFalse(ForumThreadSubscription::alreadySubscribed($thread2->ID, $member2->ID));
    }

    public function testOnBeforeDelete()
    {
        $thread = new ForumThread();
        $thread->write();

        $post = new Post();
        $post->ThreadID = $thread->ID;
        $post->write();

        $postID = $post->ID;

        $thread->delete();

        $this->assertFalse(Post::get()->byID($postID) instanceof Post);
        $this->assertFalse(Post::get()->byID($thread->ID) instanceof Post);
    }

    public function testPermissions()
    {
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'test1');
        $this->session()->inst_set('loggedInAs', $member->ID);

        // read only thread. No one should be able to post to this (apart from the )
        /** @var ForumThread $readonly */
        $readonly = $this->objFromFixture(ForumThread::class, 'ReadonlyThread');
        $this->assertFalse($readonly->canPost());
        $this->assertTrue($readonly->canView());
        $this->assertFalse($readonly->canModerate());

        // normal thread. They can post to these
        /** @var ForumThread $thread */
        $thread = $this->objFromFixture(ForumThread::class, 'Thread1');
        $this->assertTrue($thread->canPost());
        $this->assertTrue($thread->canView());
        $this->assertFalse($thread->canModerate());

        // normal thread in a read only
        /** @var ForumThread $disabledforum */
        $disabledforum = $this->objFromFixture(ForumThread::class, 'ThreadWhichIsInInheritedForum');
        $this->assertFalse($disabledforum->canPost());
        $this->assertFalse($disabledforum->canView());
        $this->assertFalse($disabledforum->canModerate());

        // Moderator can access threads nevertheless
        /** @var Member $member */
        $member = $this->objFromFixture(Member::class, 'moderator');
        $member->logIn();

        $this->assertFalse($disabledforum->canPost());
        $this->assertTrue($disabledforum->canView());
        $this->assertTrue($disabledforum->canModerate());
    }
}
