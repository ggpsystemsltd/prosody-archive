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
 *  - allow for participants and dates to be specified: complete
 *  - sort stanzas into "conversations" (using the extra table): complete
 *  - compact the output by not outputting dates when everything we are
 *    outputting is in a single day: complete
 *  - authentication to stop knowlesspeople from misusing the archive: complete
 *  - If someone's not going to be in here all that often, a "once per 
 *    minute" cron job for conversation.php ensures the conv_log is up 
 *    to date and won't have a great deal of work to do when it does run 
 *    (a really basic worker model). This means a major rethink of the 
 *    current conversation.php; it'll need to "fix" the ending of 
 *    conversations when they are added to.
 */
$dbh_intranet = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'intranet' );
if( $dbh_intranet->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_intranet->connect_error() );
}
$dbh_prosody = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'prosody' );
if( $dbh_prosody->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_prosody->connect_error() );
}

$unixtime_1 = mktime( 0, 0, 0, 3, 20, 2015 );
$unixtime_2 = mktime( 23, 59, 59 );
$unixtime_3 = mktime( 0, 0, 0 );
$managers = array( "accounts", "davidj", "murrayc", "tim maxwell" );

page_head();

$parameters = filter_input_array( INPUT_POST );
$server = filter_input_array( INPUT_SERVER );

if( isset( $parameters ) ) {
	// Verification/defaulting of parameters.
	if( array_key_exists( 'select_1', $parameters ) && gettype( $parameters[ 'select_1' ] ) == 'string' && filter_var( $parameters[ 'select_1' ], FILTER_VALIDATE_EMAIL ) && strpos( $parameters[ 'select_1' ], prosody::DOMAIN ) ) {
		// Trust this JID
	} else {
		$parameters[ 'select_1' ] = NULL;
	}
	if( array_key_exists( 'select_2', $parameters ) && gettype( $parameters[ 'select_2' ] ) == 'string' && filter_var( $parameters[ 'select_2' ], FILTER_VALIDATE_EMAIL ) && strpos( $parameters[ 'select_2' ], prosody::DOMAIN ) ) {
		// Trust this JID
	} else {
		$parameters[ 'select_2' ] = NULL;
	}
	if( array_key_exists( 'date_1', $parameters ) && gettype( $parameters[ 'date_1' ] ) == 'string' ) {
		$t_date = date_parse( $parameters[ 'date_1' ] );
		if( !checkdate( $t_date[ 'month' ], $t_date[ 'day' ], $t_date[ 'year' ] ) ) {
			$parameters[ 'date_1' ] = NULL;
		} else {
			// Trust this date
			$unixtime_1 = mktime( 0, 0, 0, $t_date[ 'month' ], $t_date[ 'day' ], $t_date[ 'year' ] );
		}
		unset( $t_date );
	} else {
		$parameters[ 'date_1' ] = NULL;
	}
	if( array_key_exists( 'date_2', $parameters ) && gettype( $parameters[ 'date_2' ] ) == 'string' ) {
		$t_date = date_parse( $parameters[ 'date_2' ] );
		if( !checkdate( $t_date[ 'month' ], $t_date[ 'day' ], $t_date[ 'year' ] ) ) {
			$parameters[ 'date_2' ] = NULL;
		} else {
			// Trust this date
			$unixtime_2 = mktime( 23, 59, 59, $t_date[ 'month' ], $t_date[ 'day' ], $t_date[ 'year' ] );
		}
		unset( $t_date );
	} else {
		$parameters[ 'date_2' ] = NULL;
	}
}

// If user is not a manager, default select_1 to the users name to stop naughtiness
if( !in_array( $server[ 'AUTHENTICATE_SAMACCOUNTNAME' ], $managers ) ) {
	$parameters[ 'select_1' ] = prosody::user_to_jid( $server[ 'AUTHENTICATE_SAMACCOUNTNAME' ] );
}

// Pre-pop the form if we have values
page_form( $dbh_intranet, $parameters );

$t_start_date = date( "Y-m-d", $unixtime_1 );
$t_end_date = date( "Y-m-d", $unixtime_2 );
$t_dt_1 = new DateTime( $t_start_date );
$t_dt_2 = new DateTime( $t_end_date );
$t_interval = $t_dt_1->diff( $t_dt_2 );
$t_interval = $t_interval->format( '%a' );

