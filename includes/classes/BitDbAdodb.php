<?php
/**
 * ADOdb Library interface Class
 *
 * @package kernel
 * @version $Header$
 *
 * Copyright (c) 2004 bitweaver.org
 * Copyright (c) 2003 tikwiki.org
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See below for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
 *
 * @author spider <spider@steelsun.com>
 */

namespace Bitweaver;

require_once EXTERNAL_LIBS_PATH.'adodb/adodb.inc.php';
// require_once EXTERNAL_LIBS_PATH.'adodb/session/adodb-session.php';

/**
 * This code must execute before adodb/adodb.inc.php runs
 * Otherwsie $ADODB_CACHE_DIR ends up being set to '/tmp'
 */
global $ADODB_CACHE_DIR;
if( empty( $ADODB_CACHE_DIR )) {
	$ADODB_CACHE_DIR = sys_get_temp_dir().'/php/adodb/'.$_SERVER['HTTP_HOST'].'/';
}
KernelTools::mkdir_p( $ADODB_CACHE_DIR );

/**
 * This class is used for database access and provides a number of functions to help
 * with database portability.
 *
 * Currently used as a base class, this class should be optional to ensure bitweaver
 * continues to function correctly, without a valid database connection.
 *
 * @package kernel
 */
class BitDbAdodb extends BitDb {
	public function __construct( $pConnectionHash = null ) {
		global $ADODB_FETCH_MODE;
		if( $pConnectionHash === null ) {
			global $gBitDbType, $gBitDbHost, $gBitDbUser, $gBitDbPassword, $gBitDbName;
			$pConnectionHash['db_type'] 	= $gBitDbType;
			$pConnectionHash['db_host']		= $gBitDbHost;
			$pConnectionHash['db_user']		= $gBitDbUser;
			$pConnectionHash['db_password']	= $gBitDbPassword;
			$pConnectionHash['db_name']		= $gBitDbName;
		}

		parent::__construct();

		// Get all the ADODB stuff included
		if( !defined( "ADODB_ASSOC_CASE" )) {
			define( "ADODB_ASSOC_CASE", 0 );
		}
		if( !defined( "ADODB_FETCH_MODE" )) {
			define( "ADODB_FETCH_MODE", ADODB_ASSOC_CASE );
		}
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

		if( !empty( $pConnectionHash['db_name'] ) && !empty( $pConnectionHash['db_type'] ) ) {
			if( $pConnectionHash['db_type'] == 'oci8' ) {
				$pConnectionHash['db_type'] = 'oci8po';
			}
			$this->mType = $pConnectionHash['db_type'];
			$this->mName = $pConnectionHash['db_name'];
			if( !isset( $this->mName )) {
				die( "No database name specified" );
			}
			$this->preDBConnection();
			$this->mDb = ADONewConnection( $pConnectionHash['db_type'] );
			$this->mDb->pdoParameters = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

			$this->mDb->Connect( $pConnectionHash['db_host'], $pConnectionHash['db_user'], $pConnectionHash['db_password'], $pConnectionHash['db_type'] != 'pdo' ? $pConnectionHash['db_name'] : NULL );
			if( !$this->mDb ) {
				die( "Unable to login to the database $pConnectionHash[db_type] on $pConnectionHash[db_host] as `user` $pConnectionHash[db_user]<p>".$this->mDb->ErrorMsg() );
			}
			$this->postDBConnection();
			unset( $pDSN );
			if( defined( "DB_PERFORMANCE_STATS" ) && constant( "DB_PERFORMANCE_STATS" )) {
				$this->mDb->LogSQL();
			}
		}

		$this->debug( $this->getDebugLevel() );
	}

