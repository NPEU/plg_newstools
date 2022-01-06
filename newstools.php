<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.NewsTools
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Tools and Helpers for News Items.
 */
class plgSystemNewsTools extends JPlugin
{
    protected $autoloadLanguage = true;
    protected $stubCreated = false;
    protected $stubID = false;

    /**
     * The save event.
     *
     * @param   string   $context  The context
     * @param   JTable   $item     The table
     * @param   boolean  $isNew    Is new item
     * @param   array    $data     The validated data
     *
     * @return  boolean
     */
    public function onContentBeforeSave($context, $item, $isNew, $data = array())
    {
        $app = JFactory::getApplication();
        $db  = JFactory::getDbo();

        if (!$app->isAdmin()) {
            return; // Only run in admin
        }

        if (!$context == 'com_content.article') {
            return; // Only run for new articles
        }

        if (empty($data['catid']) || !in_array($data['catid'], $this->params->get('applicable_categories'))) {
            #echo 'Not NT<pre>'; var_dump($data); echo '</pre>'; exit;
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

        $cat = JTable::getInstance('category');
        $cat->load($stub_catid);

        $title_prefix = trim(trim($this->params->get('title_prefix')), ':');
        $title_prefix_alias = JApplication::stringURLSafe($title_prefix);

        // Note we can't generate the content yet as we might not have the new article id.
        // However, we need to save the stub id in the article data so we're having to generate the
        // stub now and update it later.
        $content = '';

        $stub_data = array(
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
            'metadata'    => json_encode(array(
                'author'     => '',
                'robots'     => '',
                'rights'     => '',
                'xreference' => ''
            ))
        );

        $stub_id = $this->createArticle($stub_data);

        $registry = Joomla\Registry\Registry::getInstance('');
        $registry->loadString($item->attribs);
        $registry['stub_id'] = $stub_id;
        $new_attribs = $registry->toString();

        $item->attribs = $new_attribs;

        $this->stubID = $stub_id;
    }

    /**
     * The save event.
     *
     * @param   string   $context  The context
     * @param   JTable   $item     The article data
     * @param   boolean  $isNew    Is new item
     * @param   array    $data     The validated data
     *
     * @return void
     */
    public function onContentAfterSave($context, $item, $isNew, $data = array())
    {
        if ($this->stubCreated) {
            return;
        }

        $app = JFactory::getApplication();
        $db  = JFactory::getDbo();

        if (!$app->isAdmin()) {
            return; // Only run in admin
        }

        if (!$context == 'com_content.article') {
            return; // Only run for new articles
        }

        if (empty($data['catid']) || !in_array($data['catid'], $this->params->get('applicable_categories'))) {
            return; // Only run for applicable catid's
        }

        $stub_catid = $data['attribs']['stub_catid'];
        $stub_id    = $this->stubID;

        if (empty($stub_catid)) {
            return; // Only run if a stub_catid set.
        }

        $cat = JTable::getInstance('category');
        $cat->load($stub_catid);

        // We need to find the URL of the category blog that will load this item:
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('path')));
        $query->from($db->quoteName('#__menu'));
        $query->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_content&view=category&layout=blog&id=' . $data['catid']));

        $db->setQuery($query);
        $path = $db->loadResult();

        $url = JURI::root() . $path . '/' . $item->id . '-' . $data['alias'];

        $stub_data = array(
            'id'        => $stub_id,
            'introtext' => '<a href="' . $url . '">' . sprintf($this->params->get('readmore_message'), '<em>' . $data['title'] . '</em>') . '</a>',
            'urls'      => json_encode(array(
                'urla'     => $url,
                'urlatext' => $data['title'],
                'targeta'  => '',
                'urlb'     => false,
                'urlbtext' => '',
                'targetb'  => '',
                'urlc'     => false,
                'urlctext' => '',
                'targetc'  => ''
            ))
        );

        if (!$this->updateArticle($stub_data)) {
            JFactory::getApplication()->enqueueMessage(JText::sprintf( 'PLG_SYSTEM_NEWSTOOLS_STUB_SAVE_ERROR', $cat->title), 'error');
        } else{
            JFactory::getApplication()->enqueueMessage(JText::sprintf( 'PLG_SYSTEM_NEWSTOOLS_STUB_SAVE_SUCCESS', $cat->title), 'message');
        }
    }

    /**
     * Prepare form.
     *
     * @param   JForm  $form  The form to be altered.
     * @param   mixed  $data  The associated data for the form.
     *
     * @return  boolean
     */
    public function onContentPrepareForm(JForm $form, $data)
    {
        if (!($form instanceof JForm)) {
            $this->_subject->setError('JERROR_NOT_A_FORM');
            return false;
        }

        if ($form->getName() != 'com_content.article') {
            return; // We only want to manipulate the article form.
        }

        // When saving, $data is an empty array so we'll need to get the catid from the POSTed data:
        if (is_array($data) && empty($data)) {
            $app = JFactory::getApplication();
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

        // Add the extra fields to the form.
        JForm::addFormPath(__DIR__ . '/forms');
        $form->loadFile('newstools', false);
        return true;
    }

    protected function createArticle($data)
    {
        $data['rules'] = array(
            'core.edit.delete' => array(),
            'core.edit.edit' => array(),
            'core.edit.state' => array()
        );

        $basePath = JPATH_ADMINISTRATOR.'/components/com_content';
        require_once $basePath.'/models/article.php';
        $article_model =  JModelLegacy::getInstance('Article','ContentModel');
        // or  $config= array(); $article_model =  new ContentModelArticle($config);
        if (!$article_model->save($data)) {
            $err_msg = $article_model->getError();
            return false;
        } else {
            $id = $article_model->getItem()->id;
            return $id;
        }
    }

    protected function updateArticle($data)
    {
        $basePath = JPATH_ADMINISTRATOR.'/components/com_content';
        require_once $basePath.'/models/article.php';
        $article_model =  JModelLegacy::getInstance('Article','ContentModel');
        // or  $config= array(); $article_model =  new ContentModelArticle($config);
        if (!$article_model->save($data)) {
            $err_msg = $article_model->getError();
            return false;
        } else {
            return true;
        }
    }
}