<?php
/**
 * eMail Notification Library
 *
 * @package kernel
 * @version $Header$
 * @author awcolley
 *
 * created 2003/06/03
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 */
namespace Bitweaver;
use Bitweaver\KernelTools;

/**
 * A library use to store email addresses registered for specific notification events.
 *
 * Currently used in articles, trackers, users register and wiki.
 *
 * @package kernel
 * @todo does not need to inherit BitBase class. Should hold a BitDb connection as a
 * global variable.
 */
class NotificationLib extends BitBase
{
    /**
    * Lists registered notification events
    * @param string $offset the location to begin listing from
    * @param int $max_records the maximum number of records returned
    * @param string $sort_mode the method of sorting used in the listing
    * @param string $find text used to filter listing
    * @return array of registered notification events
    */
    public function list_mail_events( string $offset, int $max_records, string $sort_mode, string $find): array
    {
        if ($find)
        {
            $findesc = '%' . strtoupper( $find ) . '%';
            $mid = " where (UPPER(`event`) like ? or UPPER(`email`) like ?)";
            $bindvars=[$findesc,$findesc];
        }
        else
        {
            $mid = " ";
            $bindvars=[];
        }
        $query = "select * from `".BIT_DB_PREFIX."mail_notifications` $mid order by ".$this->mDb->convertSortmode($sort_mode);
        $query_cant = "select count(*) from `".BIT_DB_PREFIX."mail_notifications` $mid";
        $result = $this->mDb->query($query,$bindvars,$max_records,$offset);
        $cant = $this->mDb->getOne($query_cant,$bindvars);
        $ret = [];
        while ($res = $result->fetchRow())
        {
            $ret[] = $res;
        }
        $retval = [];
        $retval["data"] = $ret;
        $retval["cant"] = $cant;
        return $retval;
    }
    /**
    * Adds an email address for a specified event notification
    * @param string
    * @param array
    * @var string event the specified event
    * @var string object the specified object
    * @var string email the email to remove
    * @param string
    * @return void
    */
    public function add_mail_event($event, $object, $email)
    {
        $query = "insert into `".BIT_DB_PREFIX."mail_notifications`(`event`,`object`,`email`) values(?,?,?)";
        $this->mDb->query( $query, [ 'event', $object, $email ] );
    }

    /**
    * Removes an email address for a specified event notification
    * @param string $event the specified event
    * @param string $object the specified object
    * @param string $email the email to remove
    * @return void
    */
    public function remove_mail_event(string $event, string $object, string $email): void
    {
        $query = "delete from `".BIT_DB_PREFIX."mail_notifications` where `event`=? and `object`=? and `email`=?";
        $this->mDb->query($query, [$event,$object,$email] );
    }

    /**
    * Retrieves the email addresses for a specific event
    * @param string $event event the specified event
    * @param string $object the specified object
    * @return array of email addresses
    */
    public function get_mail_events( string $event, string $object): array
    {
        $query = "select `email` from `".BIT_DB_PREFIX."mail_notifications` where `event`=? and (`object`=? or `object`='*')";
        $result = $this->mDb->query($query, [ $event, $object ] );
        $ret = [];
        while ($res = $result->fetchRow())
        {
            $ret[] = $res["email"];
        }
        return $ret;
    }

    /**
    * Post changes to registered email addresses related to a change event
    * @param int $contentid number of the content item being updated
    * @param string $type content_type of the item
    * @param string $package the package that is being updated
    * @param string $name the name of the object
    * @param string $user the name of user making the change
    * @param string $comment any comment added to the change
    * @param string $data the content of the change
    *
    * @todo Improve the generic handling of the messages
    * Param information probably needs to be passed as an array, or accessed from Content directly
    */
    public function post_content_event( int $contentid, string $type, string $package, string $name, string $user, string $comment, string $data): void
    { global $gBitSystem;
			
		$emails = $this->get_mail_events($package.'_page_changes', $type . $contentid);

		foreach ($emails as $email) {
			global $gBitSmarty;
			$gBitSmarty->assign('mail_site', $_SERVER["SERVER_NAME"]);
			$gBitSmarty->assign('mail_page', $name );
			$gBitSmarty->assign('mail_date', $gBitSystem->getUTCTime());
			$gBitSmarty->assign('mail_user', $user );
			$gBitSmarty->assign('mail_comment', $comment );
			$gBitSmarty->assign('mail_last_version', 1);
			$gBitSmarty->assign('mail_data', $data );
			$gBitSmarty->assign('mail_machine', KernelTools::httpPrefix());
			$gBitSmarty->assign('mail_pagedata', $data );
			$mail_data = $gBitSmarty->fetch('bitpackage:'.$package.'/'.$package.'_change_notification.tpl');

			@mail($email, $package . KernelTools::tra(' page'). ' ' . $name . ' ' . KernelTools::tra('changed'), $mail_data, "From: ".$gBitSystem->getConfig( 'site_sender_email' )."\r\nContent-type: text/plain;charset=utf-8\r\n" );
		}
	}

    /**
    * Notifies registered list of eMail recipients of new user registrations
    * @param string name of the new user
    */
    public function post_new_user_event( string $user ): void
	{ global $gBitSystem, $gBitSmarty;
		$emails = $this->get_mail_events('user_registers','*');
		foreach($emails as $email) {
			$gBitSmarty->assign('mail_user',$user);
			$gBitSmarty->assign('mail_date',$gBitSystem->getUTCTime());
			$gBitSmarty->assign('mail_site',$_SERVER["SERVER_NAME"]);
			$mail_data = $gBitSmarty->fetch('bitpackage:users/new_user_notification.tpl');

			mail( $email, KernelTools::tra('New user registration'),$mail_data,"From: ".$gBitSystem->getConfig('site_sender_email')."\r\nContent-type: text/plain;charset=utf-8\r\n");
		}
	}

}

/**
 * @global NotificationLib Notification library
 */
global $notificationlib;
$notificationlib = new NotificationLib();