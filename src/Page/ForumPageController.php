<?php

namespace SilverStripe\Forum\Page;

use PageController;
use SilverStripe\Assets\Upload;
use SilverStripe\BBCodeParser\BBCodeParser;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Forum\Model\ForumThreadSubscription;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Forum\Model\PostAttachment;
use SilverStripe\Forum\Page\ForumPage;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * The forum controller class
 *
 * @package forum
 */
class ForumPageController extends PageController
{
    /** @var array */
    private static $allowed_actions = array(
        'AdminFormFeatures',
        'deleteattachment',
        'deletepost',
        'editpost',
        'markasspam',
        'PostMessageForm',
        'reply',
        'show',
        'starttopic',
        'subscribe',
        'unsubscribe',
        'rss',
        'ban',
        'ghost'
    );

    /**
     * Add forum requirements
     *
     * @return void
     */
    protected function init()
    {
        parent::init();
        if ($this->redirectedTo()) {
            return;
        }

        Requirements::javascript(ADMIN_THIRDPARTY_DIR . "/jquery/jquery.js");
        Requirements::javascript(FORUM_DIR . "/javascript/Forum.js");
        Requirements::javascript(FORUM_DIR . "/javascript/jquery.MultiFile.js");

        Requirements::themedCSS('Forum.css');

        RSSFeed::linkToFeed(
            $this->Parent()->Link("rss/forum/$this->ID"),
            _t('Forum.RSSFORUM', "Posts to the '{title}' forum", ['title' => $this->Title])
        );
        RSSFeed::linkToFeed(
            $this->Parent()->Link("rss"),
            _t('Forum.RSSFORUMS', 'Posts to all forums')
        );

        if (!$this->canView()) {
            $messageSet = array(
                'default'         => _t('Forum.LOGINDEFAULT', 'Enter your email address and password to view this forum.'),
                'alreadyLoggedIn' => _t('Forum.LOGINALREADY', 'I&rsquo;m sorry, but you can&rsquo;t access this forum until you&rsquo;ve logged in. If you want to log in as someone else, do so below'),
                'logInAgain'      => _t('Forum.LOGINAGAIN', 'You have been logged out of the forums. If you would like to log in again, enter a username and password below.')
            );

            Security::permissionFailure($this, $messageSet);

            return;
        }

        // Log this visit to the ForumMember if they exist
        $member = Member::currentUser();
        if ($member && Config::inst()->get('ForumHolder', 'currently_online_enabled')) {
            $member->LastViewed = date("Y-m-d H:i:s");
            $member->write();
        }

        // Set the back url
        if (isset($_SERVER['REQUEST_URI'])) {
            Session::set('BackURL', $_SERVER['REQUEST_URI']);
        }

        Session::set('BackURL', $this->Link());
    }

    /**
     * A convenience function which provides nice URLs for an rss feed on this forum.
     */
    public function rss()
    {
        $this->redirect($this->Parent()->Link("rss/forum/$this->ID"), 301);
    }

    /**
     * Is OpenID support available?
     *
     * This method checks if the {@link OpenIDAuthenticator} is available and
     * registered.
     *
     * @return bool Returns TRUE if OpenID is available, FALSE otherwise.
     */
    public function OpenIDAvailable()
    {
        return $this->Parent()->OpenIDAvailable();
    }

    /**
     * Subscribe a user to a thread given by an ID.
     *
     * Designed to be called via AJAX so return true / false
     *
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @return bool
     * @throws HTTPResponse_Exception
     */
    public function subscribe(HTTPRequest $request)
    {
        // Check CSRF
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        if (Member::currentUser() && !ForumThreadSubscription::already_subscribed($this->urlParams['ID'])) {
            $sub           = ForumThreadSubscription::create();
            $sub->ThreadID = (int)$this->urlParams['ID'];
            $sub->MemberID = Member::currentUserID();
            $sub->LastSent = date("Y-m-d H:i:s");
            $sub->write();

            die('1');
        }

        return false;
    }

