<?php
/**
 * Copyright (c) 2012 by PHP Team of NIX Solutions Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Core_Controller_Action_Crud
 *
 * @uses       Zend_Controller_Action
 * @category   Core
 * @package    Core_Controller
 * @subpackage Action
 */
abstract class Core_Controller_Action_Crud extends Core_Controller_Action
{

    /**
     * @var Core_Grid
     */
    protected $grid;

    /**
     * @var boolean
     */
    protected $showFilter = false;

    /**
     * init controller
     *
     * @return void
     */
    public function init()
    {
        $this->_isDashboard();

        $this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
        $this->_viewRenderer = $this->_helper->getHelper('viewRenderer');
        $this->_redirector = $this->_helper->getHelper('redirector');

        /** change view script path specification */
        $this->_after('_changeViewScriptPathSpec', array('only' => array('index', 'grid', 'create', 'edit')));

        /** load grid */
        $this->_beforeGridFilter('_loadGrid');
    }

    /**
     * index
     *
     * @return void
     */
    public function indexAction()
    {
        /**
         * todo: do it better way
         * init grid before rendering, catch all exception in action
         */
        $this->grid->getHeaders();
        $this->grid->getData();
        $this->view->grid = $this->grid;
        $this->view->showFilter = empty($this->showFilter) ? false : true;
    }

    /**
     * grid
     *
     * @return void
     */
    public function gridAction()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $this->_helper->layout->disableLayout();
        }

        /**
         * todo: do it better way
         * init grid before rendering, catch all exception in action
         */
        $this->grid->getHeaders();
        $this->grid->getData();
        $this->view->grid = $this->grid;
    }

    /**
     * create
     *
     * @return void
     */
    public function createAction()
    {
        $table = $this->_getTable();
        $form = $this->_getCreateForm()
            ->setAction($this->view->url());

        if ($this->_request->isPost() &&
            $form->isValid($this->_getAllParams())
        ) {
            $table->createRow($form->getValues())
                ->save();

            $this->_helper->flashMessenger('Successfully');
            $this->_helper->redirector('index');
        }

        $this->view->form = $form;
    }

    /**
     * edit
     *
     * @return void
     */
    public function editAction()
    {
        $model = $this->_loadModel();

        $form = $this->_getEditForm()
            ->setAction($this->view->url())
            ->setDefaults($model->toArray());

        if ($this->_request->isPost() &&
            $form->isValid($this->_getAllParams())
        ) {
            $model->setFromArray($form->getValues())
                ->save();

            $this->_helper->flashMessenger('Successfully');
            $this->_helper->redirector('index');
        }

        $this->view->form = $form;
    }

    /**
     * load model
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    protected function _loadModel()
    {
        if (!$id = $this->_getParam('id')) {
            $this->_forwardNotFound();
        }

        $table = $this->_getTable();

        if (!$model = $table->getById($id)) {
            $this->_forwardNotFound();
        }

        return $model;
    }

    /**
     * change view script path specification
     *
     * @return void
     */
    protected function _changeViewScriptPathSpec()
    {
        $this->_viewRenderer->setViewScriptPathSpec('crud/:action.:suffix');
    }

    /**
     * delete
     *
     * @return void
     */
    public function deleteAction()
    {
        $model = $this->_loadModel();
        if ($model) {
            $this->_helper->json($model->delete());
        }

    }

    /**
     * @return void
     */
    public function deleteAllAction()
    {
        $res = false;
        if ($ids = $this->_getParam('ids')) {
            $this->_getTable()->delete(
                $this->_getTable()->getAdapter()->quoteInto('id IN (?)', array_values($ids))
            );
            $res = true;
        }
        $this->_helper->json($res);
    }

    /**
     * add create button
     *
     * @return void
     */
