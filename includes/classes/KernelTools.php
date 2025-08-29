<?php
/**
 * @version $Header$
 * @package kernel
 * @subpackage functions
 */
namespace Bitweaver;

class KernelTools
{

    public function __construct() {
        mb_internal_encoding( "UTF-8" );
        define( 'EMAIL_ADDRESS_REGEX', '[-a-zA-Z0-9._%+]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}' );

    }

    /**
     * Return system defined temporary directory.
     * In Unix, this is usually /tmp
     * In Windows, this is usually c:\windows\temp or c:\winnt\temp
     *
     * @access public
     * @return bool|string system defined temporary directory.
     */
    public static function get_temp_dir():bool|string {
        static $tempdir;
        if( !$tempdir ) {
            global $gTempDir;
            if( !empty( $gTempDir ) ) {
                $tempdir = $gTempDir;
            } else {
                if( !KernelTools::is_windows() ) {
                    $tempfile = tempnam( @ini_get( 'safe_mode'
                                ? $_SERVER['DOCUMENT_ROOT'] . '/temp/'
                                : false),'foo' );
                    $tempdir = dirname( $tempfile );
                    @unlink( $tempfile );
                } else {
                    $tempdir = getenv( "TMP" );
                }
            }
        }
        return $tempdir;
    }

    /**
     * is_windows
     *
     * @access public
     * @return bool true if we are on windows, false otherwise
     */
    public static function is_windows(): bool {
        static $sWindows;
        if( !isset( $sWindows )) {
            $sWindows = substr( PHP_OS, 0, 3 ) == 'WIN';
        }
        return $sWindows;
    }

    /**
     * Determines if file exists and is non zero
     *
     * @param string $pFile target file
     * @access public
     * @return bool true if file exists and is non zero, false on failure
     */
    public static function file_valid( $pFile ) {
        return file_exists( $pFile ) && filesize( $pFile );
    }

    /**
     * Return an integer with a signed 32 bit max value 
     *
     * @param int $pInt native PHP integer, may or may not be greater than 2^32
     * @access public
     * @return number less than 32
     */
    public static function int32( $pInt ) {
        return (int)$pInt & 0xFFFFFFFF;
    }

    /**
     * Recursively create directories
     *
     * @param string $pTarget target directory
     * @param float $pPerms octal permissions
     * @access public
     * @return bool true on success, false on failure - mErrors will contain reason for failure
     */
    public static function mkdir_p( string $pTarget, float $pPerms = 0755 ): bool {
        // clean up input
        $pTarget = str_replace( "//", "/", trim( $pTarget ));

        if( empty( $pTarget ) || $pTarget == ';' || $pTarget == "/" ) {
            return false;
        }

        if( ini_get( 'safe_mode' )) {
            $pTarget = preg_replace( '/^\/tmp/', $_SERVER['DOCUMENT_ROOT'].'/temp', $pTarget );
        }

        if( file_exists( $pTarget ) ) {
            return is_dir( $pTarget );
        }

        if( !KernelTools::is_windows() ) {
            if( substr( $pTarget, 0, 1 ) != '/' ) {
                $pTarget = "/$pTarget";
            }

            if( preg_match( '#\.\.#', $pTarget )) {
                bitdebug( "mkdir_p() - We don't allow '..' in path for security reasons: $pTarget" );
                return false;
            }
        }
        $oldu = umask( 0 );
        @mkdir( $pTarget, $pPerms, true );
        umask( $oldu );

        return true;
    }

    /**
     * check to see if particular directories are wroteable by bitweaver
     * added check for Windows - wolff_borg - see http://bugs.php.net/bug.php?id=27609
     *
     * @param string $pPath path to file or dir
     * @access public
     * @return bool true on success, false on failure
     */
    public static function bw_is_writeable( $pPath ): bool {
        if( !KernelTools::is_windows() ) {
            return is_writeable( $pPath );
        } else {
            $writeable = false;
            if( is_dir( $pPath )) {
                $rnd = rand();
                $writeable = @fopen( $pPath."/".$rnd, "a" );
                if( $writeable ) {
                    fclose( $writeable );
                    unlink( $pPath."/".$rnd );
                    $writeable = true;
                }
            } else {
                $writeable = @fopen( $pPath,"a" );
                if( $writeable ) {
                    fclose( $writeable );
                    $writeable = true;
                }
            }
            return $writeable;
        }
    }

