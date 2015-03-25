<?php

/**
 * Archive Browser for mod_log_message_sql
 * 
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see LICENSE)
 * @version 1.0
 */
/**
 * Milestones: 
 *  - output stanzas for today: complete
 *  - sort stanzas into "conversations"
 *  - turn the stanzas into "usable" variables (per row, to keep memory 
 *    usage down?) and output something a little more pretty
 *  - allow for participants and dates to be specified
 *  - authentication to stop knowlesspeople from using the archive
 *  - compact the output by not outputting dates when everything we are
 *    outputting is in a single day
 */
$day_start = mktime( 0, 0, 0 ); // Midnight for today
$day_end = mktime( 23, 59, 59 ); // One second to midnight for tomorrow

$i = 0;
$color1 = "#16569E"; // Blue - "sender"
$color2 = "#A82F2F"; // Red - "receiver"
page_head();
$dbh_prosody = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'prosody' );
if( $dbh_prosody->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_prosody->connect_error() );
}
$dbh_intranet = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'intranet' );
if( $dbh_intranet->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_intranet->connect_error() );
}
$res = $dbh_prosody->query( "SELECT * FROM `prosodyarchive` "
		. "WHERE `when` BETWEEN $day_start AND $day_end "
		. "AND (`user` = 'murrayc' OR `with` = 'murrayc@ggpsystems.co.uk')"
		. "ORDER BY `when`" );
$res->data_seek( 0 );
page_form( $dbh_intranet );
while( $row = $res->fetch_assoc() ) {
	if( $i == 0 ) {
		// Odd numbered ID
		$from_jid = $row[ 'user' ] . "@" . prosody::DOMAIN;
		$to_jid = $row[ 'with' ];
		$stanza = json_decode( $row[ 'stanza' ], true );
		echo "<p>" . $row[ 'id' ] . " ";
		echo date( "(g:i:s A)", $row[ 'when' ] ) . " ";
		echo "<b>" . intranet::get_name( $dbh_intranet, $from_jid ) . "</b> => ";
		echo "<b>" . intranet::get_name( $dbh_intranet, $to_jid ) . "</b>: ";
		echo $stanza[ '__array' ][ 1 ][ '__array' ][ 0 ];
		echo "</p>";
		$i++;
	} else {
		// Even numbered ID
		$i--;
	}
}
page_foot();

function page_head() {
	echo '<html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><title>Conversation log</title><style type="text/css">.fieldset-auto-width{display: inline-block;}</style><link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css"><script src="//code.jquery.com/jquery-1.10.2.js"></script><script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script><link rel="stylesheet" href="/resources/demos/style.css"><script>$(function() {$( ".datepicker" ).datepicker();});</script></head><body><h3>Conversation log</h3>';
}

function page_form( $dbh ) {
	echo '<form method="post"><fieldset>';
	echo '<div style="float: left;"><fieldset style="display: inline-block;"><legend>Participant(s):</legend>';
	intranet::form_staff( $dbh, 1 );
	echo '<br />'; // Necessary?
	intranet::form_staff( $dbh, 2 );
	echo '</fieldset></div>';
	echo '<div style="float: left;"><fieldset style="display: inline-block;"><legend>Date Range:</legend>';
	echo '<label for="date_1" style="display: inline-block; width: 35px;">Start: </label><input type="text" class="datepicker" name="date_1" /> Use mm/dd/yyyy';
	echo '<br />'; // Necessary?
	echo '<label for="date_2" style="display: inline-block; width: 35px;">End: </label><input type="text" class="datepicker" name="date_2" /> Use mm/dd/yyyy';
	echo '</fieldset></div>';
	echo '</fieldset><input type="submit" value="Search" /></form>';
}

function page_foot() {
	echo '</body></html>';
}

class intranet {
	/**
	 * Get all the staff names (and XMPP JIDs) from intranet.staff
	 * @todo Stop it returning everyone.
	 * @param handle $dbh
	 * @param integer $field_id
	 */
	function form_staff( $dbh, $field_id ) {
		$res = $dbh->query( "SELECT `name`, `xmpp` FROM `staff` WHERE `xmpp` != '' ORDER BY `name`" );
		$res->data_seek( 0 );
		echo '<select name="select_'.$field_id.'">';
		echo '<option value="0">Any</option>';
		while( $row = $res->fetch_assoc() ) {
			echo '<option value="' . $row[ 'xmpp' ] . '">' . $row[ 'name' ] . '</option>';
		}
		echo '</select>';
	}

	/**
	 * 
	 * @param handle $dbh
	 * @param string $jid
	 * @return string
	 */
	function get_name( $dbh, $jid ) {
		$res = $dbh->query( "SELECT `name` FROM `staff` WHERE `xmpp` = '$jid'" );
		$res->data_seek( 0 );
		while( $row = $res->fetch_assoc() ) {
			return $row[ 'name' ];
		}
	}

}

class prosody {
	const DOMAIN = "ggpsystems.co.uk";

	/**
	 * jid_to_user: convert an XMPP JID into a username
	 * @param string $jid
	 * @return string
	 */
	function jid_to_user( $jid ) {
		return substr( $jid, strpos( $jid, "@" ) );
	}

	/**
	 * user_to_jid: convert a bare username into an XMPP JID
	 * @param string $name
	 * @return string
	 */
	function user_to_jid( $name ) {
		return $name . "@" . self::DOMAIN;
	}

}
