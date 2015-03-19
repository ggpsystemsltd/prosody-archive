<?php
/**
 * Archive Browser for mod_log_message_sql
 * 
 * @author Murray Crane <murray.crane@ggpsystems.co.uk>
 * @copyright (c) 2015, GGP Systems Limited
 * @license BSD 3-clause license (see LICENSE)
 * @version 1.0
 **/

/**
 * SELECT col1, col2
 * FROM (
 *    SELECT col1, col2, @rowNumber:=@rowNumber+ 1 rn
 *    FROM YourTable
 *       JOIN (SELECT @rowNumber:= 0) r
 * ) t
 * WHERE rn % 2 = 1
 **/