    /**
     * clean up an array of values and remove any dangerous html - particularly useful for cleaning up $_GET and $_REQUEST.
     * Turn off urldecode when detoxifying $_GET or $_REQUEST - these two are always urldecoded done by PHP itself (check docs).
     * If you urldecode $_GET twice there might be unexpected consequences (like page One%2BTwo --(PHP)--> One+Two --(you)--> One Two).
     *
     * @param array $pParamHash array to be cleaned
     * @param bool $pHtml set true to escape HTML code as well
     * @param bool $pUrldecode set true to urldecode as well
     * @access public
     * @return void
     */
    public static function detoxify( array &$pParamHash, bool $pHtml = false, bool $pUrldecode = true): void {
        if( !empty( $pParamHash ) && is_array( $pParamHash ) ) {
            foreach( $pParamHash as $key => $value ) {
                if( isset( $value ) && is_array( $value ) ) {
                    KernelTools::detoxify( $value, $pHtml, $pUrldecode );
                } else {
                    if( $pHtml ) {
                        $newValue = $pUrldecode ? urldecode( $value ) : $value;
                        $pParamHash[$key] = htmlspecialchars( $newValue, ENT_NOQUOTES );
                    } elseif( preg_match( "/<script[^>]*>/i", urldecode( $value ?? '' ) ) ) {
                        unset( $pParamHash[$key] );
                    }
                }
            }
        }
    }

    /**
     * file_name_to_title
     *
     * @param string $pFileName
     * @access public
     * @return string clean file name that can be used as title
     */
    public static function file_name_to_title( $pFileName ): string {
        if( preg_match( '/^[A-Z]:\\\/', $pFileName ) ) {
                // MSIE shit file names if passthrough via gigaupload, etc.
                // basename will not work - see http://us3.php.net/manual/en/function.basename.php
                $tmp = preg_split("[\\\]",$pFileName);
                $defaultName = $tmp[count($tmp) - 1];
        } elseif( strpos( '.', $pFileName ) ) {
                list( $defaultName, $ext ) = explode( '.', $pFileName );
        } else {
                $defaultName = $pFileName;
        }
        return str_replace(  '_', ' ', substr( $defaultName, 0, strrpos( $defaultName, '.' ) ) );
    }

    /**
     * path_to_url
     *
     * @param string $pPath Relative path starting in the bitweaver root directory
     * @access public
     * @return string A valid url based on the given path
     */
    public static function storage_path_to_url( $pPath ) {
        global $gBitSystem;
        return STORAGE_HOST_URI.STORAGE_PKG_URL.str_replace( '//', '/', str_replace( '+', '%20', str_replace( '%2F', '/', urlencode( $pPath ))));
    }

    /**
     * simple function to include in deprecated function calls. makes the developer replace with newer code
     *
     * @param string $pReplace code that needs replacing
     * @return void
     */
    public static function deprecated( ?string $pReplace = null ): void {
        $trace = debug_backtrace();
            $function = !empty( $trace[1]['class'] ) ? $trace[1]['class']."::".$trace[1]['function'] : $trace[1]['function'];
        $out = "Deprecated function call:\n\tfunction: $function()\n\tfile: ".$trace[1]['file']."\n\tline: ".$trace[1]['line'];

        if( !empty( $pReplace ) ) {
            $out .= "\n\t".str_replace( "\n", "\n\t", $pReplace );
        }

        if( !defined( 'IS_LIVE' ) && IS_LIVE == false ) {
            vd( $out, false, true );
        } else {
            error_log( $out );
        }
    }

    /**
     * Check that function is enabled on server.
     * @access public
     * @return bool true if function is enabled on server, false otherwise
     */
    public static function function_enabled ( $pName ) {
        static $disabled = null;

        if( $disabled == null ) {
            $functions = @ini_get( 'disable_functions' );
            $functions = str_replace( ' ', '', $functions );
            $disabled = explode( ',', $functions );
        }

        return !( in_array( $pName, $disabled ));
    }


    public static function verify_hex_color( $pColor ) {
        $ret = null;
        
        if( preg_match('/^#[a-f0-9][6]$/i', $pColor) ) {
            $ret = $pColor;
        } elseif( preg_match('/^[a-f0-9][6]$/i', $pColor)) {
            //Check for a hex color string without hash 'c1c2b4'
            $ret = '#' . $pColor;
        } elseif( preg_match('/^#[a-f0-9][3]$/i', $pColor) ) {
            $ret = $pColor;
        } elseif( preg_match('/^[a-f0-9][3]$/i', $pColor)) {
            //Check for a hex color string without hash 'fff'
            $ret = '#' . $pColor;
        } 
        return $ret;
    }


