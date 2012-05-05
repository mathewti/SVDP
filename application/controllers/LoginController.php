<?php

class LoginController extends Zend_Controller_Action
{
    /**
     * salt to be added to the passwords
     * @var string
     */
    private $_SALT = 'tIHn1G$0 d1F5r 3tyHW33 tnR1uN5jt@ L@8';
    
    /**
     * Time out for users session in minutes
     * @var int
     */
    private $_timeout = 60;
    // Getting user info
    // $identity = Zend_Auth::getInstance()->getIdentity();
    // $identity->user_name;
    // $identity->role;
    //
    // Check if identity exists
    // Zend_Auth::getInstance()->hasIdentity();
    
    /**
     * Initializes the login controller
     *
     * 
     * @return void
     */
    public function init()
    {
        /* Initialize action controller here */
        //$this->view->pageTitle = "Login Page";
    }
    
    /**
     * Handles the interface for presenting a user with a form to login
     *
     * @return void
     */
    public function loginAction()
    {
        // Forwards the user if they are already logged on
        $this->forwardUser();
        
        // Set page variables
        $this->view->error_flag = $this->getRequest()->getParam('error_flag');
        $this->view->form = new Application_Model_Login_LoginForm();
        $this->view->pageTitle = "Login Page";
    }
    
    /**
     * Handles the interface for presenting a user with a form to reset password
     *
     * @return void
     */
    public function forgotAction()
    {
        // Set page variables
        $this->view->error_flag = $this->getRequest()->getParam('error_flag');

        $this->view->form = new Application_Model_Login_ForgotForm();

        $this->view->pageTitle = "Forgot Password";
    }
    
    /**
     * Contains the logic for sending a user their lost password
     *
     * @usedby Application_Model_Login_ForgotForm
     * 
     * @return void
     */
    public function forgotprocessAction()
    {
        $request = $this->getRequest();

        // If there isnt a post request go back to index
        if( !$request->isPost() ){
            return $this->_helper->redirector('login');
        }
        
        // Get form data and check that it is valid
        $form = new Application_Model_Login_ForgotForm();
        
        if( !$form->isValid( $_POST ))
        {
            // Redirect to login page and set error flag
            $this->_redirect('/login/forgot/error_flag/TRUE');
        }
        
        // find users info
        $service = new App_Service_LoginService();
        $user = $service->getUserInfo($identity->user_id);
        
        // generate password and send e-mail if the account exists
        if($user){
            $mail = new Zend_Mail();
            $mail->setBodyText('Here is your temporary password. You will be prompted to change it at next login.');
            $mail->setFrom('SVDP@noreply.com', 'System');
            $mail->setSubject('Temporary Password');
            
            $mail->send();
            
            // Update DB with temp password
        }
        
        return $this->_helper->redirector('login');
    }
    