//    protected function _addDeleteAllButton()
//    {
//        $link = '<a href="%s" class="button" id="delete-all-button">Delete All</a>';
//                $url = $this->getHelper('url')->url(array(
//            'action' => 'delete'
//        ), 'default');
//        $this->view->placeholder('grid_buttons')->deleteAll = sprintf($link, $url);
//    }

    /**
     * get create form
     *
     * @abstract
     * @return Zend_Form
     */
    abstract protected function _getCreateForm();

    /**
     * get edit form
     *
     * @abstract
     * @return Zend_Form
     */
    abstract protected function _getEditForm();

    /**
     * load grid
     *
     * @return void
     */
    protected function _loadGrid()
    {
        $grid = new Core_Grid();
        $grid->setAdapter($this->_getSource())
            ->setCurrentPageNumber($this->_getParam('page', 1))
            ->setItemCountPerPage(10);

        if ($this->_getParam('orderColumn')) {
            $grid->setOrder($this->_getParam('orderColumn'), $this->_getParam('orderDirection', 'asc'));
        }

        if ($this->_getParam('filterColumn')) {
            $grid->setFilter($this->_getParam('filterColumn'), $this->_getParam('filterValue'));
        }

        $this->grid = $grid;
    }

    /**
     * add all table columns to grid
     *
     * @return void
     */
    public function _addAllTableColumns()
    {
        foreach ($this->_getTable()->info(Zend_Db_Table::COLS) as $col) {
            $this->grid->setColumn(
                $col,
                array(
                    'name'  => ucfirst($col),
                    'type'  => Core_Grid::TYPE_DATA,
                    'index' => $col
                )
            );
        }
    }

    /**
     * add edit column to grid
     *
     * @return void
     */
    public function _addEditColumn()
    {
        $this->grid->setColumn(
            'edit',
            array(
                'name'      => 'Edit',
                'formatter' => array($this, 'editLinkFormatter')
            )
        );
    }

    /**
     * add delete column to grid
     *
     * @return void
     */
    public function _addDeleteColumn()
    {
        $this->grid->setColumn(
            'delete',
            array(
                'name'      => 'Delete',
                'formatter' => array($this, 'deleteLinkFormatter')
            )
        );
    }

    /**
     * add radio column to grid
     *
     * @return void
     */
    public function _addCheckBoxColumn()
    {
        $this->grid->setColumn(
            'check',
            array(
                'name'      => '<input type="checkbox" id="selectAllCheckbox"/>',
                'formatter' => array($this, 'checkBoxLinkFormatter')
            )
        );
    }

    /**
     * edit link formatter
     *
     * @param $value
     * @param $row
     * @return string
     */
    public function editLinkFormatter($value, $row)
    {
        $link = '<a href="%s" class="edit">Edit</a>';
        $url = $this->getHelper('url')->url(
            array(
                'action' => 'edit',
                'id'     => $row['id']
            ),
            'default'
        );

        return sprintf($link, $url);
    }

    /**
     * delete link formatter
     *
     * @param $value
     * @param $row
     * @return string
     */
    public function deleteLinkFormatter($value, $row)
    {
        $link = '<a href="%s" class="delete">Delete</a>';
        $url = $this->getHelper('url')->url(
            array(
                'action' => 'delete',
                'id'     => $row['id']
            ),
            'default'
        );

        return sprintf($link, $url);
    }

    /**
     * delete link formatter
     *
     * @param $value
     * @param $row
     * @return string
     */
    public function checkBoxLinkFormatter($value, $row)
    {
        return '<input type="checkbox" name="id" value="' . $row['id'] . '"/>';
    }

    /**
     * @param $value
     * @param $row
     * @return string
     */
    public function trimFormatter($value, $row)
    {
        if (strlen($value) >= 200) {
            if (false !== ($breakpoint = strpos($value, ' ', 200))) {
                if ($breakpoint < strlen($value) - 1) {
                    $value = substr($value, 0, $breakpoint) . ' ...';
                }
            }
        }
        return $value;
    }

    /**
     * @param $value
     * @param $row
     * @return string
     */
    public function stripTagsFormatter($value, $row)
    {
        return strip_tags($value);
    }

    /**
     * add create button
     *
     * @return void
     */
    protected function _addCreateButton()
    {
        $link = '<a href="%s" class="button">Create</a>';
        $url = $this->getHelper('url')->url(array('action' => 'create'), 'default');
        $this->view->placeholder('grid_buttons')->create = sprintf($link, $url);
    }

    /**
     * add delete button
     *
     * @return void
     */
    protected function _addDeleteButton()
    {
        $link = '<a href="%s" class="button" id="delete-all-button">Delete All</a>';
        $url = $this->getHelper('url')->url(
            array(
                'action' => 'delete-all'
            ),
            'default'
        );
        $this->view->placeholder('grid_buttons')->deleteAll = sprintf($link, $url);
    }


    /**
     * show filter
     *
     * @return void
     */
    protected function _showFilter()
    {
        $this->showFilter = true;
    }

    /**
     * get table
     *
     * @abstract
     * @return Core_Db_Table_Abstract
     */
    abstract protected function _getTable();

    /**
     * get source
     *
     * @return Core_Grid_Adapter_AdapterInterface
     */
    protected function _getSource()
    {
        return new Core_Grid_Adapter_Select($this->_getTable()->select());
    }

    /**
     * set default script path
     *
     * @return Core_Controller_Action_Crud
     */
    protected function _setDefaultScriptPath()
    {
        $this->_viewRenderer->setViewScriptPathSpec('/:controller/:action.:suffix');
        return $this;
    }

    /**
     * before grid filter
     *
     * @param $function
     * @return void
     */
    protected function _beforeGridFilter($function)
    {
        $this->_before($function, array('only' => array('index', 'grid')));
    }


}