    /**
     * html encode all characters
     * taken from: http://www.bbsinc.com/iso8859.html
     *
     * @param string $pData string that might contain an email address
     * @access public
     * @return string encoded email address
     * $note email regex taken from: http://www.regular-expressions.info/regexbuddy/email.html
     */
    public static function encode_email_addresses( $pData ) {
        $trans = [
            // Upper case
            'A' => '&#065;',
            'B' => '&#066;',
            'C' => '&#067;',
            'D' => '&#068;',
            'E' => '&#069;',
            'F' => '&#070;',
            'G' => '&#071;',
            'H' => '&#072;',
            'I' => '&#073;',
            'J' => '&#074;',
            'K' => '&#075;',
            'L' => '&#076;',
            'M' => '&#077;',
            'N' => '&#078;',
            'O' => '&#079;',
            'P' => '&#080;',
            'Q' => '&#081;',
            'R' => '&#082;',
            'S' => '&#083;',
            'T' => '&#084;',
            'U' => '&#085;',
            'V' => '&#086;',
            'W' => '&#087;',
            'X' => '&#088;',
            'Y' => '&#089;',
            'Z' => '&#090;',

            // lower case
            'a' => '&#097;',
            'b' => '&#098;',
            'c' => '&#099;',
            'd' => '&#100;',
            'e' => '&#101;',
            'f' => '&#102;',
            'g' => '&#103;',
            'h' => '&#104;',
            'i' => '&#105;',
            'j' => '&#106;',
            'k' => '&#107;',
            'l' => '&#108;',
            'm' => '&#109;',
            'n' => '&#110;',
            'o' => '&#111;',
            'p' => '&#112;',
            'q' => '&#113;',
            'r' => '&#114;',
            's' => '&#115;',
            't' => '&#116;',
            'u' => '&#117;',
            'v' => '&#118;',
            'w' => '&#119;',
            'x' => '&#120;',
            'y' => '&#121;',
            'z' => '&#122;',

            // digits
            '0' => '&#048;',
            '1' => '&#049;',
            '2' => '&#050;',
            '3' => '&#051;',
            '4' => '&#052;',
            '5' => '&#053;',
            '6' => '&#054;',
            '7' => '&#055;',
            '8' => '&#056;',
            '9' => '&#057;',

            // special chars
            '_' => '&#095;',
            '-' => '&#045;',
            '.' => '&#046;',
            '@' => '&#064;',

            //'[' => '&#091;',
            //']' => '&#093;',
            //'|' => '&#124;',
            //'{' => '&#123;',
            //'}' => '&#125;',
            //'~' => '&#126;',
        ];
        preg_match_all( "!\b".EMAIL_ADDRESS_REGEX."\b!", $pData, $addresses );
        foreach( $addresses[0] as $address ) {
            $encoded = strtr( $address, $trans );
            $pData = preg_replace( "/\b".preg_quote( $address )."\b/", $encoded, $pData );
        }

        return $pData;
    }

    /**
     * validate email syntax
     * php include as a string
     *
     * @param $pEmail the file to include
     * @return string A string with the results of the file
     **/
    public static function validate_email_syntax( $pEmail ) {
        return preg_match( "!^".EMAIL_ADDRESS_REGEX."$!", trim( $pEmail ));
    }

    /**
     * get_include_contents -- handy function for getting the contents of a
     * php include as a string
     *
     * @param $pFile the file to include
     * @return string A string with the results of the file
     **/
    public static function get_include_contents($pFile) {
        if( is_file( $pFile )) {
            ob_start();
            global $gContent, $gBitSystem, $gBitSmarty, $gLibertySystem, $gBitUser;
            include $pFile;
            $contents = ob_get_contents();
            ob_end_clean();
            return $contents;
        }
        return false;
    }

    /**
     * Translate a string
     *
     * @param string $pString String that needs to be translated
     * @access public
     * @return string
     */
    public static function tra( $pString ) {
        global $gBitLanguage;
        return $gBitLanguage->translate( $pString );
    }

