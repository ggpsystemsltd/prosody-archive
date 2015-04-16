<?php

/**
 * Create a "conversation log" table from the prosody archive database
 *
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see LICENSE)
 * @version 1.0
 */
$dbh_prosody = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'prosody' );
if( $dbh_prosody->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_prosody->connect_error() );
}

$query = "SELECT `id`, `user`, `with`, `when` FROM `prosodyarchive` ";
$query .= "WHERE `id` NOT IN ( SELECT DISTINCT( `msg_id` ) FROM `prosodyconversation` ORDER BY `msg_id` ) ";
$query .= "AND `id` % 2 != 0 ";
$query .= "ORDER BY `when`";
$res = $dbh_prosody->query( $query );
$res->data_seek( 0 );
$row = $res->fetch_assoc();

if( !is_null( $row ) ) {
	$t_when = getdate( $row[ 'when' ] );
	$t_parameters[ 'id' ] = $row[ 'id' ];
	$t_parameters[ 'user_1' ] = $row[ 'user' ];
	$t_parameters[ 'user_2' ] = prosody::jid_to_user( $row[ 'with' ] );
	$t_parameters[ 'time_1' ] = mktime( 0, 0, 0, $t_when[ 'mon' ], $t_when[ 'mday' ], $t_when[ 'year' ] );
	$t_parameters[ 'time_2' ] = mktime( 23, 59, 59, $t_when[ 'mon' ], $t_when[ 'mday' ], $t_when[ 'year' ] );

	conversation::enumerate( $dbh_prosody, $t_parameters );
}

$dbh_prosody->close();

class conversation {

	/**
	 * + Get the first message from the archive db where the id > the id
	 *   from the previous step and the participants are the same and it
	 *   occured on the same day
	 * + Write the two ids from the first and second steps to the
	 *   conversation db
	 * + Redo the second step, but use the second step id in place of the
	 *   first step id (WARNING!!! RECURSION!!! DON'T FORGET THE EXIT
	 *   CONDITION!!!)
	 *
	 * @param mixed $p_dbh
	 * @param mixed $p_parameters
	 */
	function enumerate( $p_dbh, $p_parameters ) {
		$query = "SELECT `id` FROM `prosodyarchive` ";
		$query .= "WHERE `id` > " . $p_parameters[ 'id' ] . " ";
		$query .= "AND ((`user` = '" . $p_parameters[ 'user_1' ] . "' AND `with` = '" . prosody::user_to_jid( $p_parameters[ 'user_2' ] ) . "') ";
		$query .= "OR (`user` = '" . $p_parameters[ 'user_2' ] . "' AND `with` = '" . prosody::user_to_jid( $p_parameters[ 'user_1' ] ) . "')) ";
		$query .= "AND `when` BETWEEN " . $p_parameters[ 'time_1' ] . " AND " . $p_parameters[ 'time_2' ] . " ";
		$query .= "AND `id` % 2 != 0 ";
		$query .= "ORDER BY `when`";
		$res = $p_dbh->query( $query );
		$res->data_seek( 0 );
		$row = $res->fetch_assoc();

		// Write the two id's to prosodyconversation
		$t_query = "INSERT INTO prosodyconversation ( `msg_id`, `nxt_id`, `conv_date` ) VALUES ( " . $p_parameters[ 'id' ] . ", ";
		if( is_null( $row[ 'id' ] ) ) {
			$t_query .= "NULL";
		} else {
			$t_query .= $row[ 'id' ];
		}
		$t_query .= ", '" . date( "Y-m-d", $p_parameters[ 'time_1' ] ) . "' )";
		if( !$p_dbh->query( $t_query ) ) {
			echo "ERROR!!! " . $p_dbh->error;
			echo "<br/>" . $t_query;
			die();
		}

		if( is_null( $row ) ) { // Next conversation please.
			$query = "SELECT `id`, `user`, `with` FROM `prosodyarchive` ";
			$query .= "WHERE `id` NOT IN ( SELECT DISTINCT( `msg_id` ) FROM `prosodyconversation` ) ";
			$query .= "AND `when` BETWEEN " . $p_parameters[ 'time_1' ] . " AND " . $p_parameters[ 'time_2' ] . " ";
			$query .= "AND `id` % 2 != 0 ";
			$query .= "ORDER BY `when`";
			$res = $p_dbh->query( $query );
			$res->data_seek( 0 );
			$row = $res->fetch_assoc();
			$p_parameters[ 'user_1' ] = $row[ 'user' ];
			$p_parameters[ 'user_2' ] = prosody::jid_to_user( $row[ 'with' ] );
			// DEBUG
			//var_dump( $row );
			//echo '<br/>';  // if $row = NULL from this query, exit condition.
		}

		if( !is_null( $row ) ) {
			$p_parameters[ 'id' ] = $row[ 'id' ];
			conversation::enumerate( $p_dbh, $p_parameters );
		} else {
			return true;
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