    /**
     * Unsubscribe a user from a thread by an ID
     *
     * Designed to be called via AJAX so return true / false
     *
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @return bool
     */
    public function unsubscribe(HTTPRequest $request)
    {
        $member = Member::currentUser();

        if (!$member) {
            Security::permissionFailure($this, _t('LOGINTOUNSUBSCRIBE', 'To unsubscribe from that thread, please log in first.'));
        }

        if (ForumThreadSubscription::alreadySubscribed($this->urlParams['ID'], $member->ID)) {
            DB::query("
				DELETE FROM \"ForumThread_Subscription\"
				WHERE \"ThreadID\" = '" . Convert::raw2sql($this->urlParams['ID']) . "'
				AND \"MemberID\" = '$member->ID'");

            die('1');
        }

        return false;
    }

    /**
     * Mark a post as spam. Deletes any posts or threads created by that user
     * and removes their user account from the site
     *
     * Must be logged in and have the correct permissions to do marking
     *
     * @param HTTPRequest $request
     *
     * @return bool|HTTPResponse|void
     * @throws HTTPResponse_Exception
     */
    public function markasspam(HTTPRequest $request)
    {
        $currentUser = Member::currentUser();
        if (!isset($this->urlParams['ID'])) {
            return $this->httpError(400);
        }
        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        // Check CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        /** @var Post $post */
        $post = Post::get()->byID($this->urlParams['ID']);
        if ($post) {
            // post was the start of a thread, Delete the whole thing
            if ($post->isFirstPost()) {
                $post->Thread()->delete();
            }

            // Delete the current post
            $post->delete();
            $post->extend('onAfterMarkAsSpam');

            // Log deletion event
            Injector::inst()->get('Logger')->addNotice(sprintf(
                'Marked post #%d as spam, by moderator %s (#%d)',
                $post->ID,
                $currentUser->Email,
                $currentUser->ID
            ));

            // Suspend the member (rather than deleting him),
            // which gives him or a moderator the chance to revoke a decision.
            if ($author = $post->Author()) {
                $author->SuspendedUntil = date('Y-m-d', strtotime('+99 years', DBDatetime::now()->Format('U')));
                $author->write();
            }

            Injector::inst()->get('Logger')->addNotice(sprintf(
                'Suspended member %s (#%d) for spam activity, by moderator %s (#%d)',
                $author->Email,
                $author->ID,
                $currentUser->Email,
                $currentUser->ID
            ));
        }

        return (Director::is_ajax()) ? true : $this->redirect($this->Link());
    }

    /**
     * @param HTTPRequest $r
     *
     * @return bool|HTTPResponse|void
     * @throws HTTPResponse_Exception
     */
    public function ban(HTTPRequest $r)
    {
        if (!$r->param('ID')) {
            return $this->httpError(404);
        }
        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($r->param('ID'));
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Banned';
        $member->write();

        // Log event
        $currentUser = Member::currentUser();
        Injector::inst()->get('Logger')->addNotice(sprintf(
            'Banned member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email,
            $currentUser->ID
        ));

        return ($r->isAjax()) ? true : $this->redirectBack();
    }

    public function ghost(HTTPRequest $r)
    {
        if (!$r->param('ID')) {
            return $this->httpError(400);
        }
        if (!$this->canModerate()) {
            return $this->httpError(403);
        }

        $member = Member::get()->byID($r->param('ID'));
        if (!$member || !$member->exists()) {
            return $this->httpError(404);
        }

        $member->ForumStatus = 'Ghost';
        $member->write();

        // Log event
        $currentUser = Member::currentUser();
        Injector::inst()->get('Logger')->addNotice(sprintf(
            'Ghosted member %s (#%d), by moderator %s (#%d)',
            $member->Email,
            $member->ID,
            $currentUser->Email,
            $currentUser->ID
        ));

        return ($r->isAjax()) ? true : $this->redirectBack();
    }