	/**
	 * Used to create tables - most commonly from package/schema_inc.php files
	 * @todo remove references to BIT_DB_PREFIX, us a member function
	 * @param array pTables an array of tables and creation information in DataDict
	 * style
	 * @param array pOptions an array of options used while creating the tables
	 * @return bool true|false
	 * true if created with no errors | false if errors are stored in $this->mFailed
	 */
	public function createTables( array $pTables, array $pOptions = [] ): bool {
		// If server support InnoDB for MySql set the selected engine
		if( isset( $_SESSION['use_innodb'] )) {
			$pOptions = $_SESSION['use_innodb'] == true 
				? [ ...$pOptions, 'MYSQL' => 'ENGINE=INNODB']
				: [ ...$pOptions, 'MYSQL' => 'ENGINE=MYISAM'];
		}
		$dict = NewDataDictionary( $this->mDb, 'firebird' );
		$this->mFailed = [];
		$result = true;
		foreach( array_keys( $pTables ) AS $tableName ) {
			$completeTableName = ( defined( "BIT_DB_PREFIX" )) ? BIT_DB_PREFIX.$tableName : $tableName;
			$sql = $dict->CreateTableSQL($completeTableName, $pTables[$tableName], $pOptions);
			if( $sql && ( $dict->ExecuteSQLArray( $sql ) > 0 )) {
				// Success
			} else {
				// Failure
				$result = false;
				array_push( $this->mFailed, $sql.": ".$this->mDb->ErrorMsg() );
			}
		}
		return $result;
	}

	/**
	 * Used to check if tables already exists.
	 * @todo should be used to confirm tables are already created
	 * @param string pTable the table name
	 * @return bool true if table already exists
	 */
	public function tableExists( string $pTable ): bool {
		$dict = NewDataDictionary( $this->mDb, 'firebird' );
		$pTable = preg_replace( "/`/", "", $pTable );
		$tables = $dict->MetaTables( );
		return array_search( $pTable, $tables ) !== false;
	}

	/**
	 * Used to drop tables
	 * @todo remove references to BIT_DB_PREFIX, us a member function
	 * @param array pTables an array of table names to drop
	 * @return bool true | false
	 * true if dropped with no errors |
	 * false if errors are stored in $this->mFailed
	 */
	public function dropTables( array $pTables ): bool {
		$dict = NewDataDictionary( $this->mDb, 'firebird' );
		$this->mFailed = [];
		$return = true;
		foreach( $pTables AS $tableName ) {
			$completeTableName = ( defined( "BIT_DB_PREFIX" )) ? BIT_DB_PREFIX.$tableName : $tableName;
			$sql = $dict->DropTableSQL( $completeTableName );
			if( $sql && ( $dict->ExecuteSQLArray( $sql ) > 0 )) {
				//echo "Success<br>";
			} else {
				//echo "Failure<br>";
				$return = false;
				array_push($this->mFailed, $sql);
			}
		}
		return $return;
	}

	/**
	 * Quotes a string to be sent to the database which is
	 * passed to function on to AdoDB->qstr().
	 * @todo not sure what its supposed to do
	 * @param string pStr string to be quotes
	 * @return string quoted string using AdoDB->qstr()
	 */
	public function qstr( string $pStr ): string {
		return $this->mDb->qstr( $pStr );
	}
	
	/**
	 * Returns SUBSTRING function appropiate for database.
	 * @return string using AdoDB->substr property
	 */
	public function substr() {
		return $this->mDb->substr;
	}

	/**
	 * Returns MOD function appropiate for database.
	 * @return string using AdoDB->mod property
	 */
	public function mod_function() {
		return $this->mDb->mod;
	}

	/**
	 * Returns RANDOM function appropiate for database.
	 * Overrides BitDb::random()
	 * @return string using AdoDB->random property
	 */
	public function random() {
		return $this->mDb->random;
		
	}

	/** Queries the database, returning an error if one occurs, rather
	 * than exiting while printing the error. -rlpowell
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param string pError the error string to modify and return
	 * @param array pValues an array of values used in a parameterised query
	 * @param int pNumRows the number of rows (LIMIT) to return in this query
	 * @param int pOffset the row number to begin returning rows from. Used in
	 * @return array an AdoDB RecordSet object
	 * conjunction with $pNumRows
	 * @todo currently not used anywhere.
	 */
	public function queryError( string $pQuery, string &$pError, ?array $pValues = null, int $pNumRows = -1, int $pOffset = -1 ): array {
		$this->convertQuery( $pQuery );
		$result = $pNumRows == -1 && $pOffset == -1 
			? $this->mDb->Execute($pQuery, $pValues) 
			: $this->mDb->SelectLimit($pQuery, $pNumRows, $pOffset, $pValues);

		if( !$result ) {
			$pError = $this->mDb->ErrorMsg();
			$result=[];
		}
		//count the number of queries made
		$this->mNumQueries++;
		//$this->debugger_log($pQuery, $pValues);
		return $result;
	}

