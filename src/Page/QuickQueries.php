<?php

namespace SilverStripe\Forum\Page;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Security\Member;
use SilverStripe\ORM\Queries\SQLSelect;

trait QuickQueries
{
    /**
     * A centralized method to return a SQLSelect query for retrieving some statistics about forum posts by member
     *
     * @param  string $select
     * @param  bool   $joinMember
     * @param  bool   $matchParent
     * @return mixed
     */
    protected function getNumQuery($select, $joinMember = true, $matchParent = true)
    {
        $schema = $this->getSchema();
        $postTable = $schema->tableName(Post::class);
        $memberTable = $schema->tableName(Member::class);
        $siteTreeTable = $schema->tableName(SiteTree::class);

        $query = (new SQLSelect)
            ->setFrom('"' . $postTable . '"')
            ->setSelect($select);

        if ($joinMember) {
            $query
                ->addInnerJoin($memberTable, '"' . $postTable . '"."AuthorID" = "' . $memberTable . '"."ID"')
                ->addWhere('"' . $memberTable . '"."ForumStatus" = \'Normal\'');
        }
        if ($matchParent) {
            $query
                ->addInnerJoin($siteTreeTable, '"' . $postTable . '"."ForumID" = "' . $siteTreeTable . '"."ID"')
                ->addWhere('"' . $siteTreeTable . '"."ParentID" = ' . $this->ID);
        } else {
            $query->addWhere('"ForumID" = ' . $this->ID);
        }

        return $query->execute()->value();
    }
}
