<?php
/**
 * Post DBTable
 *
 * @category Application
 * @package Model
 * @subpackage Post
 *
 * @version  $Id: Manager.php 162 2010-07-12 14:58:58Z AntonShevchuk $
 */
class Forum_Model_Post_Manager extends Core_Model_Manager
{
    /**
     * get full info about post
     *
     * @param integer $id
     * @return Core_Db_Table_Row_Abstract
     */
    public function getPost($id = null)
    {
        if (is_null($id)) return false;
        $select = $this->getDbTable()
                ->select()
                ->setIntegrityCheck(false)
                ->from(
                    array(
                        'p' => 'bf_post'
                    ),
                    array(
                        '*',
                        'u.login',
                        'c.ctg_title',
                        'c.id',
                    )
                )
                ->joinLeft(
                    array('u' => 'users'),
                    'p.user_id = u.id',
                    array()
                )
                ->joinLeft(
                    array('c' => 'bf_category'),
                    'c.id = p.ctg_id',
                    array()
                )
                ->where('p.id = ?', $id);
        $result = $this->getDbTable()->fetchRow($select);
        return $result;
    }
    
    private function prepareDataFromForm($data)
    {
        return array(
            'post_title'  => $data['title'],
            'post_text'   => $data['text'],
            'ctg_id'      => $data['category'],
            'post_status' => $data['status'],
        );
    }
    
    public function updatePost($postId, $data)
    {
        $data = $this->prepareDataFromForm($data);
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $postId);
        $this->getDbTable()->update($data, $where);
    }
    
    public function incrementCountView($postId)
    {
        $data = array(
            'post_view_count' => new Zend_Db_Expr('post_view_count + 1'),
        );
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $postId);
        $this->getDbTable()->update($data, $where);
    }
    
    /**
     * get posts with category and user login
     *
     * @return unknown
     */
    public function getPosts($idCat = null)
    {
        $select = $this->getDbTable()->select()->setIntegrityCheck(false)
                ->from(
                    array(
                        'p' => 'bf_post'
                    ),
                    array(
                        '*',
                        'u.login',
                        'c.ctg_title',
                        'count_comments' => new Zend_Db_Expr('COUNT(com.id)'),
                    )
                )
                ->joinLeft(
                    array('u' => 'users'),
                    'p.user_id = u.id', array()
                )
                ->joinLeft(
                    array('c' => 'bf_category'),
                    'c.id = p.ctg_id', array()
                )
                ->joinLeft(
                    array('com' => 'bf_comment'),
                    'p.id = com.post_id', array()
                )
                ->group('p.id');
        if (! is_null($idCat)) {
            $select->where('p.ctg_id = ?', $idCat);
        }
        return $this->getDbTable()->fetchAll($select)->toArray();
    }
    
    public function getPostsSourse($idCat = null)
    {
        $select = $this->getDbTable()->select()->setIntegrityCheck(false)
            ->from(
                array(
                    'p' => 'bf_post'
                ), array(
                    '*',
                    'u.login',
                    'c.ctg_title',
                    'count_comments' => new Zend_Db_Expr('COUNT(com.id)'),
                )
            )
            ->joinLeft(
                array('u' => 'users'),
                'p.user_id = u.id', array()
            )
            ->joinLeft(
                array('c' => 'bf_category'),
                'c.id = p.ctg_id', array()
            )
            ->joinLeft(
                array('com' => 'bf_comment'),
                'p.id = com.post_id', array()
            )
            ->group('p.id');
        if (!is_null($idCat)) {
            $select->where('p.ctg_id = ?', $idCat);
        }
        return $select;
    }
    
    public function getLastPostsSourse($idCat = null)
    {
        $select = $this->getDbTable()->select()->setIntegrityCheck(false)
                ->from(
                    array(
                        'p' => 'bf_post'
                    ), array(
                        '*',
                        'u.login',
                        'c.ctg_title',
                        'count_comments' => new Zend_Db_Expr('COUNT(com.id)'),
                    )
                )
                ->joinLeft(
                    array('u' => 'users'),
                    'p.user_id = u.id', array()
                )
                ->joinLeft(
                    array('c' => 'bf_category'),
                    'c.id = p.ctg_id', array()
                )
                ->joinLeft(
                    array('com' => 'bf_comment'),
                    'p.id = com.post_id', array()
                )
                ->group('p.id')
                ->order('p.created desc');
        if (!is_null($idCat)) {
            $select->where('p.ctg_id = ?', $idCat);
        }
        return $select;
    }
}