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

    /**
     * The save event.
     *
     * @param   string   $context  The context
     * @param   JTable   $item     The article data
     * @param   boolean  $isNew    Is new item
     * @param   array    $data     The validated data
     *
     */
    public function onContentAfterSave($context, $item, $isNew, $data = array())
    {

        $app = JFactory::getApplication();
        $db  = JFactory::getDbo();

        if (!$app->isAdmin()) {
            return; // Only run in admin
        }

        /*if (!($isNew && $context == 'com_content.article')) {
            return; // Only run for new articles
        }*/
        
        if (!$context == 'com_content.article') {
            return; // Only run for new articles
        }

        if (!in_array($data['catid'], $this->params->get('applicable_categories'))) {
            return; // Only run for applicable catid's
        }


        /*$brand = explode('-', $data['attribs']['brand']);
        $brand_id = array_pop($brand);
        $brand_alias = implode('-', $brand);*/


        $stub_catid = $data['attribs']['stub_catid'];
        #echo '<pre>'; var_dump($stub_catid); echo '</pre>'; exit;
        if (empty($stub_catid)) {
            return; // Only run if a stub_catid set.
        }

        $cat = JTable::getInstance('category');
        $cat->load($stub_catid);

        #echo '<pre>'; var_dump($cat); echo '</pre>'; exit;
        #echo '<pre>'; var_dump($data); echo '</pre>'; exit;

        $title_prefix = trim(trim($this->params->get('title_prefix')), ':');
        $title_prefix_alias = JApplication::stringURLSafe($title_prefix);
        
        // We need to find the URL f the category blog that will load this item:
        $query = $db->getQuery(true);
        $query->select($db->quoteName(array('path')));
        $query->from($db->quoteName('#__menu'));
        $query->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_content&view=category&layout=blog&id=' . $data['catid']));

        $db->setQuery($query);
        $path = $db->loadResult();

        $url = JURI::root() . $path . '/' . $item->id . '-' . $data['alias'];

        $article_data = array(
            'id'        => 0,
            'catid'     => $stub_catid,
            'title'     => $title_prefix . ': ' . $data['title'],
            'alias'     => $title_prefix_alias  . '-' . $data['alias'],
            'introtext' => '<a href="' . $url . '">' . sprintf($this->params->get('readmore_message'), '<em>' . $data['title'] . '</em>') . '</a>',
            'fulltext'  => '',
            'state'     => 1,
            'language'  => '*',
            'access'    => 1,
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
            )),
            'metadata'  => json_encode(array(
                'author'     => '',
                'robots'     => '',
                'rights'     => '',
                'xreference' => ''
            ))
        );
        #echo '<pre>'; var_dump($article_data); echo '</pre>'; exit;
        $article_id = $this->createArticle($article_data);

        if(!$article_id){
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

    /**
     * Runs on content preparation
     *
     * @param   string  $context  The context for the data
     * @param   object  $data     An object containing the data for the form.
     *
     * @return  boolean
     */
    /*public function onContentPrepareData($context, $data)
    {
        #echo '<pre>'; var_dump($data); echo '</pre>'; exit;
        // Check we are manipulating a valid form.
        if ($context != 'com_content.article') {
            return true;
        }
        if (is_object($data)) {

        }
    }*/

    protected function createArticle($data)
    {
        $data['rules'] = array(
            'core.edit.delete' => array(),
            'core.edit.edit' => array(),
            'core.edit.state' => array(),
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
}