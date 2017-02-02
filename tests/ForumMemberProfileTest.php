<?php

namespace SilverStripe\Forum\Tests;


use SilverStripe\Forum\Page\ForumHolderPage;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;


class ForumMemberProfileTest extends FunctionalTest
{

    static $fixtureFile = "forum/tests/ForumTest.yml";
    static $useDraftSite = true;

    public function testRegistrationWithHoneyPot()
    {
        $origHoneypot = ForumHolderPage::$useHoneypotOnRegister;
        $origSpamprotection = ForumHolderPage::$useSpamProtectionOnRegister;

        ForumHolderPage::$useSpamProtectionOnRegister = false;

        ForumHolderPage::$useHoneypotOnRegister = false;
        $response = $this->get('ForumMemberProfile/register');
        $this->assertNotContains('RegistrationForm_username', $response->getBody(), 'Honeypot is disabled by default');

        ForumHolderPage::$useHoneypotOnRegister = true;
        $response = $this->get('ForumMemberProfile/register');
        $this->assertContains('RegistrationForm_username', $response->getBody(), 'Honeypot can be enabled');

        // TODO Will fail if Member is decorated with further *required* fields,
        // through updateForumFields() or updateForumValidator()
        $baseData = array(
            'Password' => array(
                '_Password' => 'text',
                '_ConfirmPassword' => 'text'
            ),
            "Nickname" => 'test',
            "Email" => 'test@test.com',
        );

        $invalidData = array_merge($baseData, array('action_doregister' => 1, 'username' => 'spamtastic'));
        $response = $this->post('ForumMemberProfile/RegistrationForm', $invalidData);
        $this->assertEquals(403, $response->getStatusCode());

        $validData = array_merge($baseData, array('action_doregister' => 1));
        $response = $this->post('ForumMemberProfile/RegistrationForm', $validData);
        // Weak check (registration might still fail), but good enough to know if the honeypot is working
        $this->assertEquals(200, $response->getStatusCode());

        ForumHolderPage::$useHoneypotOnRegister = $origHoneypot;
        ForumHolderPage::$useSpamProtectionOnRegister = $origSpamprotection;
    }

    public function testMemberProfileSuspensionNote()
    {
        DBDatetime::set_mock_now('2011-10-10');

        $normalMember = $this->objFromFixture(Member::class, 'test1');
        $this->loginAs($normalMember);
        $response = $this->get('ForumMemberProfile/edit/' . $normalMember->ID);

        $this->assertNotContains(
            _t('ForumRole.SUSPENSIONNOTE'),
            $response->getBody(),
            'Normal profiles don\'t show suspension note'
        );

        $suspendedMember = $this->objFromFixture(Member::class, 'suspended');
        $this->loginAs($suspendedMember);
        $response = $this->get('ForumMemberProfile/edit/' . $suspendedMember->ID);
        $this->assertContains(
            _t('ForumRole.SUSPENSIONNOTE'),
            $response->getBody(),
            'Suspended profiles show suspension note'
        );

        DBDatetime::clear_mock_now();
    }

    public function testMemberProfileDisplays()
    {
        /* Get the profile of a secretive member */
        $this->get('ForumMemberProfile/show/' . $this->idFromFixture(Member::class, 'test1'));

        /* Check that it just contains the bare minimum

		Commented out by wrossiter since this was breaking with custom themes. A test like this should not fail
		because of a custom theme. Will reenable these tests when we tackle the new Member functionality

		$this->assertExactMatchBySelector("div#UserProfile label", array(
			"Nickname:",
			"Number of posts:",
			"Forum ranking:",
			"Avatar:",
		));
		$this->assertExactMatchBySelector("div#UserProfile p", array(
			'test1',
			'5',
			'n00b',
			'',
		));

		/* Get the profile of a public member */
        $this->get('ForumMemberProfile/show/' . $this->idFromFixture(Member::class, 'test2'));

        /* Check that it just contains everything

		$this->assertExactMatchBySelector("div#UserProfile label", array(
			"Nickname:",
			'First Name:',
			'Surname:',
			'Email:',
			'Occupation:',
			'Country:',
			'Number of posts:',
			'Forum ranking:',
			'Avatar:'
		));
		$this->assertExactMatchBySelector("div#UserProfile p", array(
			'test2',
			'Test',
			'Two',
			'',
			'OtherUser',
			'Australia',
			'8',
			'l33t',
			'',
		));
		 */
    }
}