	/** Queries the database reporting an error if detected
	 * than exiting while printing the error. -rlpowell
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array|null pValues an array of values used in a parameterised query
	 * @param int pNumRows the number of rows (LIMIT) to return in this query
	 * @param int pOffset the row number to begin returning rows from. Used in
	 * conjunction with $pNumRows
	 * @return \ADORecordSet|null an AdoDB RecordSet object
	 */
	public function query( $query, $values = false, $numrows = BIT_QUERY_DEFAULT, $offset = BIT_QUERY_DEFAULT, $pCacheTime=BIT_QUERY_DEFAULT ) {
		$this->convertQuery( $query );
		if( empty( $this->mDb )) {
			return null;
		}
		$values = $values === null ? false : $values;
		$this->queryStart();

		if( !is_numeric( $numrows )) {
			$numrows = BIT_QUERY_DEFAULT;
		}

		if( !is_numeric( $offset )) {
			$offset = BIT_QUERY_DEFAULT;
		}

		$result = $numrows == BIT_QUERY_DEFAULT && $offset == BIT_QUERY_DEFAULT
			? ( !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT 
				? $this->mDb->Execute( $query, $values ) 
				: $this->mDb->CacheExecute( $pCacheTime, $query, $values ) )
			: ( !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT
				? $this->mDb->SelectLimit( $query, $numrows, $offset, $values )
				: $this->mDb->CacheSelectLimit( $pCacheTime, $query, $numrows, $offset, $values ) );

		$this->queryComplete();
		return $result;
	}

	/**
	 * List columns in a database as an array of ADOFieldObjects.
	 * See top of file for definition of object.
	 *
	 * @param string tabletable name to query
	 * @param bool upper	uppercase table name (required by some databases)
	 * @param bool schema is optional database schema to use - not supported by all databases.
	 *
	 * @return array of ADOFieldObjects for current table.
	 */
	public function MetaColumns( string $table, bool $normalize=true, bool $schema=false ): array {
		$table = str_replace( '`', '', $table );
		return $this->mDb->MetaColumns( $table, $normalize );
	}

	/**
	 * List indexes in a database as an array of ADOFieldObjects.
	 * See top of file for definition of object.
	 *
	 * @param string table	table name to query
	 * @param bool primary list primary indexes
	 * @param bool owner list owner of index
	 *
	 * @return  array of ADOFieldObjects for current table.
	 */
	public function MetaIndexes( string $table, bool $primary=false, bool $owner=false): array {
		$table = str_replace( '`', '', $table );
		return $this->mDb->MetaIndexes( $table, $primary, $owner );
	}

	/**
	 * getAll 
	 * 
	 * @param string $pQuery 
	 * @param array $pValues 
	 * @param numeric $pCacheTime 
	 * @access public
	 * @return array|bool true on success, false on failure - mErrors will contain reason for failure
	 */
	public function getAll( $pQuery, $pValues = [], $pCacheTime=BIT_QUERY_DEFAULT ) {
		if( empty( $this->mDb )) {
			return false;
		}
		$this->queryStart();
		$this->convertQuery( $pQuery );
		$result = !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT 
		? $this->mDb->GetAll( $pQuery, $pValues )
		: $this->mDb->CacheGetAll($pCacheTime, $pQuery, $pValues );
		//count the number of queries made
		$this->queryComplete();
		return $result;
	}

	/** Executes the SQL and returns all elements of the first column as a 1-dimensional array. The recordset is discarded for you automatically. If an error occurs, false is returned.
	 * See AdoDB GetCol() function for more detail.
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array pValues an array of values used in a parameterised query
	 * @param bool pForceArray if set to true, when an array is created for each value
	 * @param bool pFirst2Cols if set to true, only returns the first two columns
	 * @return array|bool the associative array, or false if an error occurs
	 * @todo not currently used anywhere
	 */
	public function getCol( $pQuery, $pValues = false, $pTrim=false, $pCacheTime=BIT_QUERY_DEFAULT ) {
		if( empty( $this->mDb )) {
			return false;
		}
		$this->queryStart();
		$this->convertQuery( $pQuery );
		$result = !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT
			? $this->mDb->GetCol( $pQuery, $pValues, $pTrim )
			: $this->mDb->CacheGetCol( $pCacheTime, $pQuery, $pValues, $pTrim );
		//count the number of queries made
		$this->queryComplete();
		return $result;
	}

