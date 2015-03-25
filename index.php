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

$dbh_prosody = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'prosody' );
if( $dbh_prosody->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_prosody->connect_error() );
}
$dbh_intranet = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'intranet' );
if( $dbh_intranet->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_intranet->connect_error() );
}

page_head();
// Do we have POST values yet? Only do the database bits when we do
// Also, pre-pop the form if we have values
page_form( $dbh_intranet );
$query = "SELECT * FROM `prosodyarchive` ";
// No guarantee that a WHERE is needed
$query .= "WHERE ";
// Date range selected?
$query .= "`when` BETWEEN $day_start AND $day_end ";
// Handle the AND gracefully
$query .= "AND ";
// Participant(s) selected?
$query .= "(`user` = 'murrayc' OR `with` = 'murrayc@ggpsystems.co.uk')";
$query .= "ORDER BY `when`";
$res = $dbh_prosody->query( $query );
$res->data_seek( 0 );
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

/**
 * Displays a parameters form similar to OpenFire's monitoring plugin
 * @param handle $dbh
 * @param array $parameters
 */
function page_form( $dbh, $parameters = array( 'name_1' => NULL, 'name_2' => NULL, 'date_1' => NULL, 'date_2' => NULL ) ) {
	echo '<form method="post"><fieldset>';
	echo '<div style="float: left;"><fieldset style="display: inline-block;"><legend>Participant(s):</legend>';
	intranet::form_staff( $dbh, 1, $parameters[ 'name_1' ] );
	echo '<br />'; // Necessary?
	intranet::form_staff( $dbh, 2, $parameters[ 'name_2' ] );
	echo '</fieldset></div>';
	echo '<div style="float: left;"><fieldset style="display: inline-block;"><legend>Date Range:</legend>';
	echo '<label for="date_1" style="display: inline-block; width: 35px;">Start: </label><input type="text" class="datepicker" name="date_1" value="' . $parameters[ 'date_1' ] . '" /> Use mm/dd/yyyy';
	echo '<br />'; // Necessary?
	echo '<label for="date_2" style="display: inline-block; width: 35px;">End: </label><input type="text" class="datepicker" name="date_2"  value="' . $parameters[ 'date_1' ] . '" /> Use mm/dd/yyyy';
	echo '</fieldset></div>';
	echo '</fieldset><input type="submit" value="Search" /></form>';
}

function page_foot() {
	echo '</body></html>';
}

class intranet {
	
	/**
	 * Get all the staff names (and XMPP JIDs) from intranet.staff
	 * @param handle $dbh
	 * @param integer $field_id
	 * @param string $selected_jid
	 */
	function form_staff( $dbh, $field_id, $selected_jid ) {
		$query = "SELECT `name`, `xmpp` FROM `staff` WHERE `xmpp` != ''";
		$query .= " AND (`end_date` = '0000-00-00' OR `end_date` >= '2015-03-20')";
		$query .= " ORDER BY `name`";
		$res = $dbh->query( $query );
		$res->data_seek( 0 );
		echo '<select name="select_' . $field_id . '">';
		echo '<option value="0">Any</option>';
		while( $row = $res->fetch_assoc() ) {
			echo '<option value="' . $row[ 'xmpp' ] . '"';
			if( $row['xmpp'] == $selected_jid) {
				echo ' selected="selected"';
			}
			echo '>' . $row[ 'name' ] . '</option>';
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
