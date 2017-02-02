<?php
namespace SilverStripe\Forum\Report;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\Reports\Report;
use SilverStripe\View\ArrayData;

/**
 * Member Posts Report.
 * Lists the Number of Posts made in the forums in the past months categorized
 * by month.
 */
class ForumMonthlyPostsReport extends Report
{
    /**
     * @return string
     */
    public function title()
    {
        return _t('Forum.FORUMMONTHLYPOSTS', 'Forum Posts by Month');
    }

    /**
     * @param array $params
     * @todo
     * @return static
     */
    public function sourceRecords($params = array())
    {
        $postsQuery = new SQLQuery();
        $postsQuery->setFrom('"Post"');
        $postsQuery->setSelect(array(
            'Month' => DB::getConn()->formattedDatetimeClause('"Created"', '%Y-%m'),
            'Posts' => 'COUNT("Created")'
        ));
        $postsQuery->setGroupBy('"Month"');
        $postsQuery->setOrderBy('"Month"', 'DESC');
        $posts = $postsQuery->execute();

        $output = ArrayList::create();
        foreach ($posts as $post) {
            $post['Month'] = date('Y F', strtotime($post['Month']));
            $output->add(ArrayData::create($post));
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
            'Posts' => 'Posts'
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
