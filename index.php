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
define( 'GITHUB_REPO', 'dummytest' );

// Your Github OAuth token
define( 'GITHUB_TOKEN', 'xxxxxxxxxxxxxxxxxxxxxxxxxxx' );

/** Optional config **/

// make sure to set to false for real
// define( 'DEBUG', true );

// title if an issue on Google has been deleted
define( 'DELETED_TITLE', 'Deleted issue' );

// body if an issue on Google has been deleted
define( 'DELETED_BODY', 'This issue was deleted.' );

// title of issues on Github
define( 'ISSUE_TITLE', '%s' );

// body of issues on Github. This is a sprintf() string, see sprintf_body() if you want to customize
define( 'ISSUE_BODY', 'This is a "shadow issue" for **Issue %id$s: [%title$s](%issue_href$s)**,
filed on Google Code before the project was moved on Github.

Submitted on %published$s by [%author$s](http://code.google.com%author_url$s)  
Status: %state$s  
Resolution: %status$s  

Please review the original issue and especially its comments.
**New comments here on this issue will be ignored**.  

Thanks.

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

parse( $issues );

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
	$url = "https://code.google.com/feeds/issues/p/${proj}/issues/full?max-results=1&alt=json";
	// &published-min=2012-03-12T00:00:00
	// $url = "./full-6.json";
	
	$json = json_decode( file_get_contents( $url ) );
	
	return( $json->feed->entry );
}

// Parse all Google Issues and send them to Github
function parse( $issues ) {
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
				'id' => $i,
				'title' => DELETED_TITLE,
				'body' => DELETED_BODY,
			);
			post_to_gh( $post_this );
			$i++;
		}
		
		// Create issue on GH
		$post_this = array(
			'id' => $i,
			'title' => sprintf( ISSUE_TITLE, $result['title'] ),
			'body'  => sprintfn( ISSUE_BODY, $result ),
		);
		post_to_gh( $post_this );
		$i++;		
	}
}

// Create an issue on GH
function post_to_gh( $array ) {
	/** Debug stuff */
	$title = $array['title'];
	$body  = $array['body'];
	echo $array['id'] . " <b>$title</b>\n";
	echo "$body\n____________________________________________\n";
	return;
	/**/
	
	$api_url = sprintf( 'https://api.github.com/repos/%s/%s/issues?access_token=%s', GITHUB_LOGIN, GITHUB_REPO, GITHUB_TOKEN );
	
	// Be nice to server and keep under Github's rate limiting (60 req/min)
	usleep( 1100000 ); // 1.1 sec
	
	$result = curl_req( $api_url, array( 'title' => $title, 'body' => $body ) );
	
	var_dump( $result );
}


// CURL request
function curl_req( $url, $params ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    //curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $params ) );  
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-type: application/json' ) );
    // curl_setopt( $ch, CURLOPT_USERPWD, "USERNAME:PASSWORD" );
    // curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
    $content = curl_exec( $ch );
    curl_close( $ch );
    return $content;
}

// Basic text formatting
function html_to_md( $text ) {
	//var_dump( $text );

	// Markdown: 2 spaces at EOL to force line break
	// $text = str_replace( "\n", "  \n", $text );
	
	/**/
	// Basic MD tags
	$text = str_replace(
		array( '<b>', '</b>', '&quot;', '&gt;', '&lt;' ),
		array( '**',  '**',   '"',      '>',    '<'    ),
		$text
	);
	/**/
	
	// < > convert
	// $text = str_replace( '<', '&lt;', $text );
	
	// Ident issue body to display as a pre block
	$text = '    ' . implode( "\n    ", explode( "\n", $text ) );
	
	return $text;
}
