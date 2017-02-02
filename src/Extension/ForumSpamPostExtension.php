<?php

namespace SilverStripe\Forum\Extension;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataExtension;

class ForumSpamPostExtension extends DataExtension
{

    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (Config::inst()->forClass('Post')->allow_reading_spam) {
            return;
        }

        /** @var Member $member */
        $member = Member::currentUser();
        $forum = $this->owner->Forum();

        // Do Status filtering

        if ($member && is_numeric($forum->ID) && $member->ID == $forum->Moderator()->ID) {
            $filter = "\"Post\".\"Status\" IN ('Moderated', 'Awaiting')";
        } else {
            $filter = "\"Post\".\"Status\" = 'Moderated'";
        }

        $query->addWhere($filter);

        // Exclude Ghost member posts, but show Ghost members their own posts
        $authorStatusFilter = '"AuthorID" IN (SELECT "ID" FROM "Member" WHERE "ForumStatus" = \'Normal\')';
        if ($member && $member->ForumStatus == 'Ghost') {
            $authorStatusFilter .= ' OR "AuthorID" = ' . $member->ID;
        }

        $query->addWhere($authorStatusFilter);

        $query->setDistinct(false);
    }
}
