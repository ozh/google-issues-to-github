<?php
/** 
 ** Google Issues To Github
 ** A PHP script to migrate issues from a Google Code project to Github
 ** https://github.com/ozh/google-issues-to-github
 **
 **/

/** Required config **
 *********************/
 
// Your Google Project name
define( 'GOOGLE', 'superproject' );

// Your Github login name or organisation name
define( 'GITHUB_LOGIN', 'john' );

// Your Github repo name
define( 'GITHUB_REPO', 'awesomeproject' );

// Your Github OAuth token. Refer to the README.
define( 'GITHUB_TOKEN', '123456789abcdef123456789abcdef' );


/** Optional config **
 *********************/

// Max issue to retrieve (in case you want to test for instance?)
// Note: Google limits to 1000. Refer to the doc if you want to migrate more
define( 'MAX_ISSUES', 9999 );

// Max number of times an API call should be retried before calling quit
define( 'MAX_ATTEMPT', 3 );

// Define start number of issues (should be the same on Google & Github).
// Typically, you want 1 here, unless you're resuming after you've migrated the 1000 first issues
// NOTE: IF YOU EDIT THIS SETTING, YOU MUST ALSO EDIT THE NEXT ONE. Refer to the README for details.
define( 'ISSUE_START_FROM', 1 );

// Define the 'published-min' parameter to retrieve Google Issues from a given date
// Typically, you'll want '' (empty string) here, unless you're resuming after 1000 first issues migration
// NOTE: IF YOU EDIT THIS SETTING, YOU MUST ALSO EDIT THE PREVIOUS ONE. Refer to the README for details.
define( 'PUBLISHED_MIN', '' );

// Output some message so you know what's going on
define( 'VERBOSE', true );

// Set to false when doing for real if you have unwanted notices (you shouldn't get any)
define( 'DEBUG', true );

// title if an issue on Google has been deleted
define( 'DELETED_TITLE', 'Deleted issue' );

// body if an issue on Google has been deleted
define( 'DELETED_BODY', 'This issue was deleted.' );

// title of issues on Github. %s will be replaced with the actual issue title.
define( 'ISSUE_TITLE', '%s [moved]' );