	/** Returns an associative array for the given query.
	 * See AdoDB GetAssoc() function for more detail.
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array pValues an array of values used in a parameterised query
	 * @param bool pForceArray if set to true, when an array is created for each value
	 * @param bool pFirst2Cols if set to true, only returns the first two columns
	 * @return array|bool associative array, or false if an error occurs
	 */
	public function getArray( $pQuery, $pValues = false, $pForceArray=false, $pFirst2Cols=false, $pCacheTime=BIT_QUERY_DEFAULT ) {
		if( empty( $this->mDb )) {
			return false;
		}
		$this->queryStart();
		$this->convertQuery( $pQuery );
		$result = !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT
			? $this->mDb->GetArray( $pQuery, $pValues )
			: $this->mDb->CacheGetArray( $pCacheTime, $pQuery, $pValues );
		$this->queryComplete();
		return $result;
	}

	/** Returns an associative array for the given query.
	 * See AdoDB GetAssoc() function for more detail.
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array pValues an array of values used in a parameterised query
	 * @param bool pForceArray if set to true, when an array is created for each value
	 * @param bool pFirst2Cols if set to true, only returns the first two columns
	 * @return array|false the associative array, or false if an error occurs
	 */
	public function getAssoc( $pQuery, $pValues = false, $pForceArray=false, $pFirst2Cols=false, $pCacheTime=BIT_QUERY_DEFAULT ) {
		if( empty( $this->mDb )) {
			return false;
		}
		$this->queryStart();
		$this->convertQuery( $pQuery );
		$result = !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT
			? $this->mDb->GetAssoc( $pQuery, $pValues, $pForceArray, $pFirst2Cols )
			: $this->mDb->CacheGetAssoc( $pCacheTime, $pQuery, $pValues, $pForceArray, $pFirst2Cols );
		$this->queryComplete();
		return $result;
	}

	/** Executes the SQL and returns the first row as an array. The recordset and remaining rows are discarded for you automatically. If an error occurs, false is returned.
	 * See AdoDB GetRow() function for more detail.
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array pValues an array of values used in a parameterised query
	 * @return array returns the first row as an array, or false if an error occurs
	 */
	public function getRow( $pQuery, $pValues = [], $pCacheTime=BIT_QUERY_DEFAULT ) {
		if( empty( $this->mDb ) ) {
			return [];
		}
		$this->queryStart();
		$this->convertQuery($pQuery);
		$result = !$this->isCachingActive() || $pCacheTime == BIT_QUERY_DEFAULT
			? $this->mDb->GetRow( $pQuery, $pValues )
			: $this->mDb->CacheGetRow( $pCacheTime, $pQuery, $pValues );
		$this->queryComplete();
		return $result;
	}

	/** Returns a single column value from the database.
	 * @param string pQuery the SQL query. Use backticks (`) to quote all table
	 * and attribute names for AdoDB to quote appropriately.
	 * @param array pValues an array of values used in a parameterised query
	 * @param int pOffset the row number to begin returning rows from.
	 * @return string the associative array, or false if an error occurs
	 */
	public function getOne( $pQuery, $pValues = [], $pNumRows=BIT_QUERY_DEFAULT, $pOffset=BIT_QUERY_DEFAULT, $pCacheTime = BIT_QUERY_DEFAULT ) {
		$result = $this->query($pQuery, $pValues, 1, $pOffset, $pCacheTime );
		$res = ( $result != null ) ? $result->fetchRow() : false;
		if( $res === false ) {
			//simulate pears behaviour
			return '';
		}

		$ret = current( $res );
		return $ret;
	}

	/**
	 * A database portable Sequence management function.
	 *
	 * @param string pSequenceName Name of the sequence to be used
	 *		It will be created if it does not already exist
	 * @return		0 if not supported, otherwise a sequence id
	 */
	public function GenID( $pSequenceName, $pUseDbPrefix = true ) {
		if( empty( $this->mDb )) {
			return false;
		}
		$prefix = $pUseDbPrefix ? str_replace( "`", "", BIT_DB_PREFIX ) : '';
		return $this->mDb->GenID( $prefix.$pSequenceName );
	}