    /**
     * Handles the data submitted by the user to login
     *
     * @usedby Application_Model_Login_LoginForm
     * @return void
     */
    public function processAction()
    {
        $request = $this->getRequest();

        // If there isnt a post request go back to index
        if( !$request->isPost() ){
            return $this->_helper->redirector('login');
        }
        
        // Get form and validate it
        $form = new Application_Model_Login_LoginForm();
        $form->populate($_POST);

        // Check if the password forgot button was pressed
        if($form->forgot->isChecked()){
            $this->_helper->redirector('forgot','login');
        }

        // Validate the fields on the form
        if( !$form->isValid( $request->getPost() ) ){
            // Redirect to login page and set error flag
            $this->_redirect('/login/login/error_flag/TRUE');
        }
        
        // Get user name and pass
        $userid = $form->getValue('username');
        $password = $form->getValue('password');

        // Try to authenticate the user
        $this->authenticate($userid, $password);
    }
    /**
     * Handles the logic for logging a user out
     *
     * @return void
     */
    public function logoutAction()
    {
        // Clear credentials and redirect to login form.
        Zend_Auth::getInstance()->clearIdentity();
        $this->_helper->redirector('index');
    }
    /**
     * Handles the configuration of the authentication adapter
     *
     * @usedby LoginController::process()
     * @return void
     */
    protected function getAuthAdapter()
    {
        // Get the database adapter
        $db = Zend_Db_Table::getDefaultAdapter();
        $adapter = new Zend_Auth_Adapter_DbTable($db);

        // Set the parameters, user must be active.
        $adapter
            ->setTableName('user')
            ->setIdentityColumn('user_id')
            ->setCredentialColumn('password')
            ->setCredentialTreatment('? and active_flag="1"');
        ;
        return($adapter);
    }
    /**
     * Handles the authentication of a user
     *
     * @usedby LoginController::processAction()
     * @param string $userid
     * @param string $password
     * @return void
     */
    protected function authenticate($userid, $password)
    {
        $auth = Zend_Auth::getInstance();
        $authAdapter = $this->getAuthAdapter();
        
        // Set the user inputed values
        $authAdapter
            ->setIdentity($userid)
            ->setCredential( hash('SHA256', $this->_SALT . $password) );
        ;
        
        // Authenticate the user
        $result = $auth->authenticate($authAdapter);
        
        // Check for invalid result
        if( !$result->isValid() ){
            // User was not valid
            // redirect to login
            $this->_redirect('/login/login/error_flag/TRUE');
        }
        
        // Erase the password from the data to be stored with user
        $data = $authAdapter->getResultRowObject(null,'password');

        // Store the users data
        $auth->getStorage()->write($data);
        
        // Get the users identity
        $identity = Zend_Auth::getInstance()->getIdentity();
        
        // Set the identities role. This is strange.. It should already be set
        // but for some reason the first request sent will not contain the role
        // and will cause an error
        //$identity->role = $data->role;
        
        // Set the time out length
        $authSession = new Zend_Session_Namespace('Zend_Auth');
        $authSession->setExpirationSeconds($this->_timeout * 60);
        
        // Check if user needs password change. If so forward to change
        if($data->change_pswd == 1)
        {
            // Post to change password
            return $this->_forward('change','login');
        }
        
        $this->forwardUser();
    }
    
    /**
     * Handles creation of view to change password
     *
     * @usedby Application_Model_Login_LoginForm
     * @return void
     */
    protected function changeAction()
    {
        $this->view->error_flag = $this->getRequest()->getParam('error_flag');
        
        $this->view->pageTitle = "Change Password";
        $this->view->form = new Application_Model_Login_ChangeForm();   
    }
    
    /**
     * Handles logic of changing a users password
     *
     * @usedby Application_Model_Login_ChangeForm
     * @return void
     */
    protected function processchangeAction()
    {
        $request = $this->getRequest();

        // If there isnt a post request go back to index
        if( !$request->isPost() ){
            return $this->_helper->redirector('login');
        }
        
        $form = new Application_Model_Login_ChangeForm();
        
        if( !$form->isValid($request->getPost()) )
        {
            // redirect and indicate error
            $this->_redirect('login/change/error_flag/TRUE');
        }
        
        $pwd = $form->getValue('password');
        $vpwd = $form->getValue('verify');
        
        // Ensure passwords match
        if( strcmp($pwd,$vpwd) )
        {
            // redirect and indicate error
            $this->_redirect('login/change/error_flag/TRUE');
        }
        $identity = Zend_Auth::getInstance()->getIdentity();
        
        $service = new App_Service_LoginService();
        $service->updateUserPassword($identity->user_id,$pwd);
        
        $this->forwardUser();
    }
    /**
     * Handles forwarding a user to the correct landing page
     *
     * @return void
     */
    protected function forwardUser()
    {
        // If user does not have an identity return.
        if( !Zend_Auth::getInstance()->hasIdentity())
            return;
        
        $identity = Zend_Auth::getInstance()->getIdentity();
        
        //Redirect accordinly
        switch( $identity->role)
        {
            case App_Roles::MEMBER:
                $this->_helper->redirector('index',App_Resources::MEMBER);
                break;
            case App_Roles::ADMIN:
                $this->_helper->redirector('index',App_Resources::ADMIN);
                break;
            case App_Roles::TREASURER:
                $this->_helper->redirector('index',App_Resources::TREASURER);
                break;
            default:
                return;
        }
    }
}