    /**
     * recursively remove files and directories
     *
     * @param string $pPath directory we want to remove
     * @param boolean $pFollowLinks follow symlinks or not
     * @access public
     * @return bool true on success, false on failure
     */
    public static function unlink_r( $pPath, $pFollowLinks = false ) {
        if( !empty( $pPath ) && is_dir( $pPath ) ) {
            if( $dir = opendir( $pPath ) ) {
                while( false !== ( $entry = readdir( $dir ) ) ) {
                    if( is_file( "$pPath/$entry" ) || ( !$pFollowLinks && is_link( "$pPath/$entry" ) ) ) {
                        @unlink( "$pPath/$entry" );
                    } elseif( is_dir( "$pPath/$entry" ) && $entry != '.' && $entry != '..' ) {
                        KernelTools::unlink_r( "$pPath/$entry" ) ;
                    }
                }
                closedir( $dir ) ;
            }
            return @rmdir( $pPath );
        }
        return false;
    }

    /**
     * recursively copy the contents of a directory to a new location akin to copy -r
     *
     * @param string $pSource source directory
     * @param string $pTarget target directory
     * @access public
     * @return void
     */
    public static function copy_r( $pSource, $pTarget ) {
        if( is_dir( $pSource )) {
            @KernelTools::mkdir_p( $pTarget );
            $d = dir( $pSource );

            while( false !== ( $entry = $d->read() )) {
                if( $entry == '.' || $entry == '..' ) {
                    continue;
                }

                $source = $pSource.'/'.$entry;
                if( is_dir( $source )) {
                    KernelTools::copy_r( $source, $pTarget.'/'.$entry );
                    continue;
                }
                copy( $source, $pTarget.'/'.$entry );
            }

            $d->close();
        } else {
            copy( $pSource, $pTarget );
        }
    }