if( isset( $parameters['submit'] ) ) {
	for( $i = 0; $i <= $t_interval; $i++ ) {
		$query = "SELECT `msg_id`, `nxt_id` FROM prosodyconversation " .
				"WHERE `conv_date` = '";
		$query .= date( "Y-m-d", strtotime( "+" . $i . " days", $unixtime_1 ) );
		$query .= "'";
		$res = $dbh_prosody->query( $query );
		$res->data_seek( 0 );
		while( $row = $res->fetch_assoc() ) {
			$t_messages[] = $row;
		}
	}
	unset( $query );
	unset( $res );
	unset( $row );

	foreach( $t_messages as $t_msg_tuple ) {
		if( !in_array( $t_msg_tuple[ 'msg_id' ], $t_msg_list ) ) {
			$t_msg_list[] = $t_msg_tuple[ 'msg_id' ];
		}
		$t_msg_list[] = $t_msg_tuple[ 'nxt_id' ];
	}
	unset( $t_messages );
	unset( $t_msg_tuple );

	$t_div_flag = false;
	$t_colour_1 = "#cfdbf3";
	$t_colour_2 = "#eff3fb";
	$i = 0;
	foreach( $t_msg_list as $t_msg_id ) {
		if( !is_null( $t_msg_id ) ) {
			$query = "SELECT * FROM `prosodyarchive` "
					. "WHERE `id`=$t_msg_id ";
			if( !is_null( $parameters[ 'select_1' ] ) ) {
				$query .= "AND (`user` = '" . prosody::jid_to_user( $parameters[ 'select_1' ] ) . "' OR `with` = '" . $parameters[ 'select_1' ] . "') ";
			}
			if( !is_null( $parameters[ 'select_1' ] ) ) {
				$query .= "OR (`user` = '" . prosody::jid_to_user( $parameters[ 'select_2' ] ) . "' OR `with` = '" . $parameters[ 'select_2' ] . "') ";
			}
			$res = $dbh_prosody->query( $query );
			$res->data_seek( 0 );
			$row = $res->fetch_assoc();
			if( !is_null( $row ) ) {
				if( !$t_div_flag ) {
					echo '<fieldset style="border-radius: 5px; background-color: ';
					echo ( $i ) ? $t_colour_1 : $t_colour_2;
					echo ';">';
					echo '<legend style="background-color: #ffffff;">';
					echo date( "D, jS M Y", $row[ 'when' ] );
					echo '</legend>';
				}
				$t_div_flag = true;
				$from_jid = $row[ 'user' ] . "@" . prosody::DOMAIN;
				$to_jid = $row[ 'with' ];
				$stanza = json_decode( $row[ 'stanza' ], true );
				echo '<p><font size="2">';
				echo date( "(g:i:s A)", $row[ 'when' ] ) . " ";
				echo '</font>';
				echo "<b>" . intranet::get_name( $dbh_intranet, $from_jid ) . "</b> => ";
				echo "<b>" . intranet::get_name( $dbh_intranet, $to_jid ) . "</b>: ";
				echo $stanza[ '__array' ][ 1 ][ '__array' ][ 0 ];
				echo "</p>";
			}
		} elseif( $t_div_flag ) {
			( $i ) ? $i-- : $i++; // Ternary operator flip-flop
			echo '</fieldset>';
			$t_div_flag = false;
		}
	}
}

page_foot();

$dbh_intranet->close();
$dbh_prosody->close();

function page_head() {
	echo '<html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><title>Conversation log</title><style type="text/css">.fieldset-auto-width{display: inline-block;}</style><link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css"><script src="//code.jquery.com/jquery-1.10.2.js"></script><script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script><link rel="stylesheet" href="/resources/demos/style.css"><script>$(function() {$( ".datepicker" ).datepicker({ minDate: new Date(2015, 2, 20) });});</script></head><body><h3>Conversation log</h3>';
}

/**
 * Displays a parameters form similar to OpenFire's monitoring plugin
 * @param mixed $dbh
 * @param mixed $parameters
 */
function page_form( $dbh, $parameters = array( 'select_1' => NULL, 'select_2' => NULL, 'date_1' => NULL, 'date_2' => NULL ) ) {
	echo '<form action="/prosody-archive/index.php" method="post"><fieldset style="border-radius: 5px;">';
	echo '<div style="float: left;"><fieldset style="display: inline-block; border-radius: 5px;"><legend>Participant(s):</legend>';
	intranet::form_staff( $dbh, 1, $parameters[ 'select_1' ] );
	echo '<br />';
	intranet::form_staff( $dbh, 2, $parameters[ 'select_2' ] );
	echo '</fieldset></div>';
	echo '<div style="float: left;"><fieldset style="display: inline-block; border-radius: 5px;"><legend>Date Range:</legend>';
	echo '<label for="date_1" style="display: inline-block; width: 35px;">Start: </label><input type="text" class="datepicker" name="date_1" value="' . $parameters[ 'date_1' ] . '" /> <font size="2">Use mm/dd/yyyy</font>';
	echo '<br />';
	echo '<label for="date_2" style="display: inline-block; width: 35px;">End: </label><input type="text" class="datepicker" name="date_2"  value="' . $parameters[ 'date_2' ] . '" /> <font size="2">Use mm/dd/yyyy</font>';
	echo '</fieldset></div>';
	echo '</fieldset><input type="submit" name="submit" value="Search" /></form>';
}

function page_foot() {
	echo '</body></html>';
}

function array_flatten( $array ) {
	$return = array();
	foreach( $array as $key => $value ) {
		if( is_array( $value ) ) {
			$return = array_merge( $return, array_flatten( $value ) );
		} else {
			$return[ $key ] = $value;
		}
	}
	return $return;
}

class intranet {

	/**
	 * Get all the staff names (and XMPP JIDs) from intranet.staff
	 * @param mixed $dbh
	 * @param int $field_id
	 * @param string $selected_jid
	 */
	function form_staff( $dbh, $field_id, $selected_jid ) {
		$query = "SELECT `name`, `xmpp` FROM `staff` WHERE `xmpp` != ''";
		$query .= " AND (`end_date` = '0000-00-00' OR `end_date` >= '2015-03-20')";
		$query .= " ORDER BY `name`";
		$res = $dbh->query( $query );
		$res->data_seek( 0 );
		echo '<select name="select_' . $field_id . '">';
		echo '<option value="">Any</option>';
		while( $row = $res->fetch_assoc() ) {
			echo '<option value="' . $row[ 'xmpp' ] . '"';
			if( $row[ 'xmpp' ] == $selected_jid ) {
				echo ' selected="selected"';
			}
			echo '>' . $row[ 'name' ] . '</option>';
		}
		echo '</select>';
	}

	/**
	 * 
	 * @param mixed $dbh
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
	 * Convert an XMPP JID into a username
	 * @param string $jid
	 * @return string
	 */
	function jid_to_user( $jid ) {
		return substr( $jid, 0, strpos( $jid, "@" ) );
	}

	/**
	 * Convert a bare username into an XMPP JID
	 * @param string $name
	 * @return string
	 */
	function user_to_jid( $name ) {
		return $name . "@" . self::DOMAIN;
	}

}
