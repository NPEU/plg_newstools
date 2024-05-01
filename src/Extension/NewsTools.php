<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.NewsTools
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\System\NewsTools\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * Tools and Helpers for News Items.
 */
class NewsTools extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected $stubCreated = false;
    protected $stubID = false;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from teh Guided Tour plugin but it ir always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onContentBeforeSave' => 'onContentBeforeSave',
            'onContentAfterSave' => 'onContentAfterSave',
            'onContentPrepareForm' => 'onContentPrepareForm'

        ] : [];
    }

    /**
     * The save event.
     *
     * @param   Event  $event
     *
     * @return  boolean
     */
    public function onContentBeforeSave(Event $event): void
    {
        [$context, $object, $isNew, $data] = array_values($event->getArguments());

        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }

        if (!$context == 'com_content.article') {
            return; // Only run for articles
        }

        if (empty($data['catid']) || !in_array($data['catid'], $this->params->get('applicable_categories'))) {
            return; // Only run for applicable catid's
        }

        $stub_catid = $data['attribs']['stub_catid'];
        $stub_id    = $data['attribs']['stub_id'];

        if (empty($stub_catid)) {
            return; // Only run if a stub_catid set.
        }

        if (!empty($stub_id)) {
            $this->stubCreated = true;
            return; // But don't run if there's already been an article created.
        }

        $title_prefix = trim(trim($this->params->get('title_prefix')), ':');
        $title_prefix_alias = OutputFilter::stringURLSafe($title_prefix);

        // Note we can't generate the content yet as we might not have the new article id.
        // However, we need to save the stub id in the article data so we're having to generate the
        // stub now and update it later.
        $content = '';

        $stub_data = [
            'id'          => 0,
            'catid'       => $stub_catid,
            'title'       => $title_prefix . ': ' . $data['title'],
            'alias'       => $title_prefix_alias  . '-' . $data['alias'],
            'articletext' => '',
            'introtext'   => $content,
            'fulltext'    => '',
            'state'       => 1,
            'language'    => '*',
            'access'      => 1,
            'urls'        => '',
            'metadata'    => json_encode([
                'author'     => '',
                'robots'     => '',
                'rights'     => '',
                'xreference' => ''
            ])
            ];

        $stub_id = $this->createArticle($stub_data);
        if (!$stub_id) {
            return;
        }

        $registry = new Registry;
        $registry->loadString($object->attribs);
        $registry['stub_id'] = $stub_id;
        $new_attribs = $registry->toString();

        $object->attribs = $new_attribs;

        $this->stubID = $stub_id;
    }

    /**
     *
     * @param   Event  $event
     *
     * @return  void
     */
    public function onContentAfterSave(Event $event): void
    {
        [$context, $object, $isNew, $data] = array_values($event->getArguments());

        if ($this->stubCreated) {
            return;
        }

        $app = Factory::getApplication();
        $db  = Factory::getDbo();

        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }

        if (!$context == 'com_content.article') {
            return; // Only run for articles
        }

        if (empty($data['catid']) || !in_array($data['catid'], $this->params->get('applicable_categories'))) {
            return; // Only run for applicable catid's
        }

        $stub_catid = $data['attribs']['stub_catid'];
        $stub_id    = $this->stubID;
        if (empty($stub_catid)) {
            return; // Only run if a stub_catid set.
        }

        // This isn't used but keep for reference:
        #$cat = $app->bootComponent('com_category')->getMVCFactory()->createTable('Category', 'Administrator');
        #$cat->load($stub_catid);

        // We need to find the URL of the category blog that will load this item:
        $query = $db->getQuery(true);
        $query->select($db->quoteName(['path']));
        $query->from($db->quoteName('#__menu'));
        $query->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_content&view=category&layout=blog&id=' . $data['catid']));

        $db->setQuery($query);
        $path = $db->loadResult();

        $url = Uri::getInstance()::root() . $path . '/' . $object->id . '-' . $data['alias'];

        $stub_data = [
            'id'        => $stub_id,
            'catid'       => $stub_catid,
            'introtext' => '<a href="' . $url . '">' . sprintf($this->params->get('readmore_message'), '<em>' . $data['title'] . '</em>') . '</a>',
            'articletext' => '',
            'urls'      => json_encode([
                'urla'     => $url,
                'urlatext' => $data['title'],
                'targeta'  => '',
                'urlb'     => false,
                'urlbtext' => '',
                'targetb'  => '',
                'urlc'     => false,
                'urlctext' => '',
                'targetc'  => ''
            ])
        ];

        if (!$this->updateArticle($stub_data)) {
            $app->enqueueMessage(Text::sprintf( 'PLG_SYSTEM_NEWSTOOLS_STUB_SAVE_ERROR', $cat->title), 'error');
        } else{
            $app->enqueueMessage(Text::sprintf( 'PLG_SYSTEM_NEWSTOOLS_STUB_SAVE_SUCCESS', $cat->title), 'message');
        }
    }

    /**
     * Prepare form.
     *
     * @param   Event  $event
     *
     * @return  boolean
     */
    public function onContentPrepareForm(Event $event): void
    {
        [$form, $data] = array_values($event->getArguments());

        if (!($form instanceof \Joomla\CMS\Form\Form)) {
            $this->_subject->setError('JERROR_NOT_A_FORM');
            return;
        }

        if ($form->getName() != 'com_content.article') {
            return; // We only want to manipulate the article form.
        }

        // When saving, $data is an empty array so we'll need to get the catid from the POSTed data:
        if (is_array($data) && empty($data)) {
            $app = Factory::getApplication();
            $postData = $app->input->post->getArray();
            $catid = $postData['jform']['catid'];
        } else {
            // Ensure it is an object
            $data = (object) $data;
            $catid = $data->catid;
        }

        if (!in_array($catid, $this->params->get('applicable_categories'))) {
            return; // Only run for applicable catid's
        }


        // Add the extra fields to the form
        FormHelper::addFormPath(dirname(dirname(__DIR__)) . '/forms');
        $form->loadFile('newstools', false);
        return;
    }

    protected function createArticle($data)
    {
        $data['rules'] = [
            'core.edit.delete' => [],
            'core.edit.edit' => [],
            'core.edit.state' => []
        ];

        $app = Factory::getApplication();
        $article_model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

        if (!$article_model->save($data)) {

            $err_msg = $article_model->getError();
            throw new GenericDataException($err_msg, 500);
            return false;
        } else {
            // I still can't find a sensible way of getting the ID of the item we just created from
            // the model.
            // `$article_model->getItem()->id;` just returns the curent item id, not the new one.
            // Until I find a better way, I'm just querying the database:
            $db = Factory::getDBO();
            $query = 'SELECT id
                FROM #__content
                WHERE alias = "' . $data['alias'] . '"';

            $db->setQuery($query);
            $id = $db->loadResult();

            return $id;
        }
    }

    protected function updateArticle($data)
    {
        $app = Factory::getApplication();

        $article_model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Administrator', ['ignore_request' => true]);

        // or  $config= array(); $article_model =  new ContentModelArticle($config);
        if (!$article_model->save($data)) {
            $err_msg = $article_model->getError();
            return false;
        } else {
            return true;
        }
    }

}