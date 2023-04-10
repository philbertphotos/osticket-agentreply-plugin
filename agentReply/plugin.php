<?php
 
/**
 * Description of plugin
 * @author Joseph Philbert <joe@philbertphotos.com>
 * @license http://opensource.org/licenses/MIT
 * @version 1.1
 */
 return array(
    'id' => 'pps:agentReply',
    'version' => '1.1',
	'ost_version' =>    '1.16', # Require osTicket v1.17+
    'name' => 'PPS Agent Reply ',
    'author' => 'Joseph Philbert',
    'description' => 'allow proper replies to tickets by agents via email',
    'url' => 'https://github.com/philbertphotos',
    'plugin' => 'agentReply.php:agentReplyPlugin'
);
?>
