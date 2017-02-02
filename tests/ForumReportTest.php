<?php

namespace SilverStripe\Forum\Tests;

use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Page\ForumPage;
use SilverStripe\Security\Member;
use SilverStripe\Forum\Report\ForumMemberSignupsReport;
use SilverStripe\Forum\Report\ForumMonthlyPostsReport;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Dev\FunctionalTest;

/**
 * Class ForumReportTest
 * @package SilverStripe\Forum\Tests
 */
class ForumReportTest extends FunctionalTest
{

    protected static $fixtureFile = 'forum/tests/ForumTest.yml';
    protected static $useDraftSite = true;

    public function setUp()
    {
        parent::setUp();

        $member = $this->objFromFixture(Member::class, 'admin');
        $member->logIn();
    }

    public function tearDown()
    {
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        parent::tearDown();
    }

    public function testMemberSignupsReport()
    {
        $r = new ForumMemberSignupsReport();
        $before = $r->records(array());

        // Create a new Member in current month
        $member = new Member();
        $member->Email = 'testMemberSignupsReport';
        $member->write();

        // Ensure the signup count for current month has increased by one
        $this->assertEquals((int)$before->first()->Signups + 1, (int)$r->records(array())->first()->Signups);

        // Move our member to have signed up in April 2015 and check that month's signups
        $member->Created = '2015-04-01 12:00:00';
        $member->write();
        $this->assertEquals(1, $r->records(array())->find('Month', '2015 April')->Signups);

        // We should now be back to our original number of members in current month
        $this->assertEquals((int)$before->first()->Signups, (int)$r->records(array())->first()->Signups);
    }

    public function testMonthlyPostsReport()
    {
        $r = new ForumMonthlyPostsReport();
        $before = $r->records(array());

        // Create a new post in current month
        $post = new Post();
        $post->AuthorID = $this->objFromFixture(Member::class, 'test2')->ID;
        $post->ThreadID = $this->objFromFixture(ForumThread::class, 'Thread2')->ID;
        $post->ForumID = $this->objFromFixture(ForumPage::class, 'forum5')->ID;
        $post->write();

        // Ensure the post count for current month has increased by one
        $this->assertEquals((int)$before->first()->Posts + 1, (int)$r->records(array())->first()->Posts);

        // Move our post to April 2015 and ensure there are two posts (one is specified in fixture file)
        $post->Created = '2015-04-01 12:00:00';
        $post->write();
        $this->assertEquals(2, $r->records(array())->find('Month', '2015 April')->Posts);

        // We should now be back to our original number of posts in current month
        $this->assertEquals((int)$before->first()->Posts, (int)$r->records(array())->first()->Posts);
    }
}
