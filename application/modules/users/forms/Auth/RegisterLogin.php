<?php
/**
 * Register user login form
 *
 * @category Application
 * @package Form
 * @subpackage Users
 *
 * @version  $Id: Register.php 153 2010-07-08 11:51:49Z AntonShevchuk $
 */
class Users_Form_Auth_RegisterLogin extends Zend_Form
{
    /**
     * Form initialization
     *
     * @return void
     */
    public function init()
    {
        $this->addElementPrefixPath(
            'Users_Form_Auth_Validate',
            dirname(__FILE__) . "/Validate",
            'validate'
        );

        $this->setName('userRegisterForm');

        $username = new Zend_Form_Element_Text('login');
        $username->setLabel('User name')
                 ->setRequired(true)
                 ->addFilter('StripTags')
                 ->addFilter('StringTrim')
                 ->addValidator('Alnum')
                 ->addValidator(
                     'StringLength', false,
                     array(Users_Model_User::MIN_USERNAME_LENGTH,
                           Users_Model_User::MAX_USERNAME_LENGTH)
                 )
                 ->addValidator(
                     'Db_NoRecordExists', false,
                     array(
                         array('table' => 'users',
                               'field' => 'login')
                     )
                 );

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Register');
        $submit->setAttrib('class', 'btn btn-primary');

        return $this->addElement($username)->addElement($submit);
    }
}