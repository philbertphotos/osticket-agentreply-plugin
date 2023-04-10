<?php
/**
 * Description of plugin
 * @author Joseph Philbert <joe@philbertphotos.com>
 * @license http://opensource.org/licenses/MIT
 * @version 0.1
 */
 // load various classes
foreach (array('email', 'plugin', 'ticket', 'signal', 'osticket', 'format', 'config') as $c) {
	require_once INCLUDE_DIR . "class.$c.php";
}
require_once 'config.php';

class agentReplyPlugin extends Plugin
{
	var $config_class = 'agentReplyConfig';
	const PLUGIN_NAME = 'Agent Reply Plugin';

	public function bootstrap()
	{
		$this->plugininstance();
		//$this->config = $this->getConfig($this->instance->ins);
		Signal::connect('threadentry.created', array($this, 'agentreply'));
	}

	//Checks if osticket supports instances
	function plugininstance() {
			$this->instance = new stdClass();
		if (method_exists($this,'getInstances')) {
			$ins = $this->getInstances($this->id)->key['plugin_id'];
			$this->instance->plugin = "plugin.".$this->id.".instance.".$ins;
			$this->instance->ins = $this->getInstances()->first();
		} else {
			$this->instance->plugin = "plugin.".$this->id;
		}
	}
		
	function agentreply($object){		
		//initilize global entores 
		$this->object = $object;
		$this->thread = $this->object->getThread();
		$this->ticket = $this->object->getThread()->getObject();
		$this->threadentry = ThreadEntry::lookup($this->object->getId());

		//Sanity check
		if (!$this->ticket instanceof Ticket || $this->object->getSource()!='Email')
			return false;
		
		//check department.
		$departID = $this->getConfig($this->instance->ins)->get('alert_dept');
		if ($departID == 0  || $departID = null){
			} else {
				if (!in_array($ticket->getDeptId(), $departID))
				return false;
		}

		//Check Type of thread
		switch ($this->object->getType()) {
		  case 'N':
			$this->log('AR triggered ', "Agent replied to ticket#: ". $this->ticket->getNumber() . " Type:'".$this->object->type. " Source:" . $this->object->getSource());

			//assign ticket to the first responding agent.
			if (!$this->ticket->isAssigned() && $this->getConfig($this->instance->ins)->get('auto-assign')){
				$this->ticket->setStaffId($this->threadentry->getStaffId());
				//$this->log('AR auto-assigned', "Ticket #".$this->ticket->getNumber()." auto-assigned to agent '".$collaborator->getEmail()->email."'");
			}
			if (!$this->switchResponse())
				return false;
			
				return($this->sendResponse());		  
		  break;
		  
		  case 'M':
			$this->log('AR triggered ', "Agent replied to ticket#: ". $this->ticket->getNumber() . " Type:'".$this->object->type. " Source:" . $this->object->getSource());
			if ($this->getConfig($this->instance->ins)->get('auto-assign')){
				if (($this->object->flags & ThreadEntry::FLAG_COLLABORATOR) && (($this->object->flags & ThreadEntry::FLAG_REPLY_ALL) || ($this->object->flags & ThreadEntry::FLAG_REPLY_USER))) {
					//remove responding agent from collaborators
					//$this->log('collaborators', json_encode($this->thread->getObject()->getActiveCollaborators()));
					foreach ($collaborators as $collaborator) {
						if ($collaborator->isActive()) {
							if ($staffid = Staff::getIdByEmail($collaborator->getEmail()->email)) {		
									//$this->ticket->setStaffId($staffid, false, true, __('SYSTEM'));
									//$this->log('AR auto-assigned', "Ticket #".$this->ticket->getNumber()." auto-assigned to agent '".$collaborator->getEmail()->email."'");
									if (!$this->ticket->isAssigned() && $this->getConfig($this->instance->ins)->get('auto-assign')){
									
									}
									
									$vars['cid']=array($collaborator->getId());
									$vars['del']=array($collaborator->getId());		
									
									if($vars['del'] && ($ids=array_filter($vars['del']))) {
										$collaborators = array();
										foreach ($ids as $k => $cid) {
											if (($collaborator=Collaborator::lookup($cid))
											&& ($collaborator->getThreadId() == $this->thread->getId())
											&& $collaborator->delete())
												$collaborators[] = $collaborator;
					
											$this->thread->logCollaboratorEvents($collaborator, $vars);
										}
									}
									if (!$this->switchResponse())
										return false;
					
									return($this->sendResponse());						
							}			
						}
					}
				}
			}
			break; 		  

		  default:
		  return false;
		}
	}
	
	//
	//Switches NOTE to Response by modifying DB entry 
	//	
	function switchResponse() {
		$sql = "UPDATE `".TABLE_PREFIX."thread_entry` SET `type`='R' WHERE `id`=". $this->object->getId() .";";
		//$this->log('sql',$sql);
		$result = db_query($sql);
		if (!$result)
			return false;
		
		$this->log('switchResponse ', "Entry type converted from '".$this->object->type."' to 'R'");

		$this->responseentry = ThreadEntry::lookup($this->object->getId());
		if (!$this->responseentry) 
			return false;
		
		if ($this->responseentry->getType()!='R'){
			$this->log('responseentry-type ', "'".$this->responseentry-->getType()."'");
			return false;
		}			
		return true;
	}