    /**
     * Get posts to display. This method assumes an URL parameter "ID" which contains the thread ID.
     *
     * @param string $sortDirection The sort order direction, either ASC for ascending (default) or DESC for descending
     *
     * @return DataList Posts
     */
    public function Posts($sortDirection = "ASC")
    {
        $numPerPage = ForumPage::$postsPerPage;

        /** @var DataList $posts */
        $posts = Post::get()
            ->filter('ThreadID', $this->urlParams['ID'])
            ->sort('Created', $sortDirection);

        if (isset($_GET['showPost']) && !isset($_GET['start'])) {
            $postIDList = clone $posts;
            $postIDList = $postIDList->map('ID')->toArray();

            $foundPos      = array_search($_GET['showPost'], $postIDList);
            $_GET['start'] = floor($foundPos / $numPerPage) * $numPerPage;
        }

        if (!isset($_GET['start'])) {
            $_GET['start'] = 0;
        }

        $member = Member::currentUser();

        /*
		 * Don't show posts of banned or ghost members, unless current Member
		 * is a ghost member and owner of current post
		 */
        $posts = $posts->exclude(array(
            'Author.ForumStatus' => 'Banned'
        ));

        if ($member) {
            $posts = $posts->exclude(array(
                'Author.ForumStatus' => 'Ghost',
                'Author.ID:not'      => $member->ID
            ));
        } else {
            $posts = $posts->exclude(array(
                'Author.ForumStatus' => 'Ghost'
            ));
        }

        $paginated = new PaginatedList($posts, $_GET);
        $paginated->setPageLength(ForumPage::$postsPerPage);

        return $paginated;
    }

    /**
     * Get the usable BB codes
     * @todo Replace BBCodeParser
     * @return DataList Returns the usable BB codes
     * @see  BBCodeParser::usable_tags()
     */
    public function BBTags()
    {
        return BBCodeParser::usable_tags();
    }

