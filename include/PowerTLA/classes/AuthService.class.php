<?php

/**
 * 
 */
class AuthService extends VLEService
{
    /**
     * @property $mode
     *
     * Path Info Variable for the steps in the process.
     */
    protected $mode;

    /**
     * @method void initializeRun() 
     */    
    protected function initializeRun()
    {
        // only real service requests are allowed no web-site interaction from other locations
        // Web-sites should use their backend!
        $this->forbidCORS();
        
        parent::initializeRun();
    }

    /**
     * @method void validateURI()
     */    
    protected function validateURI()
    {
        parent::validateURI();
        
        if($this->status === RESTService::OK)
        {
            $this->mode = $this->path_info;
            $this->log("mode " . $this->mode);
            switch ($this->mode)
            {
                case 'register':        // device registration for hooking mobile devices into the process
                case 'request_token':   // request a new session
                case 'authorize':       // intermediate step to authorizing a service
                case 'access_token':    // grant the access token
                    break;
                default:
                    $this->status = RESTService::BAD_URI;
                    break;
            }
        }
    }
     
    /**
     * @method void validateHeader()
     */
    protected function validateHeader()
    {
        // do the oauth tricks
        if ($this->method === 'DELETE')
        {
            parent::validateHeaders();
            return;
        }
        
        // this is only necessary because this service handles the user authentication
        switch($this->mode)
        {
            case "request_token":
                $this->session->validateConsumerToken();
                break;
            case 'authorize':
                $this->session->validateRequestToken();
                break;
            case 'access_token':
                $this->session->verifyRequestToken();
                break;
            default:
                // this should not be reached.
                $this->status = RESTService::BAD_HEADER; 
                break;
        }
        
        // this is a pre check
        // if we fail at this stage the client needs to start over again
        if ( $this->session->getOAuthState() !== OAUTH_OK )
        {
            $this->status = RESTService::BAD_HEADER;
            $this->not_allowed(); 
        }
    }
     
    /**
     * @method void handle_GET()
     *
     * Maps the functions for the  GET modes
     */    
    protected function handle_GET()
    {
        $this->log("handle_GET");
        switch($this->mode)
        {
            case "request_token":
                $this->grant_requestToken();
                break;
            case 'authorize':
                $this->obtain_authorization();
                break;
            case 'access_token':
                $this->log('enter grant_accessToken');
                $this->grant_accessToken();
                break;
            default:
                // bad request
                $this->bad_request();
                break;
        }
    }
       
    /**
     * @method void handle_POST()
     *
     *  Maps the functions for the  POST modes
     */
    protected function handle_POST()
    {
        $this->log("handle post");
        
        switch ($this->mode) {
            case 'register':
                $this->register_service();
                break;
            case 'authorize':
                // normally this won't be handled by the service. 
                $this->authenticate_user();
                break;
            default:
                $this->bad_request();
                break;
        }
    }
 
    /**
     * @method void handle_DELETE()
     *
     * Triggers session invalidation
     */    
    protected function handle_DELETE()
    {
        $this->mark();
        
        switch($this->mode) {
            case 'access_token':
                $this->invalidate_accessToken();
                break;
            default:
                $this->bad_request();
                break;
        }
    }
    
    /**
     * @method void grant_requestToken() 
     */
    protected function grant_requestToken()
    {
        // GET BASE_URI/request-token
        $this->log("grant request token");

        if($this->session->getOAuthState() === OAUTH_OK)
        {
            $this->session->generateRequestToken();
            $this->log("send the request token to the client");
            $this->data = $this->session->getRequestToken();
        }
        else {
            $this->bad_request();
        }
    }
    
    /**
     * @method void  obtain_authorization() 
     */    
    protected function obtain_authorization()
    {
        // GET BASE_URI/authorize
        $this->mark();
        
        if ($this->VLE->isActiveUser())
        {    
            // in case the user is already authenticated via the web the session management
            // shoud use user id provided by the standard session management
            $this->session->setUserID($this->VLE->getUserId());
        }
        
        if($this->session->requestVerified())
        {
            // this happens if the user uses the Web API
    
            // TODO: check if the service requires the user to verify the access.
            // if ( $this->session->getConsumerVerificationMode() === "auto" )
            //{
                // return verification code to the user
                // this happens in the case the user is already authenticated
                $this->session->generateVerificationCode();
                $this->data = $this->session->getVerificationCode();
                // $this->respond_json_data();
            //}
            //else
            //{
                // verification required
                // if the user has already verified to use the service we automatically grant again
                // if the user has not verified the service the user needs to be forwarded to a
                // location where the verification can be performed.
            //    $this->authentication_required();
            //}
        }
        else
        {
            // if we reach this point the user is not authenticated
            // so we need to ask for authentication
            $this->authentication_required();
        }
    } 
        
    /**
     * @method void authenticate_user()
     */
    protected function authenticate_user()
    {
        // POST BASE_URI/authorize
        $this->mark();
        
        // we need to use the VLE mechanism
        $this->session->verifyUser($_POST['email'], $_POST['credentials']);
        if ( $this->session->requestVerified())
        {
            // if the user credentials were ok we can send the verification code to the frontend
            // it should then proceed and get the access token
            $this->session->generateVerificationCode();
            $this->data = $this->session->getVerificationCode();
        }
        else
        {
            // wrong user name or password
            $this->authentication_required();
        }
    }
        
    /**
     * @method void grant_accessToken()
     */
    protected function grant_accessToken()
    {
        // GET BASE_URI/access_token
        $this->mark();
        if ($this->session->requestVerified())
        {
            $this->session->invalidateRequestToken();
            $this->session->generateAccessToken();
            $this->data = $this->session->getAccessToken();
        }
        else
        {
            // we should be more precise what went wrong.
            $this->authentication_required();
        }
    }
    
    /**
     * @method void invalidateAccessToken()
     *
     * This method removes the current access token from the database.
     * De facto this means the end of the user session.
     *
     * This function always returns an error code. 
     */
    protected function invalidate_accessToken()
    {
        // DELETE BASE_URI/access_token
        $this->mark();
        $this->session->invalidateAccessToken();
        $this->authentication_required();  
    }
}

?>