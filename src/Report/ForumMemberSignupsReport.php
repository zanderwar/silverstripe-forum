<?php

namespace SilverStripe\Forum\Report;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Reports\Report;
use SilverStripe\Security\Member;
use SilverStripe\View\ArrayData;

/**
 * Forum Reports.
 * These are some basic reporting tools which sit in the CMS for the user to view.
 * No fancy graphing tools or anything just some simple querys and numbers
 *
 * @package forum
 */

/**
 * Member Signups Report.
 * Lists the Number of people who have signed up in the past months categorized
 * by month.
 */
class ForumMemberSignupsReport extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t('Forum.FORUMSIGNUPS', 'Forum Signups by Month');
    }

    /**
     * @param array $params
     * @todo
     * @return static
     */
    public function sourceRecords($params = array())
    {
        $memberTable = $this->getSchema()->tableName(Member::class);
        $membersQuery = new SQLSelect();
        $membersQuery->setFrom('"' . $memberTable . '"');
        $membersQuery->setSelect(array(
            'Month' => DB::getConn()->formattedDatetimeClause('"Created"', '%Y-%m'),
            'Signups' => 'COUNT("Created")'
        ));
        $membersQuery->setGroupBy('"Month"');
        $membersQuery->setOrderBy('"Month"', 'DESC');
        $members = $membersQuery->execute();

        $output = ArrayList::create();
        foreach ($members as $member) {
            $member['Month'] = date('Y F', strtotime($member['Month']));
            $output->add(ArrayData::create($member));
        }
        return $output;
    }

    /**
     * @return array
     */
    public function columns()
    {
        $fields = array(
            'Month' => 'Month',
            'Signups' => 'Signups'
        );

        return $fields;
    }

    /**
     * @return string
     */
    public function group()
    {
        return 'Forum Reports';
    }
}