    /**
     * Fetch the contents of a file on a remote host
     *
     * @param string $pUrl url to file to fetch
     * @param boolean $pNoCurl skip the use of curl if this causes problems
     * @access public
     * @return false on failure, contents of file on success
     */
    public static function bit_http_request( $pUrl, $pNoCurl = false ) {
        global $gBitSystem;
        $ret = false;

        if( !empty( $pUrl )) {
            $pUrl = trim( $pUrl );

            // rewrite url if sloppy # added a case for https urls
            if( !preg_match( "!^https?://!", $pUrl )) {
                $pUrl = "http://".$pUrl;
            }

            if( !preg_match("/^[-_a-zA-Z0-9:\/\.\?&;%=\+]*$/", $pUrl )) {
                return false;
            }

            // try using curl first as it allows the use of a proxy
            if( !$pNoCurl && function_exists( 'curl_init' )) {
                $curl = curl_init();
                curl_setopt( $curl, CURLOPT_URL, $pUrl );
                curl_setopt( $curl, CURLOPT_HEADER, 0 );
                curl_setopt( $curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
                curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 1 );
                curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );

                // Proxy settings
                if( $gBitSystem->isFeatureActive( 'site_use_proxy' )) {
                    curl_setopt( $curl, CURLOPT_PROXY, $gBitSystem->getConfig( 'site_proxy_host' ));
                    curl_setopt( $curl, CURLOPT_PROXYPORT, $gBitSystem->getConfig( 'site_proxy_port' ));
                    curl_setopt( $curl, CURLOPT_HTTPPROXYTUNNEL, 1);
                }

                $ret = curl_exec( $curl );
                curl_close( $curl );
            } else {
                // try using fsock now
                $parsed = parse_url( $pUrl );
                if( $fsock = @fsockopen( $parsed['host'], 80, $error['number'], $error['string'], 5 )) {
                    @fwrite( $fsock, "GET ".$parsed['path'].( !empty( $parsed['query'] ) ? '?'.$parsed['query'] : '' )." HTTP/1.1\r\n" );
                    @fwrite( $fsock, "HOST: {$parsed['host']}\r\n" );
                    @fwrite( $fsock, "Connection: close\r\n\r\n" );

                    $get_info = false;
                    while( !@feof( $fsock )) {
                        if( $get_info ) {
                            $ret .= @fread( $fsock, 1024 );
                        } else {
                            if( @fgets( $fsock, 1024 ) == "\r\n" ) {
                                $get_info = true;
                            }
                        }
                    }
                    @fclose( $fsock );
                    if( !empty( $error['string'] )) {
                        return false;
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Parse XML Attributes and return an array
     *
     * this function has a whopper of a RegEx.
     * I nabbed it from http://www.phpbuilder.com/annotate/message.php3?id=1000234 - XOXO spiderr
     *
     * @param string $pString XML type string of parameters
     * @access public
     * @return array
     */
    public static function parse_xml_attributes( string $pString ): array {
        //$parameters = [ '', '' ];
        $parameters = [];
        $regexp_str = "/([A-Za-z0-9_-]+)(?:\\s*=\\s*(?:(\"|\')((?:[\\\\].|[^\\\\]?)*?)(?:\\2)|([^=\\s]*)))?/";
        preg_match_all( $regexp_str, $pString, $matches, PREG_SET_ORDER );
        foreach( $matches as $key => $match ) {
            $attrib = $match[1];
            $value = $match[sizeof( $match )-1];      // The value can be at different indexes because of optional quotes, but we know it's always at the end.
            $value = preg_replace( "/\\\\(.)/","\\1",$value );
            $parameters[$attrib] = trim( $value, '\"' );
        }
        return $parameters;
    }

    /**
     * XML Entity Mandatory Escape Characters
     *
     * @param string $string
     * @param int $quote_style
     * @access public
     * @return string
     */
    public static function xmlentities( string $string, int $quote_style=ENT_QUOTES ): string {
        static $trans;
        if( !isset( $trans )) {
            $trans = get_html_translation_table( HTML_ENTITIES, $quote_style );
            foreach( $trans as $key => $value ) {
                $trans[$key] = '&#'.ord( $key ).';';
            }

            // dont translate the '&' in case it is part of &xxx;
            $trans[chr(38)] = '&';
        }

        // after the initial translation, _do_ map standalone '&' into '&#38;'
        return preg_replace( "/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,5};)/","&#38;" , strtr( $string, $trans ));
    }

    /**
     * Redirect to another page or site
     * @param string The url to redirect to
     */
    public static function bit_redirect( $pUrl, $pStatusCode=HttpStatusCodes::HTTP_FOUND ) {
        // Handle non-3xx codes separately
        if( $pStatusCode && isset( $errors[$pStatusCode] ) ) {
            header( HttpStatusCodes::httpHeaderFor( $pStatusCode ) );
            $pStatusCode = null;
        }

        global $gBitUser;
        if( is_object( $gBitUser ) ) {
    //		apache_setenv( 'USERID', $gBitUser->getField('login', '-'), true );
        }

        // clean up URL before executing it
        while( strstr( $pUrl, '&&' ) ) {
            $pUrl = str_replace( '&&', '&', $pUrl );
        }

        while( strstr( $pUrl, '&amp;&amp;' ) ) {
            $pUrl = str_replace( '&amp;&amp;', '&amp;', $pUrl );
        }

        // header locates should not have the &amp; in the address it breaks things
        while( strstr( $pUrl, '&amp;' ) ) {
            $pUrl = str_replace( '&amp;', '&', $pUrl );
        }
        
        if( $pStatusCode ) {
            header( 'Location: ' . $pUrl, true, $pStatusCode );
        } else {
            header( 'Location: ' . $pUrl );
        }
        session_write_close();
        exit();
    }

    /**
     * array_diff_keys
     *
     * @access public
     */
    public static function array_diff_keys() {
        $args = func_get_args();

        $res = $args[0];
        if( !is_array( $res )) {
            return [];
        }

        for( $i = 1; $i < count( $args ); $i++ ) {
            if( !is_array( $args[$i] )) {
                continue;
            }
            foreach( $args[$i] as $key => $data ) {
                unset( $res[$key] );
            }
        }
        return $res;
    }

    /**
     * trim_array
     *
     * @param array $pArray
     * @access public
     */
    public static function trim_array( &$pArray ) {
        if( is_array( $pArray ) ) {
            foreach( array_keys( $pArray ) as $key ) {
                if( is_string( $pArray[$key] ) ) {
                    $pArray[$key] = trim( $pArray[$key] );
                }
            }
        }
    }


    /**
     * ordinalize
     *
     * @param numeric $num Number to append th, st, nd, rd to - only makes sense when languages is english
     * @access public
     */
    public static function ordinalize( $num ) {
        $ord = '';
        if( is_numeric( $num ) ) {
            if( $num >= 11 and $num <= 19 ) {
                $ord = KernelTools::tra( "th" );
            } elseif( $num % 10 == 1 ) {
                $ord = KernelTools::tra( "st" );
            } elseif( $num % 10 == 2 ) {
                $ord = KernelTools::tra( "nd" );
            } elseif( $num % 10 == 3 ) {
                $ord = KernelTools::tra( "rd" );
            } else {
                $ord = KernelTools::tra( "th" );
            }
        }

        return $num.$ord;
    }

    /**
     * Cleans file path according to system we're on
     *
     * @param string $pPath
     * @access public
     * @return string
     */
    public static function clean_file_path( string $pPath ): string {
        $pPath = !empty($_SERVER["SERVER_SOFTWARE"]) && strpos($_SERVER["SERVER_SOFTWARE"],"IIS") ? str_replace( '\/', '\\', $pPath) : $pPath;
        return $pPath;
    }

    public static function httpScheme() {
        return 'http'.( ( isset($_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] == 'on' ) ) ? 's' : '' );
    }

    public static function httpPrefix() {
        return KernelTools::httpScheme().'://'.$_SERVER['HTTP_HOST'];
    }

    /**
    * If an unrecoverable error has occurred, this method should be invoked. script exist occurs
    *
    * @param string $ pMsg error message to be displayed
    * @return void this function will DIE DIE DIE!!!
    * @access public
    */
    public static function install_error( $pMsg = null ) {
        global $gBitDbType;
        // here we decide where to go. if there are no db settings yet, we go the welcome page.
            $step = isset( $gBitDbType ) ? 1 : 0;

        header( "Location: ".KernelTools::httpPrefix().BIT_ROOT_URL."install/install.php?step=".$step );
        die;
    }

    /**
     * pear_check will check to see if a given PEAR module is installed
     *
     * @param string $pPearModule The name of the module in the format: Image/GraphViz.php
     * @access public
     * @return string with error message on failure, null on success
     */
    public static function pear_check( $pPearModule = null ) {
        if( !@include_once "PEAR.php") {
            return KernelTools::tra( "PEAR is not installed." );
        } elseif( !empty( $pPearModule ) && !@include_once $pPearModule ) {
            $module = str_replace( ".php", "", str_replace( "/", "_", $pPearModule ));
            return KernelTools::tra( "The PEAR plugin <strong>$module</strong> is not installed. Install it with '<strong>pear install $module</strong>' or use your distribution's package manager.");
        } else {
            return null;
        }
    }

    /**
     * A set of compare functions that can be used in conjunction with usort() type functions
     *
     * @param array $ar1
     * @param array $ar2
     * @access public
     * @return bool true on success, false on failure
     */
    public static function usort_by_title( $ar1, $ar2 ) {
        if( !empty( $ar1['title'] ) && !empty( $ar2['title'] ) ) {
            return strcasecmp( $ar1['title'], $ar2['title'] );
        } else {
            return 0;
        }
    }

    public static function compare_links( $ar1, $ar2 ) {
        return $ar1["links"] - $ar2["links"];
    }

    public static function compare_backlinks( $ar1, $ar2 ) {
        return $ar1["backlinks"] - $ar2["backlinks"];
    }

    public static function r_compare_links( $ar1, $ar2 ) {
        return $ar2["links"] - $ar1["links"];
    }

    public static function r_compare_backlinks( $ar1, $ar2 ) {
        return $ar2["backlinks"] - $ar1["backlinks"];
    }

    public static function compare_images( $ar1, $ar2 ) {
        return $ar1["images"] - $ar2["images"];
    }

    public static function r_compare_images( $ar1, $ar2 ) {
        return $ar2["images"] - $ar1["images"];
    }

    public static function compare_files( $ar1, $ar2 ) {
        return $ar1["files"] - $ar2["files"];
    }

    public static function r_compare_files( $ar1, $ar2 ) {
        return $ar2["files"] - $ar1["files"];
    }

    public static function compare_versions( $ar1, $ar2 ) {
        return $ar1["versions"] - $ar2["versions"];
    }

    public static function r_compare_versions( $ar1, $ar2 ) {
        return $ar2["versions"] - $ar1["versions"];
    }

    public static function compare_changed( $ar1, $ar2 ) {
        return $ar1["lastChanged"] - $ar2["lastChanged"];
    }

    public static function r_compare_changed( $ar1, $ar2 ) {
        return $ar2["lastChanged"] - $ar1["lastChanged"];
    }


    // ======================= deprecated functions =======================
    /**
     * @deprecated deprecated since version 2.1.0-beta
     */
    public static function chkgd2() {
        KernelTools::deprecated( 'Please use get_gd_version() instead' );
    }
}