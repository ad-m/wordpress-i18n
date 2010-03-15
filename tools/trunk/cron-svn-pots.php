<?php
require_once 'makepot.php';

$options = getopt( 'c:p:m:n:sa:b:' );
if ( empty( $options) ) {
?>
	-c	Application svn checkout
	-p	POT svn checkout
	-m	MakePOT project
	-n	POT filename
	-a	Relative path of application inside version dir in -c
	-b	Relative patch of POT dir inside version dir in -p
<?php
	die;
}

$application_svn_checkout = realpath( $options['c'] );
$pot_svn_checkout = realpath( $options['p'] );
$makepot_project = str_replace( '-', '_', $options['m'] );
$pot_name = $options['n'];
$no_branch_dirs = isset( $options['s'] );
$relative_application_path = isset( $options['a'] )? '/'.$options['a'] : '';
$relative_pot_path = isset( $options['b'] )? '/'.$options['b'] : '';
$makepot = new MakePOT;

$versions = array();

chdir( $application_svn_checkout );
system( 'svn up' );
if ( is_dir( 'trunk' ) ) $versions[] = 'trunk';
$branches = glob( 'branches/*' );
if ( false !== $branches ) $versions = array_merge( $versions, $branches );
$tags = glob( 'tags/*' );
if ( false !== $tags ) $versions = array_merge( $versions, $tags );

if ( $no_branch_dirs ) {
	$versions = array( '.' );
}

chdir( $pot_svn_checkout );
if ( $application_svn_checkout != $pot_svn_checkout) system( 'svn up' );
$real_application_svn_checkout = realpath( $application_svn_checkout );
foreach( $versions as $version ) {
	$application_path = "$real_application_svn_checkout/$version{$relative_application_path}";
	if ( !is_dir( $application_path ) ) continue;
	$pot = "$version{$relative_pot_path}/$pot_name";
	$exists = is_file( $pot );
	// do not update old tag pots
	if ( 'tags/' == substr( $version, 0, 5 ) && $exists ) continue;
	if ( !is_dir( $version ) ) system( "svn mkdir $version" );
	if ( !is_dir(dirname("$pot_svn_checkout/$pot")) ) continue;
	call_user_func( array( &$makepot, $makepot_project ), $application_path, "$pot_svn_checkout/$pot" );
	if ( !$exists ) system( "svn add $pot" );
	// do not commit if the difference is only in the header, but always commit a new file
	if ( !$exists || `svn diff $pot | wc -l` > 13 ) {
		preg_match( '/Revision:\s+(\d+)/', `svn info $application_path`, $matches );
		$logmsg = isset( $matches[1] ) && intval( $matches[1] )? "POT, generated from r".intval( $matches[1] ) : 'Automatic POT update';
		$target = $exists? $pot : $version;
		system( "svn ci $target --message='$logmsg'" );
	}
}