// body of issues on Github. See parse_and_migrate() for the list of available %stuff$s
define( 'ISSUE_BODY', 'This is a copy of **Issue %id$s: [%title$s](%issue_href$s)**, filed on Google Code before the project was [moved on Github](https://github.com/ozh/google-issues-to-github).

Submitted on %published$s by [%author$s](http://code.google.com%author_url$s)
Status: %status$s

Please review the original issue and especially its comments. **New comments here on this issue will be ignored**. Thanks.

### Original description

%content$s');

/**********************************************************************/


// Check we're running from command line
if( php_sapi_name() != 'cli' ) {
	die( 'This script must be invoked from the command line' );
}

// Debug mode
if( defined( 'DEBUG' ) && DEBUG ) {
	error_reporting( E_ALL );
} else {
	error_reporting( E_ERROR | E_PARSE );
}

$issues = get_gc_issues( GOOGLE );

parse_and_migrate( $issues );


/**********************************************************************/

// Better sprintf : replace %blah$s with the value of $array['blah']
// http://www.php.net/sprintf#94608
function sprintfn( $format, array $args = array() ) {
    // map of argument names to their corresponding sprintf numeric argument value
    $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

    // find the next named argument. each search starts at the end of the previous replacement.
    for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
        $arg_pos = $match[0][1];
        $arg_len = strlen($match[0][0]);
        $arg_key = $match[1][0];

        // programmer did not supply a value for the named argument found in the format string
        if (! array_key_exists($arg_key, $arg_nums)) {
            user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
            return false;
        }

        // replace the named argument with the corresponding numeric one
        $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
        $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
    }

    return vsprintf($format, array_values($args));
}

// Fetch all issues from the Google Project
function get_gc_issues( $proj ) {

	wtf_is_going_on( 'Fetching issues from Google project ...' );
	
	$published = '';
	if( defined( 'PUBLISHED_MIN' ) && PUBLISHED_MIN )
		$published = 'published-min=' . PUBLISHED_MIN ;

	$url = sprintf( 'https://code.google.com/feeds/issues/p/%s/issues/full?max-results=%s&alt=json&%s', $proj, MAX_ISSUES, $published );
	
	$json = json_decode( file_get_contents( $url ) );
	
	wtf_is_going_on( 'Fetched ' . count( $json->feed->entry ) . ' issues !' );
	
	return( $json->feed->entry );
}

// Parse all Google Issues and send them to Github
function parse_and_migrate( $issues ) {
	$i = ISSUE_START_FROM;
	foreach( $issues as $issue ) {
	
		$post_this = array();
		
		$issue_href = $issue->link[1];
		$author = $issue->author[0];
		
		$result = array(
			'id'          => $issue->{'issues$id'}->{'$t'},
			'published'   => $issue->published->{'$t'},
			'title'       => $issue->title->{'$t'},
			'content'     => html_to_md( $issue->content->{'$t'} ),
			'issue_href'  => $issue_href->href,
			'author'      => $author->name->{'$t'},
			'author_url'  => $author->uri->{'$t'},
			'state'       => $issue->{'issues$state'}->{'$t'},
			'status'      => $issue->{'issues$status'}->{'$t'},
		);
		
		// If counter is not sync, it's because there was a missing (deleted) issue on Google : post a dummy one
		while( $i < $result['id'] ) {
			$post_this = array(
				'id'    => $i,
				'title' => DELETED_TITLE,
				'body'  => DELETED_BODY,
				'state' => 'closed',
			);
			post_to_gh( $post_this );
			$i++;
		}
		
		// Create issue on GH
		$post_this = array(
			'id'    => $i,
			'title' => sprintf( ISSUE_TITLE, $result['title'] ),
			'body'  => sprintfn( ISSUE_BODY, $result ),
			'state' => $result['state'],
		);
		post_to_gh( $post_this );
		$i++;		
	}
}

// Create an issue on GH
function post_to_gh( $array ) {
	/** Debug stuff. Append just a / at the end of the line to enable debug output -> *
	$title = $array['title'];
	$body  = $array['body'];
	echo $array['id'] . " $title\n";
	//echo "$body\n____________________________________________\n";
	return;
	/**/
	
	wtf_is_going_on( 'Migrating issue #' . $array['id'] );
	
	// First, create a new issue
	$api_url = sprintf( 'https://api.github.com/repos/%s/%s/issues?access_token=%s', GITHUB_LOGIN, GITHUB_REPO, GITHUB_TOKEN );
	
	$success = false;
	$attempt = 1;
	
	// Try to create the issue till it works, retry as many times as defined
	while( $success == false && $attempt <= MAX_ATTEMPT ) {
		$json = json_decode( curl_req( 'POST', $api_url, array( 'title' => $array['title'], 'body' => $array['body'] ) ) );
		if( !isset( $json->number ) ) {
			$attempt++;
			wtf_is_going_on( sprintf( 'Could not CREATE issue %s ... Try %s', $array['id'], $attempt ) );
			sleep( 2 * $attempt ); // wait before next try to reduce chances of server overload
		} else {
			$success = true;
			$created = $json->number;
		}
	}
	
	// Check for great success -- or die.
	if( !$success ) {
		wtf_is_going_on( sprintf( 'Could not CREATE issue %s ... ABORTING.', $array['id'] ) );
		wtf_is_going_on( 'Github server return the following JSON :' );
		var_dump( $json );
		die( -1 );		
	}
	
	// Alright !
	wtf_is_going_on( 'Created issue '. $created );

	// Now, patch that issue to close it if needed
	if( $array['state'] == 'closed' ) {
		
		// Be nice to the server
		// sleep( 1 );

		$api_url = sprintf( 'https://api.github.com/repos/%s/%s/issues/%s?access_token=%s', GITHUB_LOGIN, GITHUB_REPO, $created, GITHUB_TOKEN );
		
		$success = false;
		$attempt = 1;
		
		// Try to close the issue till it hurts
		while( $success == false && $attempt <= MAX_ATTEMPT ) {
			$json = json_decode( curl_req( 'PATCH', $api_url, array( 'state' => 'closed' ) ) );
			if( !isset( $json->number ) ) {
				$attempt++;
				wtf_is_going_on( sprintf( 'Could not CLOSE issue %s ... Try %s', $created, $attempt ) );
				sleep( 2 * $attempt );
			} else {
				$success = true;
				$created = $json->number;
			}
		}
		
		// Check for great success // -- or die.
		if( !$success ) {
			wtf_is_going_on( sprintf( 'Could not CLOSE issue %s ... ABORTING.', $created ) );
			// wtf_is_going_on( 'Github server return the following JSON :' );
			// var_dump( $json );
			// die( -1 );		
		}
		
		// Alright!
		wtf_is_going_on( 'Closed issue '. $json->number );
	}
	
	// Die here if there's a issue number mismatch
	if( $created != $array['id'] ) {
		wtf_is_going_on( sprintf( 'Issue number mismatch! Google is %s, Github is %s. Aborting.', $array['id'], $created ) );
		die( -1 );
	}

	// Be nice to the server, also reduce chance to timeout
	sleep( 1 );
}


// CURL request
function curl_req( $method, $url, $params ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params ) );  
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    $content = curl_exec( $ch );
    curl_close( $ch );
    return $content;
}

// Basic text formatting
function html_to_md( $text ) {
	// Basic HTML tags mostly found in Google Issues
	$text = str_replace(
		array( '<b>', '</b>', '&quot;', '&gt;', '&lt;' ),
		array( '**',  '**',   '"',      '>',    '<'    ),
		$text
	);
	
	// Ident issue body to display as a pre block
	$text = '    ' . implode( "\n    ", explode( "\n", $text ) );
	
	return $text;
}

// Output some stuff to check what's going on
function wtf_is_going_on( $message ) {
	if( defined( 'VERBOSE' ) && VERBOSE )
		echo "**** $message\n";
}