	/**
	 * A database portable Sequence management function.
	 *
	 * @param string pSequenceName Name of the sequence to be used
	 *		It will be created if it does not already exist
	 * @param int pStartID Allows setting the initial value of the sequence
	 * @return bool		0 if not supported, otherwise a sequence id
	 * @todo	To be combined with GenID
	 */
	public function CreateSequence( $pSeqname='adodbseq',$startID=1 ) {
		if( empty( $this->mDb->_genSeqSQL )) {
			return false;
		}
		return $this->mDb->CreateSequence( $pSeqname, $startID );
	}

	/**
	 * A database portable Sequence management function.
	 *
	 * @param string pSequenceName Name of the sequence to be dropped
	 * @return	false if not supported
	 */
	public function DropSequence( $pSeqname='adodbseq' ) {
		if( empty( $this->mDb->_dropSeqSQL )) {
			return false;
		}
		return $this->mDb->DropSequence( $pSeqname );
	}

	/**
	 * A database portable IFnull function.
	 *
	 * @param string pField argument to compare to null
	 * @param string pNullRepl the null replacement value
	 * @return string that represents the function that checks whether
	 * $pField is null for the given database, and if null, change the
	 * value returned to $pNullRepl.
	 */
	public function ifNull($pField, $pNullRepl): string {
		return $this->mDb->ifNull( $pField, $pNullRepl );
	}

	/** Format the timestamp in the format the database accepts.
	 * @param int pDate a Unix integer timestamp or an ISO format Y-m-d H:i:s
	 * @return string the timestamp as a quoted string.
	 * @todo could be used to later convert all int timestamps into db
	 * timestamps. Currently not used anywhere.
	 */
	public function ls( $pDate ) {
		// not sure what this did - maybe someone can comment why its here
		//return preg_replace("/'/","", $this->mDb->DBTimeStamp($pDate));
		return $this->mDb->DBTimeStamp($pDate);
	}

	/**
	 * Format date column in sql string given an input format that understands Y M D
	 */
	function SQLDate( $pDateFormat, $pBaseDate=false ) {
		return $this->mDb->SQLDate( $pDateFormat, $pBaseDate );
	}

	/**
	 * Calculate the offset of a date for a particular database and generate
	 * appropriate SQL. Useful for calculating future/past dates and storing
	 * in a database.
	 * @param float pDays Number of days to offset by
	 *		If dayFraction=1.5 means 1.5 days from now, 1.0/24 for 1 hour.
	 * @param string pColumn Value to be offset
	 *		If null an offset from the current time is supplied
	 * @return string New number of days
	 *
	 * @todo Not currently used - this is database specific and uses TIMESTAMP
	 * rather than unix seconds
	 */
	public function OffsetDate( $pDays, $pColumn=null ) {
		return $this->mDb->OffsetDate( $pDays, $pColumn );
	}

	/** Converts backtick (`) quotes to the appropriate quote for the
	 * database.
	 * @private
	 * @param string pQuery the SQL query using backticks (`)
	 * @return void the correctly quoted SQL statement
	 * @todo investigate replacement by AdoDB NameQuote() function
	 */
	public function convertQuery( string &$pQuery ): void {
		if( !empty( $this->mType )) {
			switch( $this->mType ) {
			case "oci8":
				// convert bind variables - adodb does not do that
				$qe = explode( "?", $pQuery );
				$pQuery = "";
				for( $i = 0; $i < sizeof($qe) - 1; $i++ ) {
					$pQuery .= $qe[$i] . ":" . $i;
				}
				$pQuery .= $qe[$i];
			default:
				parent::convertQuery( $pQuery );
				break;
			}
		}
	}

	/** will activate ADODB's native debugging output
	 * @param bool|int pLevel debugging level - false is off, true is on, 99 is verbose
	 **/
	public function debug( bool|int $pLevel=99 ): void {
		parent::debug( $pLevel );
		if( is_object( $this->mDb )) {
			$this->mDb->debug = $pLevel;
		}
	}

	/** returns the level of query debugging output
	 * @return bool|int pLevel debugging level - false is off, true is on, 99 is verbose
	 **/
	public function getDebugLevel(): bool|int {
		return $this->mDebug ?? false;
	}

