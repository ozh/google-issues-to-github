<pre>
<?php
/** 
 ** Google Issues To Github
 ** A PHP script to migrate issues from a Google Code project to Github
 ** https://github.com/ozh/google-issues-to-github
 **
 **/

/** Required config **/

// Your Google Project name
define( 'GOOGLE', 'yourls' );

// Your Github login
define( 'GITHUB_LOGIN', 'ozh' );

// Your Github repo
define( 'GITHUB_REPO', 'NotYOURLS' );

// Your Github OAuth token
define( 'GITHUB_TOKEN', 'XXXXXX' );

/** Optional config **/

// Output some message so you know what's going on
define( 'VERBOSE', true );

// Set to false when doing for real
define( 'DEBUG', true );

// title if an issue on Google has been deleted
define( 'DELETED_TITLE', 'Deleted issue' );

// body if an issue on Google has been deleted
define( 'DELETED_BODY', 'This issue was deleted.' );

// title of issues on Github
define( 'ISSUE_TITLE', '%s' );

// body of issues on Github. This is a sprintf() string, see sprintf_body() if you want to customize
define( 'ISSUE_BODY', 'This is a "shadow issue" for **Issue %id$s: [%title$s](%issue_href$s)**, filed on Google Code before the project was [moved on Github](https://github.com/ozh/google-issues-to-github).

Submitted on %published$s by [%author$s](http://code.google.com%author_url$s)
Status: %status$s

Please review the original issue and especially its comments. **New comments here on this issue will be ignored**. Thanks.

### Original description

%content$s');


/**********************************************************************/

if( defined( 'DEBUG' ) && DEBUG ) {
	error_reporting( E_ALL );
} else {
	error_reporting( E_ERROR | E_PARSE );
}

$issues = get_gc_issues( GOOGLE );

$num_issues = count( $issues );

parse_and_migrate( $issues );

// Sprintf the issue body
function sprintf_body( $string, $array ) {
	$default = array(
		'id'          => 0,
		'published'   => '',
		'title'       => '',
		'content'     => '',
		'issue_href'  => '',
		'author'      => '',
		'author_url'  => '',
		'state'       => '',
		'status'      => '',
	);
	
	$array = array_merge( $default, $array );
		
	return sprintf( $string, $array['id'], $array['title'], $array['issue_href'],
		$array['published'], $array['author'], $array['author_url'],
		$array['state'], $array['status'], $array['content'] );
}

// http://www.php.net/manual/fr/function.sprintf.php#94608
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

	$url = "https://code.google.com/feeds/issues/p/${proj}/issues/full?max-results=9999&alt=json";
	// &published-min=2012-03-12T00:00:00
	
	$json = json_decode( file_get_contents( $url ) );
	
	wtf_is_going_on( 'Fetched ' . count( $json->feed->entry ) . ' issues !' );
	
	return( $json->feed->entry );
}

// Parse all Google Issues and send them to Github
function parse_and_migrate( $issues ) {
	$i = 1;
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
	/** Debug stuff *
	$title = $array['title'];
	$body  = $array['body'];
	echo $array['id'] . " $title\n";
	echo "$body\n____________________________________________\n";
	return;
	/**/
	
	wtf_is_going_on( "Attempting to migrate issue #" . $array['id'] );
	
	$api_url = sprintf( 'https://api.github.com/repos/%s/%s/issues?access_token=%s', GITHUB_LOGIN, GITHUB_REPO, GITHUB_TOKEN );
	
	// First, create a new issue
	$json = json_decode( curl_req( 'POST', $api_url, array( 'title' => $array['title'], 'body' => $array['body'] ) ) );

	// Quick check for great success
	if( !isset( $json->number ) ) {
		wtf_is_going_on( 'Could not CREATE... Trying again...' );
		sleep( 5 );
		$json = json_decode( curl_req( 'POST', $api_url, array( 'title' => $array['title'], 'body' => $array['body'] ) ) );
		
		// Still no chance?
		if( !isset( $json->number ) ) {
			wtf_is_going_on( 'Could not CREATE... Aborting. :' );
			var_dump( $json );
			die( -1 );
		}
	}
	
	$created = $json->number;

	wtf_is_going_on( 'Created issue '. $json->number );

	// Now, patch that issue to close it if needed
	if( $array['state'] == 'closed' ) {

		wtf_is_going_on( "Attempting to close issue #" . $json->number );
		sleep( 2 );
		
		$api_url = sprintf( 'https://api.github.com/repos/%s/%s/issues/%s?access_token=%s', GITHUB_LOGIN, GITHUB_REPO, $json->number, GITHUB_TOKEN );
		$json = json_decode( curl_req( 'PATCH', $api_url, array( 'state' => 'closed' ) ) );

		// Quick check for great success
		if( !isset( $json->number ) ) {
			// Not sure why, PATCH requests fail more often ...
			wtf_is_going_on( 'Could not CLOSE... Trying again...' );
			sleep( 5 );
			$json = json_decode( curl_req( 'PATCH', $api_url, array( 'state' => 'closed' ) ) );
			
			// Nope, definitely didn't work :(
			if( !isset( $json->number ) ) {
				wtf_is_going_on( 'Could not CLOSE... Giving up on that one!' );
				// var_dump( $json );
				// die( -1 );
			}
		}
		
		if( isset( $json->number ) )
			wtf_is_going_on( 'Closed issue '. $json->number );
	}

	// Die here if there's a issue number mismatch
	if( $created != $array['id'] ) {
		wtf_is_going_on( '**** Issue number mismatch. Aborting.' );
		die( -1 );
	}

	// Be nice to the server, also reduce chance to timeout
	sleep( 2 );
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
	// Basic MD tags
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