    /**
     * Section for dealing with reply / edit / create threads form
     *
     * @param bool      $addMode
     * @param bool|Post $post
     *
     * @return Form Returns the post message form
     */
    public function PostMessageForm($addMode = false, $post = false)
    {
        $thread = false;

        if ($post instanceof Post) {
            $thread = $post->Thread();
        } elseif (isset($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
            $thread = ForumThread::get()->byID($this->urlParams['ID']);
        }

        // Check permissions
        $messageSet = array(
            'default'         => _t('Forum.LOGINTOPOST', 'You\'ll need to login before you can post to that forum. Please do so below.'),
            'alreadyLoggedIn' => _t(
                'Forum.LOGINTOPOSTLOGGEDIN',
                'I\'m sorry, but you can\'t post to this forum until you\'ve logged in.'
                . 'If you want to log in as someone else, do so below. If you\'re logged in and you still can\'t post, you don\'t have the correct permissions to post.'
            ),
            'logInAgain'      => _t('Forum.LOGINTOPOSTAGAIN', 'You have been logged out of the forums.  If you would like to log in again to post, enter a username and password below.'),
        );

        // Creating new thread
        if ($addMode && !$this->canPost()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        // Replying to existing thread
        if (!$addMode && !$post && $thread && !$thread->canPost()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        // Editing existing post
        if (!$addMode && $post && !$post->canEdit()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        $forumBBCodeHint = $this->renderWith([
            'type' => 'Includes',
            'Forum_BBCodeHint'
        ]);

        $fields = new FieldList(
            ($post && $post->isFirstPost() || !$thread) ? new TextField("Title", _t('Forum.FORUMTHREADTITLE', 'Title')) : new ReadonlyField('Title', _t('Forum.FORUMTHREADTITLE', ''), 'Re:' . $thread->Title),
            new TextareaField("Content", _t('Forum.FORUMREPLYCONTENT', 'Content')),
            new LiteralField(
                "BBCodeHelper",
                "<div class=\"BBCodeHint\">[ <a href=\"#BBTagsHolder\" id=\"BBCodeHint\">" .
                _t('Forum.BBCODEHINT', 'View Formatting Help') .
                "</a> ]</div>" .
                $forumBBCodeHint
            ),
            new CheckboxField(
                "TopicSubscription",
                _t('Forum.SUBSCRIBETOPIC', 'Subscribe to this topic (Receive email notifications when a new reply is added)'),
                ($thread) ? $thread->getHasSubscribed() : false
            )
        );

        if ($thread) {
            $fields->push(new HiddenField('ThreadID', 'ThreadID', $thread->ID));
        }
        if ($post) {
            $fields->push(new HiddenField('ID', 'ID', $post->ID));
        }

        // Check if we can attach files to this forum's posts
        if ($this->canAttach()) {
            $fields->push(FileField::create("Attachment", _t('Forum.ATTACH', 'Attach file')));
        }

        // If this is an existing post check for current attachments and generate
        // a list of the uploaded attachments
        if ($post && $attachmentList = $post->Attachments()) {
            if ($attachmentList->exists()) {
                $attachments = "<div id=\"CurrentAttachments\"><h4>" . _t('Forum.CURRENTATTACHMENTS', 'Current Attachments') . "</h4><ul>";
                $link        = $this->Link();
                // An instance of the security token
                $token = SecurityToken::inst();

                foreach ($attachmentList as $attachment) {
                    // Generate a link properly, since it requires a security token
                    $attachmentLink = Controller::join_links($link, 'deleteattachment', $attachment->ID);
                    $attachmentLink = $token->addToUrl($attachmentLink);

                    $attachments .= "<li class='attachment-$attachment->ID'>$attachment->Name [<a href='{$attachmentLink}' rel='$attachment->ID' class='deleteAttachment'>"
                        . _t('Forum.REMOVE', 'remove') . "</a>]</li>";
                }
                $attachments .= "</ul></div>";

                $fields->push(new LiteralField('CurrentAttachments', $attachments));
            }
        }

        $actions = new FieldList(
            new FormAction("doPostMessageForm", _t('Forum.REPLYFORMPOST', 'Post'))
        );

        $required = $addMode === true ? new RequiredFields("Title", "Content") : new RequiredFields("Content");

        $form = new Form($this, 'PostMessageForm', $fields, $actions, $required);

        $this->extend('updatePostMessageForm', $form, $post);

        if ($post) {
            $form->loadDataFrom($post);
        }

        return $form;
    }

    /**
     * Wrapper for older templates. Previously the new, reply and edit forms were 3 separate
     * forms, they have now been refactored into 1 form. But in order to not break existing
     * themes too much just include this.
     *
     * @deprecated 0.5
     * @return Form
     */
    public function ReplyForm()
    {
        user_error('Please Use $PostMessageForm in your template rather that $ReplyForm', E_USER_WARNING);

        return $this->PostMessageForm();
    }

    /**
     * Post a message to the forum. This method is called whenever you want to make a
     * new post or edit an existing post on the forum
     *
     * @param array $data
     * @param Form  $form Submitted Form
     *
     * @return HTTPResponse
     */
    public function doPostMessageForm($data, Form $form)
    {
        $member = Member::currentUser();

        //Allows interception of a Member posting content to perform some action before the post is made.
        $this->extend('beforePostMessage', $data, $member);

        $content = (isset($data['Content'])) ? $this->filterLanguage($data["Content"]) : "";
        $title   = (isset($data['Title'])) ? $this->filterLanguage($data["Title"]) : false;

        // If a thread id is passed append the post to the thread. Otherwise create
        // a new thread
        $thread = false;
        if (isset($data['ThreadID'])) {
            $thread = DataObject::get_by_id(ForumThread::class, $data['ThreadID']);
        }

        // If this is a simple edit the post then handle it here. Look up the correct post,
        // make sure we have edit rights to it then update the post
        $post = false;
        if (isset($data['ID'])) {
            /** @var Post $post */
            $post = Post::get()->byID($data['ID']);
            if ($post && $post->isFirstPost()) {
                if ($title) {
                    $thread->Title = $title;
                }
            }
        }

        // Check permissions
        $messageSet = array(
            'default'         => _t('Forum.LOGINTOPOST', 'You\'ll need to login before you can post to that forum. Please do so below.'),
            'alreadyLoggedIn' => _t('Forum.NOPOSTPERMISSION', 'I\'m sorry, but you do not have permission post to this forum.'),
            'logInAgain'      => _t('Forum.LOGINTOPOSTAGAIN', 'You have been logged out of the forums.  If you would like to log in again to post, enter a username and password below.'),
        );

        // Creating new thread
        if (!$thread && !$this->canPost()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        // Replying to existing thread
        if ($thread && !$post && !$thread->canPost()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        // Editing existing post
        if ($thread && $post && !$post->canEdit()) {
            Security::permissionFailure($this, $messageSet);

            return false;
        }

        if (!$thread) {
            $thread          = new ForumThread();
            $thread->ForumID = $this->ID;
            if ($title) {
                $thread->Title = $title;
            }
            $starting_thread = true;
        }

        // Upload and Save all files attached to the field
        // Attachment will always be blank, If they had an image it will be at least in Attachment-0
        //$attachments = new DataObjectSet();
        $attachments = new ArrayList();

        if (!empty($data['Attachment-0']) && !empty($data['Attachment-0']['tmp_name'])) {
            $id = 0;
            //
            // @todo this only supports ajax uploads. Needs to change the key (to simply Attachment).
            //
            while (isset($data['Attachment-' . $id])) {
                $image = $data['Attachment-' . $id];

                if ($image && !empty($image['tmp_name'])) {
                    $file          = PostAttachment::create();
                    $file->OwnerID = Member::currentUserID();
                    $folder        = Config::inst()->get('ForumHolder', 'attachments_folder');

                    try {
                        $upload = Upload::create()->loadIntoFile($image, $file, $folder);
                        $file->write();
                        $attachments->push($file);
                    } catch (ValidationException $e) {
                        $message = _t('Forum.UPLOADVALIDATIONFAIL', 'Unallowed file uploaded. Please only upload files of the following: ');
                        $message .= implode(', ', Config::inst()->get('SilverStripe\\Assets\\File', 'allowed_extensions'));
                        $form->setFieldMessage('Attachment', $message, 'bad');

                        Session::set("FormInfo.Form_PostMessageForm.data", $data);

                        return $this->redirectBack();
                    }
                }

                $id++;
            }
        }

        // from now on the user has the correct permissions. save the current thread settings
        $thread->write();

        if (!$post || !$post->canEdit()) {
            $post           = new Post();
            $post->AuthorID = ($member) ? $member->ID : 0;
            $post->ThreadID = $thread->ID;
        }

        $post->ForumID = $thread->ForumID;
        $post->Content = $content;
        $post->write();


        if ($attachments) {
            foreach ($attachments as $attachment) {
                $attachment->PostID = $post->ID;
                $attachment->write();
            }
        }

        // Add a topic subscription entry if required
        $isSubscribed = ForumThreadSubscription::alreadySubscribed($thread->ID);
        if (isset($data['TopicSubscription'])) {
            if (!$isSubscribed) {
                // Create a new topic subscription for this member
                $obj           = ForumThreadSubscription::create();
                $obj->ThreadID = $thread->ID;
                $obj->MemberID = Member::currentUserID();
                $obj->write();
            }
        } elseif ($isSubscribed) {
            // See if the member wanted to remove themselves
            DB::query("DELETE FROM \"ForumThread_Subscription\" WHERE \"ThreadID\" = '$post->ThreadID' AND \"MemberID\" = '$member->ID'");
        }

        // Send any notifications that need to be sent
        ForumThreadSubscription::notify($post);

        // Send any notifications to moderators of the forum
        if (ForumPage::$notifyModerators) {
            if (isset($starting_thread) && $starting_thread) {
                $this->notifyModerators($post, $thread, true);
            } else {
                $this->notifyModerators($post, $thread);
            }
        }

        return $this->redirect($post->Link());
    }

    /**
     * Send email to moderators notifying them the thread has been created or post added/edited.
     *
     * @param      $post
     * @param      $thread
     * @param bool $starting_thread
     */
    public function notifyModerators($post, $thread, $starting_thread = false)
    {
        $moderators = $this->Moderators();
        if ($moderators && $moderators->exists()) {
            foreach ($moderators as $moderator) {
                if ($moderator->Email) {
                    $adminEmail = Config::inst()->get(Email::class, 'admin_email');

                    $email = new Email();
                    $email->setFrom($adminEmail);
                    $email->setTo($moderator->Email);
                    if ($starting_thread) {
                        $email->setSubject('New thread "' . $thread->Title . '" in forum [' . $this->Title . ']');
                    } else {
                        $email->setSubject('New post "' . $post->Title . '" in forum [' . $this->Title . ']');
                    }
                    $email->setHTMLTemplate('email/ForumMember_NotifyModerator');
                    $email->addData([
                        'NewThread' => $starting_thread,
                        'Moderator' => $moderator,
                        'Author'    => $post->Author(),
                        'Forum'     => $this,
                        'Post'      => $post
                    ]);

                    $email->send();
                }
            }
        }
    }

    /**
     * Return the Forbidden Words in this Forum
     *
     * @return DBText
     */
    public function getForbiddenWords()
    {
        return $this->Parent()->ForbiddenWords;
    }

    /**
     * This function filters $content by forbidden words, entered in forum holder.
     *
     * @param String $content (it can be Post Content or Post Title)
     *
     * @return String $content (filtered string)
     */
    public function filterLanguage($content)
    {
        $words = $this->getForbiddenWords();
        if ($words != "") {
            $words = explode(",", $words);
            foreach ($words as $word) {
                $content = str_ireplace(trim($word), "*", $content);
            }
        }

        return $content;
    }

    /**
     * Get the link for the reply action
     *
     * @return string URL for the reply action
     */
    public function ReplyLink()
    {
        return $this->Link() . 'reply/' . $this->urlParams['ID'];
    }

    /**
     * Show will get the selected thread to the user. Also increments the forums view count.
     *
     * If the thread does not exist it will pass the user to the 404 error page
     *
     * @return array|HTTPResponse_Exception
     */
    public function show()
    {
        $title = Convert::raw2xml($this->Title);

        if ($thread = $this->getForumThread()) {
            //If there is not first post either the thread has been removed or thread if a banned spammer.
            if (!$thread->getFirstPost()) {
                // don't hide the post for logged in admins or moderators
                $member = Member::currentUser();
                if (!$this->canModerate($member)) {
                    return $this->httpError(404);
                }
            }

            $thread->incNumViews();

            $posts = _t('Forum.POSTTOTOPIC', "Posts to the {title} topic", ['title' => $thread->Title]);

            RSSFeed::linkToFeed($this->Link("rss") . '/thread/' . (int)$this->urlParams['ID'], $posts);

            $title = Convert::raw2xml($thread->Title) . ' &raquo; ' . $title;
            $field = DBField::create_field('HTMLText', $title);

            return array(
                'Thread' => $thread,
                'Title'  => $field
            );
        } else {
            // if redirecting post ids to thread id is enabled then we need
            // to check to see if this matches a post and if it does redirect
            if (ForumPage::$redirectPostUrlsToThread && isset($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
                /** @var Post $post */
                if ($post = Post::get()->byID($this->urlParams['ID'])) {
                    return $this->redirect($post->Link(), 301);
                }
            }
        }

        return $this->httpError(404);
    }

    /**
     * Start topic action
     *
     * @return array Returns an array to render the start topic page
     */
    public function starttopic()
    {
        $topic = array(
            'Subtitle' => DBField::create_field('HTMLText', _t('Forum.NEWTOPIC', 'Start a new topic')),
            'Abstract' => DBField::create_field('HTMLText', DataObject::get_one("ForumHolder")->ForumAbstract)
        );

        return $topic;
    }

    /**
     * Get the forum title
     *
     * @return string Returns the forum title
     */
    public function getHolderSubtitle()
    {
        return $this->dbObject('Title');
    }

    /**
     * Get the currently viewed forum. Ensure that the user can access it
     *
     * @return ForumThread
     */
    public function getForumThread()
    {
        if (isset($this->urlParams['ID'])) {
            $id = Convert::raw2sql($this->urlParams['ID']);

            if (is_numeric($id)) {
                if ($thread = DataObject::get_by_id(ForumThread::class, $id)) {
                    if (!$thread->canView()) {
                        Security::permissionFailure($this);

                        return false;
                    }

                    return $thread;
                }
            }
        }

        return false;
    }

    /**
     * Delete an Attachment
     * Called from the EditPost method. Its Done via Ajax
     *
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @return bool
     * @throws HTTPResponse_Exception
     */
    public function deleteattachment(HTTPRequest $request)
    {
        // Check CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        // check we were passed an id and member is logged in
        if (!isset($this->urlParams['ID'])) {
            return false;
        }

        $file = PostAttachment::get()->byID($this->urlParams['ID']);

        if ($file && $file->canDelete()) {
            $file->delete();

            return (!Director::is_ajax()) ? $this->redirectBack() : true;
        }

        return false;
    }

    /**
     * Edit post action
     *
     * @return array Returns an array to render the edit post page
     */
    public function editpost()
    {
        return array(
            'Subtitle' => _t('Forum.EDITPOST', 'Edit post')
        );
    }

    /**
     * Get the post edit form if the user has the necessary permissions
     *
     * @return Form
     */
    public function EditForm()
    {
        $id   = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : null;
        $post = Post::get()->byID($id);

        return $this->PostMessageForm(false, $post);
    }

    /**
     * Delete a post via the url.
     *
     * @param \SilverStripe\Control\HTTPRequest $request
     *
     * @return bool
     * @throws HTTPResponse_Exception
     */
    public function deletepost(HTTPRequest $request)
    {
        // Check CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400);
        }

        if (isset($this->urlParams['ID'])) {
            /** @var Post $post */
            if ($post = DataObject::get_by_id(Post::class, (int)$this->urlParams['ID'])) {
                if ($post->canDelete()) {
                    // delete the whole thread if this is the first one. The delete action
                    // on thread takes care of the posts.
                    if ($post->isFirstPost()) {
                        $thread = DataObject::get_by_id(ForumThread::class, $post->ThreadID);
                        $thread->delete();
                    } else {
                        // delete the post
                        $post->delete();
                    }
                }
            }
        }

        return (Director::is_ajax()) ? true : $this->redirect($this->Link());
    }

    /**
     * Returns the Forum Message from Session. This
     * is used for things like Moving thread messages
     * @return String
     */
    public function ForumAdminMsg()
    {
        $message = Session::get('ForumAdminMsg');
        Session::clear('ForumAdminMsg');

        return $message;
    }


    /**
     * Forum Admin Features form.
     * Handles the dropdown to select the new forum category and the checkbox for stickyness
     *
     * @return Form
     */
    public function AdminFormFeatures()
    {
        if (!$this->canModerate()) {
            return false;
        }

        $id = (isset($this->urlParams['ID'])) ? $this->urlParams['ID'] : false;

        $fields = FieldList::create(
            new CheckboxField('IsSticky', _t('Forum.ISSTICKYTHREAD', 'Is this a Sticky Thread?')),
            new CheckboxField('IsGlobalSticky', _t('Forum.ISGLOBALSTICKY', 'Is this a Global Sticky (shown on all forums)')),
            new CheckboxField('IsReadOnly', _t('Forum.ISREADONLYTHREAD', 'Is this a Read only Thread?')),
            new HiddenField("ID", "Thread")
        );

        /** @var DataList $forums */
        if (($forums = ForumPage::get()) && $forums->exists()) {
            $fields->push(
                DropdownField::create(
                    "ForumID",
                    _t('Forum.CHANGETHREADFORUM', "Change Thread Forum"), $forums->map('ID', 'Title')
                )
            );
        }

        $actions = FieldList::create(
            FormAction::create('doAdminFormFeatures', _t('Forum.SAVE', 'Save'))
        );

        $form = Form::create($this, 'AdminFormFeatures', $fields, $actions);

        // need this id wrapper since the form method is called on save as
        // well and needs to return a valid form object
        if ($id) {
            $thread = ForumThread::get()->byID($id);
            $form->loadDataFrom($thread);
        }

        $this->extend('updateAdminFormFeatures', $form);

        return $form;
    }

    /**
     * Process's the moving of a given topic. Has to check for admin privledges,
     * passed an old topic id (post id in URL) and a new topic id
     *
     * @param $data
     * @param $form
     *
     * @return HTTPResponse
     */
    public function doAdminFormFeatures($data, $form)
    {
        if (isset($data['ID'])) {
            $thread = ForumThread::get()->byID($data['ID']);

            if ($thread) {
                if (!$thread->canModerate()) {
                    return Security::permissionFailure($this);
                }

                $form->saveInto($thread);
                $thread->write();
            }
        }

        return $this->redirect($this->Link());
    }
}
