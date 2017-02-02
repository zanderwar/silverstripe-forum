<?php
namespace SilverStripe\Forum\Report;

use SilverStripe\Forum\Model\Post;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
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
        $postTable = DataObject::singleton()->getSchema()->tableName(Post::class);
        $postsQuery = new SQLSelect();
        $postsQuery->setFrom('"' . $postTable . '"');
        $postsQuery->setSelect(array(
            'Month' => DB::get_conn()->formattedDatetimeClause('"Created"', '%Y-%m'),
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