	/**
	 *	Improved method of initiating a transaction. Used together with CompleteTrans().
	 *	Advantages include:
	 *
	 *	a. StartTrans/CompleteTrans is nestable, unlike BeginTrans/CommitTrans/RollbackTrans.
	 *	   Only the outermost block is treated as a transaction.<br>
	 *	b. CompleteTrans auto-detects SQL errors, and will rollback on errors, commit otherwise.<br>
	 *	c. All BeginTrans/CommitTrans/RollbackTrans inside a StartTrans/CompleteTrans block
	 *	   are disabled, making it backward compatible.
	 */
	function StartTrans() {
		return is_object( $this->mDb ) && $this->mDb->StartTrans();
	}

	/**
	 *	Used together with StartTrans() to end a transaction. Monitors connection
	 *	for sql errors, and will commit or rollback as appropriate.
	 *
	 *	autoComplete if true, monitor sql errors and commit and rollback as appropriate,
	 *	and if set to false force rollback even if no SQL error detected.
	 *	@return bool true on commit, false on rollback.
	 */
	function CompleteTrans() {
		return is_object( $this->mDb ) && $this->mDb->CompleteTrans();
	}

	/**
	 * If database does not support transactions, rollbacks always fail, so return false
	 * otherwise returns true if the Rollback was successful
	 *
	 * @return bool true/false.
	 */
	function RollbackTrans() {
		$this->mDb->FailTrans();
		return $this->mDb->CompleteTrans( false );
	}

	/**
	 * Create a list of tables available in the current database
	 *
	 * @param bool|string ttype can either be 'VIEW' or 'TABLE' or false.
	 * 		If false, both views and tables are returned.
	 *		"VIEW" returns only views
	 *		"TABLE" returns only tables
	 * @param bool showSchema returns the schema/user with the table name, eg. USER.TABLE
	 * @param bool mask  is the input mask - only supported by oci8 and postgresql
	 *
	 * @return array of tables for current database.
	 */
	public function MetaTables( bool|string $ttype = false, bool $showSchema = false, bool $mask = false ): bool|array {
		return $this->mDb->MetaTables( $ttype, $showSchema, $mask );
	}

	/**
	 * @return # rows affected by UPDATE/DELETE
	 */
	function Affected_Rows() {
		return $this->mDb->Affected_Rows();
	}
}

/*
 * Custom ADODB Error Handler. This will be called with the following params
 *
 * @param $dbms		the RDBMS you are connecting to
 * @param $fn		the name of the calling function (in uppercase)
 * @param $errno		the native error number from the database
 * @param $errmsg	the native error msg from the database
 * @param $p1		$fn specific parameter - see below
 * @param $P2		$fn specific parameter - see below
 */
function bitdb_error_handler( $dbms, $fn, $errno, $errmsg, $p1, $p2, &$thisConnection ) {
	global $gBitDb;
	if( ini_get( 'error_reporting' ) == 0 ) {
		return; // obey @ protocol
	}

	$dbParams = [
		'db_type'=>$dbms,
		'call_func'=>$fn,
		'errno'=>$errno,
		'db_msg'=>$errmsg,
		'sql'=>$p1,
		'p2'=>$p2
	];
	$logString = bit_error_string( $dbParams );

	/*
	 * Log connection error somewhere
	 *	0 message is sent to PHP's system logger, using the Operating System's system
	 *		logging mechanism or a file, depending on what the error_log configuration
	 *		directive is set to.
	 *	1 message is sent by email to the address in the destination parameter.
	 *		This is the only message type where the fourth parameter, extra_headers is used.
	 *		This message type uses the same internal function as mail() does.
	 *	2 message is sent through the PHP debugging connection.
	 *		This option is only available if remote debugging has been enabled.
	 *		In this case, the destination parameter specifies the host name or IP address
	 *		and optionally, port number, of the socket receiving the debug information.
	 *	3 message is appended to the file destination
	 */
	error_log( $logString,0 );
	$subject = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'BITWEAVER';

	$fatal = false;
	if(( $fn == 'EXECUTE' ) && ( $thisConnection->MetaError() != -5 ) && (empty( $gBitDb ) || $gBitDb->isFatalActive()) ) {
		$fatal = true;
	}

	bit_display_error( $logString, $dbParams['db_msg'], $fatal );
}