	//
	//Sends email response to ticket creator on agent response.
	//
	function sendResponse() {
		$vars = array();
		$vars['staffId'] = $this->responseentry->getStaffId();
		$vars['thread-type'] = 'R';
		if ($vars['staffId']) {$vars['poster'] = Staff::lookup($vars['staffId']);}

		$vars['response'] = $this->responseentry->getBody();
		$vars['reply-to'] = 'all';
		$vars['emailcollab'] = $this->ticket->getActiveCollaborators();
		//$this->log('send vars ',json_encode($vars));
		
		$errors = array();
		$response = $this->postReply($vars, $errors, true, true, $this->responseentry);
		//$this->log('Response',json_encode($response));
		
		if (!empty($errors)) {
			$this->log('PostReply Errors', json_encode($errors));
			return false;	
		}
		
		return true;
	}	
	 
    function postReply($vars, &$errors, $alert=true, $claim=true, $response) {
        global $thisstaff, $cfg;

        if (!$vars['poster'] && $thisstaff)
            $vars['poster'] = $thisstaff;

        if (!$vars['staffId'] && $thisstaff)
            $vars['staffId'] = $thisstaff->getId();

        if (!$vars['ip_address'] && $_SERVER['REMOTE_ADDR'])
            $vars['ip_address'] = $_SERVER['REMOTE_ADDR'];

        // clear db cache
        $this->ticket->getThread()->_collaborators = null;

        // Get active recipients of the response
        $recipients = $this->ticket->getRecipients($vars['reply-to'], $vars['ccs']);
        if ($recipients instanceof MailingList)
            $vars['thread_entry_recipients'] = $recipients->getEmailAddresses();
		
        $dept = $this->ticket->getDept();
        $assignee = $this->ticket->getStaff();
        // Set status if new is selected
        if ($vars['reply_status_id']
                && ($status = TicketStatus::lookup($vars['reply_status_id']))
                && $status->getId() != $this->ticket->getStatusId())
            $this->ticket->setStatus($status);

        //Claim on response bypasses the department assignment restrictions
        $claim = ($claim
                && $cfg->autoClaimTickets()
                && !$dept->disableAutoClaim());
        if ($claim && $thisstaff && $this->ticket->isOpen() && !$this->ticket->getStaffId()) {
            $this->ticket->setStaffId($thisstaff->getId()); //direct assignment;
        }

        $this->ticket->onResponse($response, array('assignee' => $assignee)); //do house cleaning..

        $this->ticket->lastrespondent = $response->staff;

        /* email the user??  - if disabled - then bail out */
        if (!$alert)
            return $response;

        //allow agent to send from different dept email
        if (!$vars['from_email_id']
                ||  !($email = Email::lookup($vars['from_email_id'])))
            $email = $dept->getEmail();

        $options = array('thread'=>$response);
        $signature = $from_name = '';
        if ($thisstaff && $vars['signature']=='mine')
            $signature=$thisstaff->getSignature();
        elseif ($vars['signature']=='dept' && $dept->isPublic())
            $signature=$dept->getSignature();

        if ($thisstaff && ($type=$thisstaff->getReplyFromNameType())) {
            switch ($type) {
                case 'mine':
                    if (!$cfg->hideStaffName())
                        $from_name = (string) $thisstaff->getName();
                    break;
                case 'dept':
                    if ($dept->isPublic())
                        $from_name = $dept->getName();
                    break;
                case 'email':
                default:
                    $from_name =  $email->getName();
            }
            if ($from_name)
                $options += array('from_name' => $from_name);
        }

        $variables = array(
            'response' => $response,
            'signature' => $signature,
            'staff' => $thisstaff,
            'poster' => $thisstaff
        );

        if ($email
                && $recipients
                && ($tpl = $dept->getTemplate())
                && ($msg=$tpl->getReplyMsgTemplate())) {

            // Add ticket link (possibly with authtoken) if the ticket owner
            // is the only recipient on a ticket with collabs
            if (count($recipients) == 1
                    && $this->ticket->getNumCollaborators()
                    && ($contact = $recipients->offsetGet(0)->getContact())
                    && ($contact instanceof TicketOwner))
                $variables['recipient.ticket_link'] =
                    $contact->getTicketLink();

            $msg = $this->ticket->replaceVars($msg->asArray(),
                $variables + array('recipient' => $this->ticket->getOwner())
            );

            // Attachments
            $attachments = $cfg->emailAttachments() ?
                $response->getAttachments() : array();

            //Send email to recepients
            $email->send($recipients, $msg['subj'], $msg['body'],
                    $attachments, $options);
        }

        return $response;
    }

	
	/**
   * Logging function, Ensures we have permission to log before doing so
   * Attempts to log to the Admin logs, and to the webserver logs if debugging
   * is enabled.
   *
   * @param string $title, string $message
   */
	function log($title, $message) {
		global $ost;
		if ($this->getConfig($this->instance->ins)->get('agent-debug'))
			$ost->logWarning($title, $message, false);
	}	
}