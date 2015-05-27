<?php

/**
 * Populate the conversation log tables from the prosody archive table
 *
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see LICENSE)
 * @version 1.0
 */
$dbh_prosody = new mysqli( '127.0.0.1', 'prosody', 'BQ6Mv4VJLWVaSWWX', 'prosody' );
if ( $dbh_prosody->connect_errno ) {
	die( "Failed to connect to MySQL: " . $dbh_prosody->connect_error() );
}

$query = "SELECT `id`, `user`, LEFT( `with`, LENGTH( `with` ) - 17 ) AS `with`, `when` FROM `prosodyarchive` WHERE `id` NOT IN ( SELECT DISTINCT( `msg_id` ) FROM `conv_msg` ORDER BY `msg_id` ) AND `id` % 2 != 0 ORDER BY `when`";
$res = $dbh_prosody->query( $query );
$res->data_seek( 0 );
while ( $row = $res->fetch_assoc() ) {
	$t_parameters[ 'when' ] = date( "Y-m-d", $row[ 'when' ] );
	$t_parameters[ 'id' ] = $row[ 'id' ];
	$t_parameters[ 'user_1' ] = $row[ 'user' ];
	$t_parameters[ 'user_2' ] = $row[ 'with' ];

	conversation::record( $dbh_prosody, $t_parameters );
}

$dbh_prosody->close();

class conversation {

	/**
	 * Add a message to the conversation log
	 * 
	 * @param mixed $p_dbh
	 * @param mixed $p_parameters
	 */
	function record( $p_dbh, $p_parameters ) {
		if( !$p_dbh->query( "CREATE TEMPORARY TABLE IF NOT EXISTS tmp_conv AS (SELECT co.conv_id, MAX(CASE WHEN us.name='user1' THEN co.user_name ELSE NULL END) AS user1, MAX(CASE WHEN us.name='user2' THEN co.user_name ELSE NULL END) AS user2 FROM `conversation` AS co INNER JOIN `user_types` AS us ON co.user_id = us.id WHERE co.date='".$p_parameters['when']."' GROUP BY co.conv_id)" )) {
			printf("1 Error: %s\n", $p_dbh->error);
		}
		$query = "SELECT conv_id FROM tmp_conv WHERE (user1='".$p_parameters[ 'user_1' ]."' AND user2='".$p_parameters[ 'user_2' ]."') OR (user1='".$p_parameters[ 'user_2' ]."' AND user2='".$p_parameters[ 'user_1' ]."')";
		$res = $p_dbh->query( $query );
		$res->data_seek( 0 );
		$row_count = $res->num_rows;
		if( $row_count > 0 ) {
			while( $row = $res->fetch_assoc() ) {
				// The conversation exists, just add a new conv_msg row
				if( !$p_dbh->query( "INSERT INTO `conv_msg` (`conv_id`, `msg_id`) VALUES (".$row[ 'conv_id' ].", ".$p_parameters[ 'id' ].")" )) {
					printf("2 Error: %s\n", $p_dbh->error);
				}
			}
		} else {
			// New conversation, add a new conversation row, then a new conv_msg row
			//$stmt->close();
			$res = $p_dbh->query( "SELECT MAX(`conv_id`)+1 AS new_conv_id FROM `conversation`" );
			$res->data_seek( 0 );
			$row = $res->fetch_assoc();
			$t_conv_id = $row[ 'new_conv_id' ];

			$user_type = 1;
			$stmt1 = $p_dbh->prepare( "INSERT INTO `conversation` (`conv_id`, `user_id`, `user_name`, `date`) VALUES (?, ?, ?, ?)" );
			$stmt1->bind_param( "iiss", $t_conv_id, $user_type, $p_parameters[ 'user_1' ], $p_parameters[ 'when' ] );
			$stmt1->execute();
			$stmt1->close();

			$user_type = 2;
			$stmt1 = $p_dbh->prepare( "INSERT INTO `conversation` (`conv_id`, `user_id`, `user_name`, `date`) VALUES (?, ?, ?, ?)" );
			$stmt1->bind_param( "iiss", $t_conv_id, $user_type, $p_parameters[ 'user_2' ], $p_parameters[ 'when' ] );
			$stmt1->execute();
			$stmt1->close();

			$stmt1 = $p_dbh->prepare( "INSERT INTO `conv_msg` (`conv_id`, `msg_id`) VALUES (?, ?)" );
			$stmt1->bind_param( "ii", $t_conv_id, $p_parameters[ 'id' ] );
			$stmt1->execute();
			$stmt1->close();
		}
		// Drop the temp table
		$p_dbh->query( "DROP TEMPORARY TABLE IF EXISTS tmp_conv;" );
	}
}
